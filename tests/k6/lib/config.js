// Configuration centrale des scénarios k6 — tout se pilote par variables
// d'environnement, rien n'est codé en dur (voir docs/PERF_TESTS_K6.md).
//
// ⚠️ Contraintes serveur à connaître avant de monter la charge
// (backend/app/Providers/AppServiceProvider.php) :
//   - throttle:auth    → 10 requêtes / 15 min / IP sur POST /auth/login ;
//   - throttle:api     → par rôle et par utilisateur : super_admin 300/min,
//                        admin 200/min, enseignant 120/min, parent 60/min
//                        (divisé par 2 entre 22h et 6h) ;
//   - surveillance/webhook → 60 req/min/IP.
// Les profils par défaut ci-dessous restent SOUS ces limites avec un seul
// compte admin ; pour aller plus loin, répartir sur plusieurs comptes
// (K6_EMAILS en liste) ou relever les limites sur l'environnement de perf.

export const BASE_URL = (__ENV.K6_BASE_URL || 'http://localhost:8000').replace(/\/+$/, '');

// Comptes de test : K6_EMAILS accepte une liste séparée par des virgules
// (répartition de charge) ; K6_PASSWORDS soit une liste parallèle, soit un
// mot de passe unique appliqué à tous. Le compte doit être SANS 2FA (sinon
// login renvoie two_factor_required et aucun access_token).
export function comptes() {
  const emails = (__ENV.K6_EMAILS || '').split(',').map((s) => s.trim()).filter(Boolean);
  const passwords = (__ENV.K6_PASSWORDS || '').split(',').map((s) => s.trim()).filter(Boolean);
  if (emails.length === 0) {
    throw new Error(
      'K6_EMAILS/K6_PASSWORDS non définis — renseignez un compte de test sans 2FA (docs/PERF_TESTS_K6.md).'
    );
  }
  return emails.map((email, i) => ({ email, password: passwords[i] || passwords[0] || '' }));
}

// Seuils par défaut (à recalibrer après les premières exécutions — le but
// des premières campagnes est justement d'établir la baseline).
export const seuils = {
  p95: Number(__ENV.K6_P95_MS || 800),
  echecs: Number(__ENV.K6_MAX_ECHECS || 0.01),
};

// Profil de charge par défaut des scénarios « lecture » : 5 VU max
// (~100-150 req/min avec le pacing intégré, sous la limite admin de 200/min).
export const profil = {
  vus: Number(__ENV.K6_VUS || 5),
  ramp: __ENV.K6_RAMP || '30s',
  hold: __ENV.K6_HOLD || '3m',
};

// Isolation multi-tenant (scénario isolation-rls) : un 2e compte appartenant
// à un AUTRE établissement, et le nom (ou début de nom) d'un élève qui
// appartient à cet autre établissement. Depuis le compte A, la recherche de
// ce nom ne doit JAMAIS renvoyer de résultat.
export const isolation = {
  emailB: __ENV.K6_EMAIL_B || '',
  passwordB: __ENV.K6_PASSWORD_B || '',
  eleveB: __ENV.K6_ELEVE_B_NOM || '',
};
