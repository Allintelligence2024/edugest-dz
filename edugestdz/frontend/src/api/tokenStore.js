/**
 * Sprint 3 — Stockage du jeton d'accès EN MÉMOIRE uniquement.
 *
 * Avant : `localStorage.setItem('access_token', ...)`. Tout script injecté
 * (XSS, dépendance npm compromise, extension) pouvait lire le jeton et
 * usurper la session — y compris après fermeture de l'onglet, puisque
 * localStorage persiste.
 *
 * Après :
 *   - le jeton d'accès (courte durée) vit dans une variable de module :
 *     inaccessible depuis un autre contexte, effacé au rechargement ;
 *   - le refresh token (longue durée) est dans un cookie httpOnly, que le
 *     JavaScript ne peut pas lire du tout ;
 *   - au démarrage, on rejoue /auth/refresh pour récupérer un jeton d'accès
 *     à partir du cookie : la session survit au rechargement sans jamais
 *     exposer de secret au JS.
 */

let accessToken = null;
let expiresAt = 0;

const abonnes = new Set();

function notifier() {
  abonnes.forEach((fn) => {
    try {
      fn(accessToken);
    } catch {
      /* un abonné défaillant ne doit pas casser les autres */
    }
  });
}

export function getAccessToken() {
  return accessToken;
}

export function setAccessToken(token, expiresInSeconds) {
  accessToken = token || null;
  // Marge de 30 s pour éviter d'envoyer un jeton qui expire en vol.
  expiresAt = token && expiresInSeconds
    ? Date.now() + (expiresInSeconds - 30) * 1000
    : 0;
  notifier();
}

export function clearAccessToken() {
  accessToken = null;
  expiresAt = 0;
  notifier();
}

export function isExpired() {
  return !accessToken || (expiresAt > 0 && Date.now() >= expiresAt);
}

export function onTokenChange(fn) {
  abonnes.add(fn);
  return () => abonnes.delete(fn);
}

/**
 * Purge les jetons hérités de l'ancienne implémentation.
 *
 * Sans cela, un utilisateur déjà connecté garderait indéfiniment un jeton
 * en clair dans son navigateur alors que l'application ne s'en sert plus.
 */
export function purgerAncienStockage() {
  try {
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
    sessionStorage.removeItem('access_token');
    sessionStorage.removeItem('refresh_token');
  } catch {
    /* environnements sans Storage (SSR, tests) */
  }
}
