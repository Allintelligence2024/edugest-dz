// Rapport de fin d'exécution : écrit un JSON complet + un résumé lisible.
// handleSummary() fonctionne sur toutes les versions de k6 (remplace
// --summary-export, déprisé puis retiré) et garantit que les résultats
// sont PUBLIÉS (fichier) et pas seulement affichés — exigence du lot G :
// « publie les résultats, ne les résume pas en "ça tient" ».
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.0.4/index.js';

export function rapport(data) {
  const dir = __ENV.K6_REPORT_DIR || 'results';
  const nom = __ENV.K6_REPORT_NAME || 'k6';
  const horodatage = new Date().toISOString().replace(/[:.]/g, '-');
  const fichier = `${dir}/${nom}-${horodatage}.json`;

  // Métadonnées utiles au comparatif entre campagnes.
  data.meta = {
    scenario: nom,
    base_url: __ENV.K6_BASE_URL || 'http://localhost:8000',
    vus: __ENV.K6_VUS || '5',
    execute_le: new Date().toISOString(),
  };

  return {
    [fichier]: JSON.stringify(data, null, 2), // publication complète
    stdout: textSummary(data, { indent: ' ', enableColors: true }),
  };
}
