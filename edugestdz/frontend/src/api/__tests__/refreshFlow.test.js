import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';

/**
 * Sprint 3 — Flux de rafraîchissement silencieux.
 *
 * Deux propriétés critiques sont vérifiées ici :
 *
 *  1. `credentials: 'include'` — sans lui le navigateur ne transmet pas le
 *     cookie httpOnly, et le refresh échoue systématiquement ;
 *  2. la **sérialisation** — plusieurs 401 simultanés ne doivent déclencher
 *     qu'UN seul appel réseau. Des appels concurrents seraient interprétés
 *     côté serveur comme la réutilisation d'un jeton volé, ce qui révoquerait
 *     toute la lignée et déconnecterait l'utilisateur légitime.
 */
describe('flux de rafraîchissement (client fetch)', () => {
  let client;

  beforeEach(async () => {
    vi.resetModules();
    vi.unstubAllGlobals();

    const { clearAccessToken } = await import('../tokenStore');
    clearAccessToken();

    client = await import('../client');
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  const reponse = (status, body = {}) => ({
    ok: status >= 200 && status < 300,
    status,
    json: async () => body,
  });

  it('transmet les credentials pour que le cookie httpOnly parte', async () => {
    const fetchMock = vi.fn().mockResolvedValue(reponse(200, { data: [] }));
    vi.stubGlobal('fetch', fetchMock);

    await client.api('/eleves');

    expect(fetchMock).toHaveBeenCalledWith(
      expect.stringContaining('/eleves'),
      expect.objectContaining({ credentials: 'include' }),
    );
  });

  it('appelle /auth/refresh avec les credentials', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      reponse(200, { access_token: 'nouveau', expires_in: 3600 }),
    );
    vi.stubGlobal('fetch', fetchMock);

    await client.rafraichirJeton();

    expect(fetchMock).toHaveBeenCalledWith(
      expect.stringContaining('/auth/refresh'),
      expect.objectContaining({ method: 'POST', credentials: 'include' }),
    );
  });

  it('stocke le nouveau jeton en mémoire après rafraîchissement', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
      reponse(200, { access_token: 'jeton-frais', expires_in: 3600 }),
    ));

    await client.rafraichirJeton();

    const { getAccessToken } = await import('../tokenStore');
    expect(getAccessToken()).toBe('jeton-frais');
  });

  it('rejoue la requête initiale après un 401', async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(reponse(401))
      .mockResolvedValueOnce(reponse(200, { access_token: 'jeton2', expires_in: 3600 }))
      .mockResolvedValueOnce(reponse(200, { data: 'ok' }));
    vi.stubGlobal('fetch', fetchMock);

    const res = await client.api('/eleves');

    expect(res).toEqual({ data: 'ok' });
    expect(fetchMock).toHaveBeenCalledTimes(3);

    // La requête rejouée doit porter le nouveau jeton.
    const derniere = fetchMock.mock.calls[2][1];
    expect(derniere.headers.Authorization).toBe('Bearer jeton2');
  });

  /** La propriété anti-fausse-alerte de vol. */
  it('ne lance qu\'un seul refresh pour des 401 concurrents', async () => {
    let appelsRefresh = 0;
    let jetonPose = false;

    const fetchMock = vi.fn(async (url) => {
      if (String(url).includes('/auth/refresh')) {
        appelsRefresh += 1;
        // Latence volontaire : c'est pendant cette fenêtre que les appels
        // concurrents doivent se rattacher au refresh déjà en cours.
        await new Promise((r) => { setTimeout(r, 10); });
        jetonPose = true;
        return reponse(200, { access_token: 'jeton-partage', expires_in: 3600 });
      }
      // Premier passage : 401. Une fois le jeton obtenu, succès.
      return jetonPose ? reponse(200, { data: 'ok' }) : reponse(401);
    });
    vi.stubGlobal('fetch', fetchMock);

    await Promise.all([
      client.api('/a'),
      client.api('/b'),
      client.api('/c'),
      client.api('/d'),
    ]);

    expect(appelsRefresh).toBe(1);
  });

  it('signale la session expirée si le refresh échoue', async () => {
    vi.stubGlobal('fetch', vi.fn(async (url) =>
      String(url).includes('/auth/refresh') ? reponse(401) : reponse(401),
    ));

    const surExpiration = vi.fn();
    client.setSessionExpiredHandler(surExpiration);

    await expect(client.api('/eleves')).rejects.toThrow('SESSION_EXPIRED');
    expect(surExpiration).toHaveBeenCalled();
  });

  it('efface le jeton mémoire quand la session expire', async () => {
    const { setAccessToken, getAccessToken } = await import('../tokenStore');
    setAccessToken('perime', 3600);

    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reponse(401)));
    client.setSessionExpiredHandler(() => {});

    await expect(client.api('/eleves')).rejects.toThrow('SESSION_EXPIRED');
    expect(getAccessToken()).toBeNull();
  });

  /** Sans ce garde-fou, un refresh en 401 se rappellerait indéfiniment. */
  it('ne tente pas de rafraîchir la route de refresh elle-même', async () => {
    const fetchMock = vi.fn().mockResolvedValue(reponse(401));
    vi.stubGlobal('fetch', fetchMock);
    client.setSessionExpiredHandler(() => {});

    await expect(client.api('/auth/refresh')).rejects.toThrow('SESSION_EXPIRED');
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('rejette un refresh qui ne renvoie pas de jeton', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reponse(200, { success: true })));

    await expect(client.rafraichirJeton()).rejects.toThrow('REFRESH_SANS_JETON');
  });

  /** Après un échec, un nouvel essai doit repartir proprement. */
  it('réarme le verrou après un refresh en échec', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reponse(401)));
    await expect(client.rafraichirJeton()).rejects.toThrow();

    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
      reponse(200, { access_token: 'ok', expires_in: 3600 }),
    ));
    await expect(client.rafraichirJeton()).resolves.toBe('ok');
  });
});
