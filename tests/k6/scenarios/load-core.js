// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// LOAD-CORE — parcours de lecture standard sous charge
//
// Chaque VU se connecte une fois (compte attribué round-robin depuis
// K6_EMAILS), puis boucle : profil → liste élèves (page 25) → recherche
// → séances → factures, avec un temps de réflexe de 1 à 3 s.
//
// Profil par défaut : 5 VU (~100-150 req/min, sous la limite throttle:api
// d'un compte admin à 200/min). Pour charger plus : K6_VUS=20 et
// K6_EMAILS=multi-comptes, ou relever les limites sur l'env de perf.
//
// Usage :
//   K6_EMAILS=... K6_PASSWORDS=... k6 run tests/k6/scenarios/load-core.js
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
import { sleep } from 'k6';
import { handleSummary } from '../lib/report.js';
import { seuils, profil } from '../lib/config.js';
import { obtenirToken, getAuth } from '../lib/api.js';

export const options = {
  stages: [
    { duration: profil.ramp, target: profil.vus },
    { duration: profil.hold, target: profil.vus },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_failed: [`rate<${seuils.echecs}`],
    http_req_duration: [`p(95)<${seuils.p95}`],
    checks: ['rate>0.95'],
  },
};

const TERMES = ['be', 'am', 'ka', 'ma', 'sa', 'ra', 'ali', 'med', 'kha'];

export default function () {
  const token = obtenirToken();

  getAuth(token, '/auth/me', 'GET /auth/me', [200]);
  sleep(1);

  getAuth(token, '/eleves?per_page=25', 'GET /eleves', [200]);
  sleep(1);

  const terme = TERMES[Math.floor(Math.random() * TERMES.length)];
  getAuth(token, `/search?q=${terme}`, 'GET /search', [200]);
  sleep(1);

  getAuth(token, '/seances', 'GET /seances', [200]);
  sleep(1);

  // Factures : rôles finance uniquement — 403 attendu (et sain) pour un
  // compte enseignant, 200 pour admin/gestionnaire/comptable.
  getAuth(token, '/factures', 'GET /factures', [200, 403]);
  sleep(Math.random() * 2 + 1);
}
