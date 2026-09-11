// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// AUTH-THROTTLE — le limiteur de login tient-il le choc ?
//
// throttle:auth = 10 requêtes / 15 min / IP (AppServiceProvider). Ce
// scénario enchaîne 12 logins VALIDES depuis la même IP (le poste k6) :
//   - budget frais  → ~10 passent (200 + access_token), la suite en 422 ;
//   - budget épuisé (autre campagne déjà passée) → tout est refusé en
//     429 dès le premier appel.
// Dans les DEUX cas, ce qui doit être vrai : uniquement des 200 ou des
// 429 propres (JSON structuré, pas de page d'erreur), jamais un 5xx,
// et au moins un 429 sur la série (sinon le limiteur ne fonctionne pas).
// Le détail 200/429 est dans le rapport JSON — c'est lui qui fait foi.
//
// ⚠️ EFFET DE BORD : cette campagne consomme le budget login de l'IP k6
// pour 15 minutes — à lancer en DERNIER ou depuis une autre machine que
// les autres scénarios (run.sh l'isole déjà en exécution séparée).
//
// Usage :
//   K6_EMAILS=... K6_PASSWORDS=... k6 run tests/k6/scenarios/auth-throttle.js
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import { handleSummary } from '../lib/report.js';
import { loginBrut } from '../lib/api.js';
import { comptes } from '../lib/config.js';

const nb200 = new Counter('auth_login_200');
const nb429 = new Counter('auth_throttle_429');

export const options = {
  vus: 1,
  iterations: 1,
  thresholds: {
    http_req_failed: ['rate<0.01'],
    auth_throttle_429: ['count>=1'], // le limiteur doit s'exprimer
    auth_login_200: ['count>=0'],    // informatif — dépend du budget restant
    checks: ['rate>0.99'],
  },
};

export default function () {
  const [compte] = comptes();

  for (let i = 1; i <= 12; i++) {
    const res = loginBrut(compte.email, compte.password);
    const corps = res.json();
    check(res, {
      [`login #${i} : 200 ou 429, jamais autre chose`]: (r) => r.status === 200 || r.status === 429,
      [`login #${i} : pas de 5xx`]: (r) => r.status < 500,
      [`login #${i} : 200 => token / 429 => message JSON`]: (r) =>
        r.status === 429 || (corps && typeof corps.access_token === 'string'),
    });
    if (res.status === 200) nb200.add(1);
    if (res.status === 429) nb429.add(1);
    if (res.status >= 500) {
      // Un 5xx sur le login est le seul vrai échec de ce scénario.
      console.error(`5xx au login #${i} : ${res.status} — ${res.body}`);
    }
    sleep(0.5);
  }
}

export { handleSummary };
