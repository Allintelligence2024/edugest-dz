// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// SMOKE — la stack répond-elle correctement ? (à lancer en premier)
//
// 1 VU, 1 itération : ping, health complet, login, profil, liste élèves,
// recherche, analytics. Tout échec de check fait échouer la campagne
// (seuil checks > 99 %) : inutile de charger une stack à moitié debout.
//
// Usage :
//   K6_EMAILS=... K6_PASSWORDS=... k6 run tests/k6/scenarios/smoke.js
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
import { check } from 'k6';
import http from 'k6/http';
import { handleSummary } from '../lib/report.js';
import { BASE_URL } from '../lib/config.js';
import { obtenirToken, getAuth } from '../lib/api.js';

export const options = {
  vus: 1,
  iterations: 1,
  thresholds: {
    // Un smoke qui ne vérifie rien ne sert à rien : tout doit passer.
    checks: ['rate>0.99'],
    http_req_failed: ['rate<0.01'],
  },
};

export default function () {
  // ── Public : ping (UptimeRobot) et health complet ──
  const ping = http.get(`${BASE_URL}/api/v1/health/ping`, { tags: { name: 'GET /health/ping' } });
  check(ping, { 'ping 200': (r) => r.status === 200 });

  const health = http.get(`${BASE_URL}/api/v1/health`, { tags: { name: 'GET /health' } });
  check(health, {
    'health 200 (pas degraded)': (r) => r.status === 200,
    'health: base de données ok': (r) => {
      const c = r.json('checks.database');
      return c && c.status === 'ok';
    },
    'health: redis ok': (r) => {
      const c = r.json('checks.redis');
      return c && c.status === 'ok';
    },
    'health: kill-switch inactif': (r) => {
      const c = r.json('checks.kill_switch');
      return c && c.status === 'inactive';
    },
  });

  // ── Authentification (échoue proprement si 2FA activée) ──
  const token = obtenirToken(0);

  // ── Parcours authentifié minimal ──
  getAuth(token, '/auth/me', 'GET /auth/me', [200]);
  getAuth(token, '/eleves?per_page=25', 'GET /eleves', [200]);
  getAuth(token, '/search?q=am', 'GET /search', [200]);
  getAuth(token, '/analytics/dashboard', 'GET /analytics/dashboard', [200]);
}

export { handleSummary };
