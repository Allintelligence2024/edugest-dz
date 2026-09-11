// Helpers HTTP partagés par les scénarios : login avec cache par VU,
// en-têtes authentifiés, vérification de statut avec compteur de 429.
import http from 'k6/http';
import { check, fail } from 'k6';
import exec from 'k6/execution';
import { Counter } from 'k6/metrics';
import { BASE_URL, comptes } from './config.js';

// Compteur global : nombre de réponses 429 (limites de débit atteintes).
export const reponses429 = new Counter('http_429');

// Cache de token PAR VU : en k6, l'état module-level est propre à chaque VU
// (un runtime JS par VU) — chaque VU ne se connecte donc qu'une fois, et
// les différents comptes de K6_EMAILS sont répartis round-robin sur les VU.
const tokens = new Map();

function indexCompte() {
  // exec.vu n'existe pas dans setup() ; on retombe alors sur le compte 0.
  try {
    return Math.max(0, (exec.vu.idInTest || 1) - 1);
  } catch (_e) {
    return 0;
  }
}

// POST /api/v1/auth/login — renvoie le token ou null. Ne met PAS en cache
// (utilisé par auth-throttle qui doit refaire des logins réels).
export function loginBrut(email, password) {
  const res = http.post(
    `${BASE_URL}/api/v1/auth/login`,
    JSON.stringify({ email, password }),
    { headers: { 'Content-Type': 'application/json' }, tags: { name: 'POST /auth/login' } }
  );
  if (res.status === 429) reponses429.add(1);
  return res;
}

// Login du compte i (ou du compte attribué au VU), avec cache par VU.
export function obtenirToken(i = null) {
  const comptesDispos = comptes();
  const idx = i === null ? indexCompte() % comptesDispos.length : i;
  if (tokens.has(idx)) return tokens.get(idx);

  const c = comptesDispos[idx];
  const res = loginBrut(c.email, c.password);
  const corps = res.json();

  const ok = check(res, {
    'login 200': (r) => r.status === 200,
    'login sans 2FA': () => corps && corps.two_factor_required !== true,
    'login token reçu': () => corps && typeof corps.access_token === 'string' && corps.access_token.length > 0,
  });
  if (!ok) {
    fail(`login impossible pour ${c.email} (statut ${res.status}) — compte de test invalide, inactif, ou 2FA activée (voir docs/PERF_TESTS_K6.md)`);
  }

  tokens.set(idx, corps.access_token);
  return corps.access_token;
}

export function entetesAuth(token) {
  return {
    Authorization: `Bearer ${token}`,
    'Content-Type': 'application/json',
  };
}

// GET authentifié + check de statut attendu (liste blanche). Compte les
// réponses 429 sans les traiter en échec : en charge, atteindre une limite
// de débit est un comportement SERVEUR NORMAL — ce qui ne l'est pas, c'est
// un 5xx. Le statut exact est vérifié par le scénario via `check`.
export function getAuth(token, chemin, nom, statutsAttendus = [200]) {
  const res = http.get(`${BASE_URL}/api/v1${chemin}`, {
    headers: entetesAuth(token),
    tags: { name: nom },
  });
  if (res.status === 429) reponses429.add(1);
  check(res, {
    [`${nom} : statut ${statutsAttendus.join('|')}`]: (r) => statutsAttendus.includes(r.status),
    [`${nom} : pas de 5xx`]: (r) => r.status < 500,
  });
  return res;
}

// POST authentifié, même logique que getAuth.
export function postAuth(token, chemin, corps, nom, statutsAttendus = [200]) {
  const res = http.post(`${BASE_URL}/api/v1${chemin}`, JSON.stringify(corps), {
    headers: entetesAuth(token),
    tags: { name: nom },
  });
  if (res.status === 429) reponses429.add(1);
  check(res, {
    [`${nom} : statut ${statutsAttendus.join('|')}`]: (r) => statutsAttendus.includes(r.status),
    [`${nom} : pas de 5xx`]: (r) => r.status < 500,
  });
  return res;
}
