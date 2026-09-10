// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// ISOLATION-RLS — l'isolation inter-établissements tient-elle en charge ?
//
// Préparation : deux comptes d'établissements DIFFÉRENTS
//   - K6_EMAILS/K6_PASSWORDS      → compte A (établissement A)
//   - K6_EMAIL_B/K6_PASSWORD_B    → compte B (établissement B)
//   - K6_ELEVE_B_NOM              → nom (ou début de nom) d'un élève
//                                   appartenant à l'établissement B
//
// Chaque itération : depuis A, rechercher l'élève de B → la réponse ne
// doit JAMAIS le contenir (total 0) ; depuis B, même recherche → doit le
// trouver (témoin que la requête est correcte). Toute fuite (résultat
// non vide côté A) fait échouer la campagne : c'est un incident de
// sécurité, pas une statistique.
//
// Usage :
//   K6_EMAILS=... K6_PASSWORDS=... K6_EMAIL_B=... K6_PASSWORD_B=... \
//   K6_ELEVE_B_NOM=... k6 run tests/k6/scenarios/isolation-rls.js
//
// ⚠️ 2 logins par VU (comptes A et B) : avec le throttle:auth à 10
// requêtes / 15 min / IP, garder K6_VUS ≤ 5 — au-delà, les logins
// passent en 429 et le scénario échoue (ou relever la limite sur l'env
// de perf).
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import http from 'k6/http';
import { handleSummary } from '../lib/report.js';
import { seuils, profil, isolation, BASE_URL } from '../lib/config.js';
import { obtenirToken, entetesAuth, loginBrut } from '../lib/api.js';

// Une fuite d'isolation = échec immédiat de la campagne (seuil == 0).
const fuitesIsolation = new Counter('isolation_fuites');

export const options = {
  stages: [
    { duration: profil.ramp, target: profil.vus },
    { duration: profil.hold, target: profil.vus },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    isolation_fuites: ['count==0'],
    http_req_failed: [`rate<${seuils.echecs}`],
    http_req_duration: [`p(95)<${seuils.p95}`],
  },
};

// Cache du token du compte B, propre à chaque VU (l'état module-level
// est par VU en k6) : B ne se connecte qu'une fois par VU.
const tokensB = new Map();

function obtenirTokenB() {
  if (tokensB.has(0)) return tokensB.get(0);
  const res = loginBrut(isolation.emailB, isolation.passwordB);
  const corps = res.json();
  check(res, {
    'login compte B : 200': (r) => r.status === 200,
    'login compte B : token reçu': () => corps && typeof corps.access_token === 'string',
  });
  if (!corps || !corps.access_token) {
    throw new Error('isolation-rls : login du compte B impossible (K6_EMAIL_B/K6_PASSWORD_B)');
  }
  tokensB.set(0, corps.access_token);
  return corps.access_token;
}

export default function () {
  if (!isolation.emailB || !isolation.passwordB || !isolation.eleveB) {
    throw new Error('isolation-rls : K6_EMAIL_B, K6_PASSWORD_B et K6_ELEVE_B_NOM sont requis (docs/PERF_TESTS_K6.md)');
  }

  const tokenA = obtenirToken(0); // compte A : K6_EMAILS
  const tokenB = obtenirTokenB(); // compte B : K6_EMAIL_B

  const q = encodeURIComponent(isolation.eleveB);

  // ── Depuis A : l'élève de B ne doit JAMAIS apparaître ──
  const depuisA = http.get(`${BASE_URL}/api/v1/search?q=${q}`, {
    headers: entetesAuth(tokenA),
    tags: { name: 'GET /search (cross-tenant)' },
  });
  const corpsA = depuisA.json();
  const propre = check(depuisA, {
    'recherche cross-tenant : 200': (r) => r.status === 200,
    'AUCUN résultat côté A (isolation)': () => corpsA && (corpsA.total === 0 || (corpsA.data || []).length === 0),
  });
  if (!propre) fuitesIsolation.add(1);

  // ── Depuis B : le même élève doit être trouvé (témoin) ──
  const depuisB = http.get(`${BASE_URL}/api/v1/search?q=${q}`, {
    headers: entetesAuth(tokenB),
    tags: { name: 'GET /search (témoin même tenant)' },
  });
  const corpsB = depuisB.json();
  check(depuisB, {
    'témoin côté B : 200': (r) => r.status === 200,
    'élève trouvé côté B': () => corpsB && (corpsB.total || 0) > 0,
  });

  sleep(2);
}

export { handleSummary };
