import {
  getAccessToken,
  setAccessToken,
  clearAccessToken,
  purgerAncienStockage,
} from './tokenStore';

const _raw = import.meta.env.VITE_API_URL ?? '';
const BASE_URL = _raw.replace(/\/api\/v1\/?$/, '');

let _onSessionExpired = null;

export function setSessionExpiredHandler(fn) {
  _onSessionExpired = fn;
}

// Purge des jetons hérités du stockage localStorage (Sprint 3).
purgerAncienStockage();

/**
 * Le jeton d'accès vit en mémoire, jamais dans localStorage : un script
 * injecté ne peut plus l'exfiltrer. Le refresh token, lui, est un cookie
 * httpOnly totalement invisible au JavaScript.
 */
export function getToken() {
  return getAccessToken() ?? '';
}

export { setAccessToken, clearAccessToken };

let refreshEnCours = null;

/**
 * Rafraîchissement sérialisé : un seul appel réseau même si plusieurs
 * requêtes tombent en 401 en même temps. Des appels concurrents seraient
 * interprétés côté serveur comme une réutilisation de refresh token (vol)
 * et provoqueraient la révocation de toute la session.
 */
async function rafraichirJeton() {
  if (!refreshEnCours) {
    refreshEnCours = fetch(`${BASE_URL}/api/v1/auth/refresh`, {
      method: 'POST',
      credentials: 'include',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    })
      .then(async (res) => {
        if (!res.ok) throw new Error('REFRESH_ECHOUE');
        const data = await res.json();
        if (!data?.access_token) throw new Error('REFRESH_SANS_JETON');
        setAccessToken(data.access_token, data.expires_in);
        return data.access_token;
      })
      .finally(() => {
        refreshEnCours = null;
      });
  }
  return refreshEnCours;
}

export { rafraichirJeton };

async function envoyer(path, options, token) {
  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    ...options.headers,
  };

  if (token) headers.Authorization = `Bearer ${token}`;

  return fetch(`${BASE_URL}/api/v1${path}`, {
    ...options,
    headers,
    // Transporte le cookie httpOnly du refresh token.
    credentials: 'include',
  });
}

export async function api(path, options = {}) {
  let response = await envoyer(path, options, getAccessToken());

  // Tentative unique de rafraîchissement silencieux avant d'abandonner.
  if (response.status === 401 && !path.startsWith('/auth/refresh')) {
    try {
      const nouveauJeton = await rafraichirJeton();
      response = await envoyer(path, options, nouveauJeton);
    } catch {
      clearAccessToken();
      if (_onSessionExpired) _onSessionExpired();
      throw new Error('SESSION_EXPIRED');
    }
  }

  if (response.status === 401) {
    clearAccessToken();
    if (_onSessionExpired) _onSessionExpired();
    throw new Error('SESSION_EXPIRED');
  }

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    const msg = data?.message ?? data?.error?.message ?? `HTTP ${response.status}`;
    throw new Error(msg);
  }

  return data;
}

export function getApiUrl(path) {
  return `${BASE_URL}/api/v1${path}`;
}

export { BASE_URL };

export default api;
