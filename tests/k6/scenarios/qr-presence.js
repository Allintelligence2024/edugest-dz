// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// QR-PRESENCE — le scan de QR de présence en charge (chemin d'erreur)
//
// POST /api/v1/presences/scan exige { qr_token, seance_id (uuid existant) }.
// Sans jetons QR réels (générés par une séance ouverte), ce scénario
// bombarde le CHEMIN D'ERREUR : jetons aléatoires + uuid inexistants →
// la validation doit répondre 422 proprement, sans 5xx, même sous charge.
// Un 500 sous charge ici = bug à corriger avant le chemin nominal.
//
// Pour tester le chemin NOMINAL (scan valide), voir la procédure manuelle
// dans docs/PERF_TESTS_K6.md § « QR nominal ».
//
// Usage :
//   K6_EMAILS=... K6_PASSWORDS=... k6 run tests/k6/scenarios/qr-presence.js
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
import { sleep } from 'k6';
import { handleSummary } from '../lib/report.js';
import { seuils, profil } from '../lib/config.js';
import { obtenirToken, postAuth } from '../lib/api.js';

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

// uuid v4 aléatoire — n'existe pas en base : c'est le but (validation 422).
function uuidAleatoire() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

export default function () {
  const token = obtenirToken();
  postAuth(
    token,
    '/presences/scan',
    { qr_token: `perf-${Math.random().toString(36).slice(2)}`, seance_id: uuidAleatoire() },
    'POST /presences/scan (invalide)',
    [422]
  );
  sleep(1);
}
