// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// BULLETINS — génération de bulletins en charge (ÉCRITURE lourde)
//
// POST /api/v1/bulletins/generer { groupe_id (uuid), trimestre T1|T2|T3,
// annee_scolaire } — réservé aux rôles admin/gestionnaire/secretariat.
//
// ⚠️ ÉCRITURES en base : à lancer UNIQUEMENT sur l'environnement de perf
// avec des données semées (K6_GROUPE_ID = uuid d'un groupe de test).
// Sans K6_GROUPE_ID, le scénario bombarde le chemin de validation
// (uuid inexistant → 422 propre, jamais 5xx).
//
// Usage :
//   K6_EMAILS=... K6_PASSWORDS=... [K6_GROUPE_ID=...] \
//   k6 run tests/k6/scenarios/bulletins.js
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
    // La génération est lourde (calcul des moyennes par élève) : seuil
    // distinct, plus généreux que la lecture.
    http_req_duration: [`p(95)<${__ENV.K6_P95_MS_ECRITURE || 3000}`],
    checks: ['rate>0.95'],
  },
};

const GROUPE_ID = __ENV.K6_GROUPE_ID || '';
const ANNEE = __ENV.K6_ANNEE_SCOLAIRE || '2025-2026';
const TRIMESTRE = __ENV.K6_TRIMESTRE || 'T1';

export default function () {
  const token = obtenirToken();
  // Sans groupe réel : uuid inconnu → 422 attendu (chemin de validation
  // sous charge). Avec K6_GROUPE_ID : 200 et bulletins réellement générés.
  postAuth(
    token,
    '/bulletins/generer',
    {
      groupe_id: GROUPE_ID || '00000000-0000-4000-8000-000000000000',
      trimestre: TRIMESTRE,
      annee_scolaire: ANNEE,
    },
    'POST /bulletins/generer',
    GROUPE_ID ? [200] : [422]
  );
  sleep(2);
}
