import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import {
  getAccessToken,
  setAccessToken,
  clearAccessToken,
  isExpired,
  onTokenChange,
  purgerAncienStockage,
} from '../tokenStore';

/**
 * Sprint 3 — Le jeton d'accès ne doit JAMAIS être persisté.
 *
 * Avant, il vivait dans localStorage : lisible par toute XSS, par une
 * dépendance npm compromise ou une extension, et conservé après fermeture
 * de l'onglet. Ces tests verrouillent le nouveau comportement.
 */
describe('tokenStore', () => {
  beforeEach(() => {
    clearAccessToken();
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  describe('stockage en mémoire', () => {
    it('restitue le jeton qu\'on lui confie', () => {
      setAccessToken('jeton-abc', 3600);
      expect(getAccessToken()).toBe('jeton-abc');
    });

    it('part d\'un état vide', () => {
      expect(getAccessToken()).toBeNull();
    });

    it('efface le jeton sur demande', () => {
      setAccessToken('jeton-abc', 3600);
      clearAccessToken();
      expect(getAccessToken()).toBeNull();
    });

    /** Le point central du correctif P0-5. */
    it('n\'écrit jamais le jeton dans localStorage', () => {
      setAccessToken('jeton-secret', 3600);

      expect(localStorage.setItem).not.toHaveBeenCalledWith(
        expect.stringMatching(/token/i),
        expect.anything(),
      );
    });

    it('n\'écrit jamais le jeton dans sessionStorage', () => {
      const espion = vi.spyOn(Storage.prototype, 'setItem');
      setAccessToken('jeton-secret', 3600);
      expect(espion).not.toHaveBeenCalled();
      espion.mockRestore();
    });
  });

  describe('expiration', () => {
    it('considère expiré un stockage vide', () => {
      expect(isExpired()).toBe(true);
    });

    it('considère valide un jeton frais', () => {
      setAccessToken('jeton', 3600);
      expect(isExpired()).toBe(false);
    });

    /**
     * Marge de 30 s : envoyer un jeton qui expire pendant le vol de la
     * requête produirait un 401 évitable.
     */
    it('anticipe l\'expiration de 30 secondes', () => {
      vi.useFakeTimers();
      setAccessToken('jeton', 60);

      vi.advanceTimersByTime(29_000);
      expect(isExpired()).toBe(false);

      vi.advanceTimersByTime(2_000); // 31 s → dans la marge
      expect(isExpired()).toBe(true);
    });

    it('traite un jeton sans durée comme non expirant', () => {
      setAccessToken('jeton');
      expect(isExpired()).toBe(false);
    });
  });

  describe('abonnements', () => {
    it('notifie les abonnés à chaque changement', () => {
      const abonne = vi.fn();
      onTokenChange(abonne);

      setAccessToken('nouveau', 3600);
      expect(abonne).toHaveBeenCalledWith('nouveau');

      clearAccessToken();
      expect(abonne).toHaveBeenCalledWith(null);
    });

    it('permet de se désabonner', () => {
      const abonne = vi.fn();
      const desabonner = onTokenChange(abonne);

      desabonner();
      setAccessToken('x', 3600);

      expect(abonne).not.toHaveBeenCalled();
    });

    /** Un abonné qui lève ne doit pas empêcher les autres d'être notifiés. */
    it('isole les abonnés défaillants', () => {
      const casse = vi.fn(() => { throw new Error('boum'); });
      const sain = vi.fn();

      onTokenChange(casse);
      onTokenChange(sain);

      expect(() => setAccessToken('x', 3600)).not.toThrow();
      expect(sain).toHaveBeenCalled();
    });
  });

  describe('purge du stockage hérité', () => {
    /**
     * Sans cette purge, un utilisateur déjà connecté avant la migration
     * garderait indéfiniment un jeton en clair dans son navigateur, alors
     * même que l'application ne s'en sert plus.
     */
    it('supprime les jetons laissés par l\'ancienne version', () => {
      purgerAncienStockage();

      expect(localStorage.removeItem).toHaveBeenCalledWith('access_token');
      expect(localStorage.removeItem).toHaveBeenCalledWith('refresh_token');
    });

    it('ne lève pas si le Storage est indisponible', () => {
      const espion = vi.spyOn(Storage.prototype, 'removeItem')
        .mockImplementation(() => { throw new Error('SSR'); });

      expect(() => purgerAncienStockage()).not.toThrow();
      espion.mockRestore();
    });
  });
});
