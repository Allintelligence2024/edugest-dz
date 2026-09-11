# 📋 Changelog — EduGest DZ

Toutes les versions notables sont documentées ici.
Format basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).

---

## [Non publié] — Sprint 6 (septembre 2026)

### Ajouté
- **Pentest ciblé des 4 composants de sécurité** : 7 constats (C1-C7), 3 corrigés — rôle admin sur le kill-switch MPC et le dashboard sécurité (C1, critique : DoS global possible avec 2 comptes quelconques), `/health` mentait sur l'état du kill-switch (C4), `audit:verify` désormais planifié chaque nuit + cron serverless (C6) ; garde-fou RBAC étendu (`docs/SPRINT6_EXPLOITATION.md` § 3)
- **Observabilité Sentry** : alertes de sécurité (brute force, verrouillage d'urgence), scores Zero-Trust > 50, événements kill-switch, chaîne d'audit rompue — câblage no-op sans `SENTRY_DSN` ; instrumentation mobile `sentry-expo` (no-op sans `EXPO_PUBLIC_SENTRY_DSN`)
- **Conformité 18-07** : consentements parentaux tracés de façon immuable (`POST /api/v1/rgpd/consentements` + historique), rétention automatique quotidienne des exports (RGPD 30 j, audit 1 an — `edugest:rgpd-retention`), registre des traitements pré-rempli (`docs/REGISTRE_TRAITEMENTS.md`)
- **Scénarios de charge k6** : 7 scénarios (smoke, charge, isolation RLS, QR, bulletins, throttle auth, webhook) + lanceur + guide (`tests/k6/`, `docs/PERF_TESTS_K6.md`)
- Réparation de la chaîne backup/restore (`restore-backup.sh` rejoué sur stubs)

### Ajouté (suites pentest, clôture Sprint 6)
- **Shim CI `edugestdz/`** : symlinks de compatibilité pour que les workflows existants (qui pointent vers `edugestdz/*`) trouvent le code déplacé à la racine — la CI de branche redevient verte sans toucher `.github/` ; à supprimer dès l'application de `docs/fusion-workflows.patch`
- **C2 corrigé** : middleware `jwt.blacklist` appliqué à toutes les routes authentifiées — le verrouillage d'urgence invalide désormais les JWT existants
- **C3 partiel** : code du challenge Zero-Trust envoyé hors-bande (e-mail), plus jamais dans la réponse 428 ; nouvel endpoint `POST /security/zero-trust/verify` (+ 5 tests)
- **C5 partiel** : `TRUSTED_PROXIES` opt-in (`bootstrap/app.php`) pour un `$request->ip()` correct derrière proxy
- **Mobile** : `AuthContext.js` corrigé (référence `api` non importée → crash du logout et suppression silencieuse du token au démarrage) ; suite de tests Jest réparée : **4 suites / 21 tests verts** (chemins d'import, mock i18n du LoginScreen, assertions alignées sur les écrans réels)

### Corrigé
- `README.md`, `docs/SECURITE.md`, `docs/ARCHITECTURE.md` (grille Risk Score fictive remplacée par la vraie), `docs/ANPDP_DECLARATION.md`, `docs/MONITORING_CHECKLIST.md` : alignés sur la réalité (58 wilayas et non 48, Merkle HMAC-SHA-256 et non SHA3, MFA obligatoire seulement super-admin, crypto classique et non post-quantique, compteurs réels, lien plan d'incident, argumentaire commercial ANPDP reformulé)
- Lockfile mobile resynchronisé avec `package.json` (`npm ci` échouait)

---

## [1.0.0-beta] — 8 Juillet 2026

### Ajouté
- **Sécurité Niveau 6** : Audit Chain Merkle HMAC-SHA-256, SIEM 5 règles, Kill Switch MPC, crypto asymétrique Ed25519/RSA-4096 (classique, pas post-quantique), Supply Chain Verifier — 19 tests
- **Sécurité Niveau 5** : Honeypots actifs (16 routes leurres), Canary Tokens, SSRF Protection, SQL Injection Layer, HashiCorp Vault (fallback BDD chiffrée), Insider Threat Detector, Dead Man Switch — 24 tests
- **Sécurité Niveau 4** : Zero-Trust Risk Score Engine, Device Fingerprinting, RBAC granulaire par champ, Intelligent Rate Limiter — 18 tests
- **Sécurité Niveau 3** : Audit logs HMAC-SHA256, Password Policy (12 chars + blacklist), IP Allowlist super-admin, JWT rotation, Breach Response API — 9 tests
- **Sécurité Niveau 2** : Chiffrement colonnes AES-256, MFA (obligatoire super-admin), Brute force protection, Headers OWASP complets — 11 tests
- **Sécurité Niveau 1** : JWT Blacklist Redis, PostgreSQL RLS, Isolation tenant triple, Fichiers signés URL temporaires
- **BEM/BAC** : Module examens officiels, 5 tables, 22 endpoints, 5 PDFs, 12 tests
- **LMS** : Cours en ligne, chapitres, leçons (vidéo/PDF/quiz), quiz auto-corrigés, certificats — 13 tests
- **Bibliothèque** : Catalogue, prêts, retours, amendes, réservations
- **Module Manager** : Activation/désactivation de 14 modules par tenant
- **WhatsApp Business** : Intégration API Meta officielle
- **Google Classroom** : Synchronisation OAuth2 cours et devoirs
- **Surveillance Dahua** : Webhooks, alertes caméras
- **Diagnostic EWS** : Scoring élèves à risque, plans de rattrapage, convocations parents
- **Communication parents** : SMS Twilio, push Firebase, WhatsApp
- **Déploiement 3 niveaux** : SaaS Cloud DZ (Hostarts), Hybride OVH, Self-Hosted
- **Theme dark/light** + i18n 4 langues (FR, AR, EN, Darija)
- **Documentation** : Swagger/OpenAPI tous les endpoints, guides utilisateur, docs sécurité

### Statistiques
- 607 tests automatisés ✅
- 35+ controllers API REST
- 55+ modèles Eloquent
- 60+ migrations
- 25+ services métier
- 10 middlewares sécurité en série

---

## [0.9.0] — 5 Juillet 2026

### Ajouté
- Transport scolaire : circuits, arrêts, pointage bus
- Cantine : menus, inscriptions, pointage repas
- Stock : inventaire, mouvements, bons de commande
- Personnel non-enseignant : contrats, congés, paie
- Budget annuel : dépenses, prévisionnel, bilan
- Entretien bâtiment : locaux, interventions, préventif
- Notifications push Firebase + Twilio SMS
- Rapports PDF absences mensuels
- Simulation BEM/BAC

---

## [0.8.0] — 30 Juin 2026

### Ajouté
- Finance : factures, paiements CIB/Dahabia (Satim sandbox)
- Paie enseignants : barème IRG 2026 + CNAS
- Billets entrée/retard/sortie/convocation
- Marketplace : cours particuliers, matching, réservations, avis
- 2FA TOTP : activation, vérification, codes de secours
- Audit logs : Spatie ActivityLog intégration

---

## [0.5.0] — 20 Juin 2026

### Ajouté
- Multi-tenancy complet : isolation BDD + middleware
- Authentification JWT : login, logout, refresh
- Gestion élèves et parents : CRUD complet + import CSV
- Planning : emploi du temps, séances, conflits
- Notes et évaluations : saisie, moyennes, bulletins PDF
- Gestion enseignants : dossiers, contrats, pointage

---

## [0.1.0] — 13 Juin 2026 — Initial commit

### Ajouté
- Structure initiale du projet Laravel 11
- Configuration PostgreSQL + Redis + Meilisearch
- Docker Compose (9 services)
- GitHub Actions CI/CD
- Modèle multi-tenant de base
