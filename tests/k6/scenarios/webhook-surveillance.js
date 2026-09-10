// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// WEBHOOK-SURVEILLANCE — le webhook public Dahua en charge
//
// POST /api/v1/surveillance/webhook — PUBLIC, throttle 60 req/min/IP.
// Payload Dahua natif (cf. DahuaWebhookService::normaliserPayload).
//
// Deux modes :
//   - par défaut : SerialNo inconnu → le service répond 200
//     {received:true, processed:false} (alerte ignorée) : on vérifie que
//     le chemin reste propre sous charge ;
//   - K6_CAMERA_SERIAL=<serial d'une caméra de test active> : des
//     alertes RÉELLES sont créées (écritures + notifications) — à
//     réserver à l'environnement de perf.
//
// Au-delà de 60 req/min/IP, le throttle renvoie 429 : attendu et sain.
//
// Usage :
//   k6 run tests/k6/scenarios/webhook-surveillance.js   # sans authentification
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
import { check, sleep } from 'k6';
import http from 'k6/http';
import { handleSummary } from '../lib/report.js';
import { BASE_URL } from '../lib/config.js';

const SERIAL = __ENV.K6_CAMERA_SERIAL || 'PERF-UNKNOWN-0000';

// Cadence visée : ~45 req/min < throttle 60/min (on teste le webhook,
// pas son limiteur — auth-throttle s'en charge pour /auth/login).
export const options = {
  vus: 1,
  duration: __ENV.K6_HOLD || '2m',
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<500'],
    checks: ['rate>0.95'],
  },
};

const CODES = ['VideoMotion', 'AlarmLocal', 'CrossLineDetection', 'VideoLoss'];

export default function () {
  const payload = {
    IpAddress: '192.168.1.64',
    SerialNo: SERIAL,
    ChannelID: 1,
    LocaleTime: new Date().toISOString().replace('T', ' ').slice(0, 19),
    Events: [
      {
        Code: CODES[Math.floor(Math.random() * CODES.length)],
        Action: 'Start',
        Index: 0,
      },
    ],
  };

  const res = http.post(`${BASE_URL}/api/v1/surveillance/webhook`, JSON.stringify(payload), {
    headers: { 'Content-Type': 'application/json' },
    tags: { name: 'POST /surveillance/webhook' },
  });
  check(res, {
    'webhook : 200 ou 429 (throttle)': (r) => r.status === 200 || r.status === 429,
    'webhook : pas de 5xx': (r) => r.status < 500,
    'webhook : JSON reçu=true': (r) => {
      const c = r.json();
      return r.status === 429 || (c && c.received === true);
    },
  });

  sleep(1.3); // ≈ 46 req/min
}

export { handleSummary };
