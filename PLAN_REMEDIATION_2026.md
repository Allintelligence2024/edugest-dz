# Plan de Remédiation & d'Amélioration Totale — EduGest DZ

> Date : 2026-09-07 · Branche : `arena/01a07cee-edugest-dz`
> Base : audit externe (partiellement exact, ~80 %) + contre-audit code réel effectué sur le repo.
> Ce plan corrige et complète l'audit précédent avec les chiffres réellement mesurés.

---

## 0. État des lieux réel (mesuré, pas estimé)

| Métrique | Valeur réelle | Commentaire |
|---|---|---|
| Contrôleurs API v1 | 75 (12 443 lignes) | |
| Services | 47 (7 434 lignes) | |
| Modèles Eloquent | 106 | |
| Migrations | 90 | |
| **Policies** | **3** | ⚠️ pour 106 modèles |
| Contrôleurs appelant `authorize()` | **3 / 75** | 🔴 **trou d'autorisation majeur** |
| Middlewares | 17 | riche, bien pensé |
| Tests backend | 138 fichiers / ~1 027 méthodes | badge README « 607 » à re-vérifier |
| Tests frontend | 11 fichiers | pour 50 pages → très faible |
| Tests mobile | 6 fichiers | pour 28 écrans → très faible |
| Seuil coverage CI backend | `--min=30` | trop bas |
| Tables sous RLS PostgreSQL | 43 (conditionnel) | garde `if driver !== pgsql` |
| Binaires commités | 61 MB (pas 188) | `.exe` × 2 + `.msix` |
| Poids `.git` | 56 MB | repo total 132 MB |
| Fichiers `.md` à la racine | 94 | prompts IA, pas de la doc |
| Fichiers `.html` à la racine | 19 | maquettes / rapports |
| Stack réelle | React **19.2**, Vite **8**, Laravel 11, PHP 8.2, Expo 52, RN 0.76 | l'audit disait React 18 |

---

## 1. Classification des problèmes

> **Avancement** — Sprint 1 ✅ terminé · Sprint 2 ✅ terminé (voir
> `edugestdz/docs/RBAC_MATRIX.md` et `edugestdz/DEPLOIEMENT_VERCEL.md`).
> P0-1, P0-2, P0-3, P0-4 résolus. Restent P0-5 (localStorage) et P0-6 (clé
> HMAC audit), traités au Sprint 3.

### 🔴 P0 — Bloquants (correctness + sécurité exploitable)

| # | Problème | Localisation | Impact |
|---|---|---|---|
| ~~P0-1~~ ✅ | ~~Feature QR présence non fonctionnelle~~ | `EleveService` | **Corrigé Sprint 1** — HMAC déterministe, lookup O(1) |
| ~~P0-2~~ ✅ | ~~Autorisation RBAC quasi absente~~ | 72/75 contrôleurs | **Corrigé Sprint 2** — `role:`/`permission:` + 2 tests de garde |
| ~~P0-3~~ ⚠️ | ~~TenantIsolationTest orphelin~~ | — | **Faux positif** : doublon périmé, la vraie version tournait déjà en CI. Doublon supprimé. |
| ~~P0-4~~ ✅ | ~~Binaires 61 MB trackés~~ | racine | **Corrigé Sprint 1** — détrackés + `.gitignore` |
| P0-5 | **Token JWT en `localStorage`** | `frontend/src/api/client.js:10` | Vol de session via XSS |
| P0-6 | **HMAC audit chain = `config('app.key')`** | `AuditChainService.php:26` | Rotation APP_KEY ⇒ toute la chaîne d'audit invalide |

### 🟠 P1 — Majeurs

| # | Problème | Localisation |
|---|---|---|
| P1-1 | `tenant.current_id => null` : fail-open si middleware absent | `config/tenant.php:4` |
| P1-2 | RLS conditionnel silencieux (`tableHasColumn`) — couverture réelle inconnue | migration `2026_07_07_300000` |
| P1-3 | Secrets en dur dans CI (`EduGest@2026!` × 5) | `.github/workflows/ci.yml` |
| P1-4 | Mots de passe par défaut docker-compose (pgAdmin, redis-commander exposés) | `docker-compose.yml:199,218,220` |
| P1-5 | Coverage backend `--min=30`, frontend non bloquant | `ci.yml:114`, `frontend-ci.yml` |
| P1-6 | Double check tenant redondant (masque les vrais bugs de scope) | `EleveController.php:187` |
| P1-7 | Pas de refresh token silencieux côté web (mobile l'a) | `client.js` |
| P1-8 | Workflows CI éclatés racine + `edugestdz/` | `.github/` × 2 |

### 🟡 P2 — Modérés

| # | Problème |
|---|---|
| P2-1 | `ResolveTenant` renvoie 404 au lieu de 401/403 |
| P2-2 | Junk files backend : `dbtest_check.php`, `dbtest_check2.php`, `temp_junit.xml`, `test_output.txt`, `composer.phar`, `composer-setup.php` |
| P2-3 | 94 `.md` + 19 `.html` à la racine |
| P2-4 | Contrôleurs obèses (Stock 569, Entretien 464, Transport 458, Budget 442) |
| P2-5 | i18n maison sans ICU (pas de pluriel, dates, nombres) |
| P2-6 | Emoji en JSX sur 7 pages (accessibilité) |
| P2-7 | `TestUserSeeder` : `Hash::make('password')` |
| P2-8 | `JWT_SECRET` en dur dans `phpunit.xml:90` |
| P2-9 | Honeypot : 22 routes hardcodées non configurables |
| P2-10 | Pas de stratégie de versioning API au-delà du préfixe `/v1` |

---

## 2. Plan d'exécution — 6 sprints

### **Sprint 1 — Assainissement & correctness (P0-1, P0-3, P0-4, P2-2)**
*Objectif : le repo dit la vérité sur lui-même.*

**1.1 Réparer la vérification QR** — `EleveService`
- Ajouter colonne `qr_token_hash` (migration) + index unique ; ne plus réutiliser `qr_code` (qui stocke un chemin d'image).
- Remplacer bcrypt par **HMAC-SHA256 sur un secret dédié** (`config('security.qr_secret')`), payload stable `{eleve_id, tenant_id, version}` — plus de `now()` vs `updated_at`.
- Lookup **O(1)** par `where('qr_token_hash', hash_hmac(...))` au lieu du scan de tous les élèves.
- Ajouter `expires_at` + révocation (incrément `version`).
- Tests : génération, vérification, token expiré, token d'un autre tenant, token forgé.

**1.2 Réactiver le test d'isolation**
- Déplacer `/TenantIsolationTest.php` → `edugestdz/backend/tests/Feature/Security/TenantIsolationTest.php`.
- Corriger namespace, vérifier que les 16 tests passent, les rendre **bloquants en CI**.

**1.3 Purger les binaires**
- `git rm --cached "Claude Setup.exe" "Hermes-Setup.exe" python-manager-26.2.msix`
- `.gitignore` racine : `*.exe`, `*.msix`, `*.msi`, `*.phar`, `*.zip`, `*.dmg`
- Réécriture d'historique `git filter-repo` **planifiée séparément** (casse les forks/PR ouvertes → à coordonner, pas dans un commit silencieux).

**1.4 Junk backend** — supprimer `dbtest_check*.php`, `temp_junit.xml`, `test_output.txt`, `composer.phar`, `composer-setup.php` + ignorer.

---

### **Sprint 2 — Autorisation (P0-2)** ⭐ *le plus gros chantier*
*Objectif : fermer l'escalade de privilèges intra-tenant.*

- **Cartographier les rôles** : `super_admin`, `directeur`, `enseignant`, `parent`, `eleve`, `comptable`, `personnel`.
- **Matrice rôles × 75 contrôleurs** documentée dans `docs/RBAC_MATRIX.md`.
- Créer les **Policies manquantes** pour les ~25 modèles réellement exposés par l'API (Facture, Paiement, Paie, Note, Bulletin, Presence, Budget, Stock, Transport, Cantine, Lms*, Personnel…).
- Middleware `role:` + `permission:` appliqué **au niveau des groupes de routes** dans `routes/api/*.php` (defense in depth : route + policy).
- **Test de non-régression systématique** : pour chaque groupe de routes, un test « rôle non autorisé ⇒ 403 ». C'est ce test qui garantit qu'on n'a rien oublié.
- Ajouter un **test de couverture d'autorisation** : itère sur toutes les routes `auth:api` et échoue si aucune policy/middleware de rôle n'est attaché.

---

### **Sprint 3 — Durcissement sécurité (P0-5, P0-6, P1-1..P1-4, P2-1, P2-7, P2-8)**

**3.1 Auth frontend**
- Migrer vers **cookies `httpOnly` + `SameSite=Strict` + `Secure`** pour le refresh token ; access token en mémoire (Redux, jamais persisté).
- Ajouter protection CSRF (Sanctum stateful ou double-submit token).
- Implémenter l'**intercepteur de refresh silencieux** côté web (aligner sur `mobile/src/api/axios.js` qui le fait déjà bien).

**3.2 Chaîne d'audit**
- Clé HMAC dédiée `AUDIT_CHAIN_KEY`, **indépendante d'APP_KEY**, avec `key_version` stockée sur chaque enregistrement ⇒ rotation possible sans casser la vérification historique.
- Commande `php artisan audit:verify` + job planifié quotidien.

**3.3 Tenant fail-closed**
- `config/tenant.php` : supprimer le défaut `null`. Le `BelongsToTenant` scope doit **lever une exception** si aucun tenant n'est résolu, hors contexte console/queue explicitement whitelisté.
- Test : requête sans `ResolveTenant` ⇒ exception, jamais de fuite de données.

**3.4 RLS auditable**
- Commande `php artisan rls:status` qui liste par table : RLS activé O/N, policies présentes.
- Test d'intégration : les 43 tables attendues ont bien RLS actif après migration ; échec CI sinon.

**3.5 Secrets**
- CI : remplacer les mots de passe littéraux par des valeurs générées à la volée (`openssl rand`) ou des GitHub Secrets.
- docker-compose : plus aucun défaut ; `.env.example` + `install.sh` génère des secrets aléatoires. pgAdmin/redis-commander derrière un profile `--profile debug`, jamais up par défaut.
- `phpunit.xml` : `JWT_SECRET` généré au bootstrap des tests.
- `TestUserSeeder` : mot de passe aléatoire affiché en console, refus de tourner si `APP_ENV=production`.

**3.6** `ResolveTenant` → **403** (`TENANT_FORBIDDEN`), sans divulgation d'existence.

---

### **Sprint 4 — Qualité & tests (P1-5, P1-6, P1-8)**

- Supprimer les checks tenant redondants (P1-6) **après** que les tests d'isolation soient verts — ils deviennent le filet de sécurité.
- **Unifier la CI** : un seul `.github/workflows/` à la racine, jobs `backend`, `frontend`, `mobile`, `security`.
- Palier de coverage progressif et **bloquant** : backend 30 → **45 (S4)** → **60 (S5)** → 70 ; frontend 0 → 40 ; mobile 0 → 30.
- Frontend : tests sur les 15 pages critiques (auth, élèves, notes, finance, paiement).
- Mobile : tests sur les flux auth + présence + paiement.
- Ajouter au job `security` : `composer audit`, `npm audit --audit-level=high`, Gitleaks, PHPStan niveau 6, Larastan.
- **Tests N+1** : `QueryMonitor` déjà présent → l'activer en test et échouer au-delà d'un seuil de requêtes par endpoint.

---

### **Sprint 5 — Architecture & maintenabilité (P2-3, P2-4, P2-9, P2-10)**

**5.1 Hygiène racine**
```
docs/
  archive/missions/     ← les 94 MISSION_*.md, PHASE*.md, FLUX_*.md
  archive/audits/       ← AUDIT_*.md|html, RAPPORT_*
  design/               ← les 19 edugest-*.html, maquettes
  business/             ← ETUDE DE MARCHE, CAHIER DE CHARGE
```
Racine finale : `README.md`, `CHANGELOG.md`, `CONTRIBUTING.md`, `LICENSE`, `SECURITY.md`, `.gitignore`, `edugestdz/`, `scripts/`.

**5.2 Fusionner `edugestdz/` dans la racine** (ou l'inverse) — le double niveau + double `.github` est une source constante de confusion.

**5.3 Découper les contrôleurs > 350 lignes** en Actions/sous-contrôleurs REST (Stock, Entretien, Transport, Budget, Eleve, PaiementEnLigne, Cantine).

**5.4** Honeypot : routes déplacées en config (`config/security.php`), extensibles sans redéploiement de code.

**5.5** Politique de versioning API : `docs/API_VERSIONING.md` (deprecation headers, fenêtre de support, `/v2` en parallèle).

**5.6** i18n : migrer vers `i18next` (ICU, pluriels, dates/nombres, RTL robuste). Remplacer les emoji JSX par des icônes `lucide-react` avec `aria-label`.

---

### **Sprint 6 — Production readiness**

- **Load test** (k6) : RLS à 50 tenants × 5 000 élèves, vérification QR, dashboard analytics, génération de bulletins.
- **Pentest** ciblé : Zero-Trust (`score > 50` : calibrer le `RiskScoreEngine`), JWT blacklist, kill-switch MPC, chaîne Merkle.
- **Observabilité** : Sentry/Prometheus, alertes sur `TenantIsolationVerifier` et `SqlInjectionDetector`.
- **Backups** : tester réellement la restauration (`backups/` existe, procédure non prouvée).
- **Conformité Loi 18-07 / ANPDP** : compléter au-delà de `ExportRgpdController` — registre des traitements, durées de rétention, purge automatique, consentement parental.
- **Vérité du README** : recompter les tests (~1 027 méthodes mesurées vs badge 607) et requalifier « sécurité niveau bancaire » en fonctionnalités réellement testées.

---

## 3. Ordre de priorité recommandé (si temps limité)

1. **P0-2 Autorisation RBAC** — c'est le risque le plus grave et le plus silencieux.
2. **P0-3 Réactiver TenantIsolationTest** — 30 minutes, valeur énorme.
3. **P0-1 QR cassé** — fonctionnalité annoncée non fonctionnelle.
4. **P0-4 Binaires** — trivial, gain immédiat.
5. **P0-5 localStorage** — refonte auth web, plus coûteux.
6. **P0-6 clé HMAC audit** — bombe à retardement à la première rotation d'APP_KEY.

---

## 4. Critères de sortie (definition of done)

- [ ] `TenantIsolationTest` vert et bloquant en CI
- [ ] Test « rôle non autorisé ⇒ 403 » sur 100 % des groupes de routes authentifiés
- [ ] Vérification QR en O(1), 5 tests verts dont 2 d'attaque
- [ ] `git ls-files` ne renvoie aucun `.exe` / `.msix` / `.phar`
- [ ] Aucun secret littéral dans `.github/`, `docker-compose*.yml`, `phpunit.xml`
- [ ] Coverage backend ≥ 60 %, frontend ≥ 40 %, bloquants
- [ ] `php artisan rls:status` → 43/43 tables protégées
- [ ] Racine du repo ≤ 10 fichiers
- [ ] Gitleaks + PHPStan 6 + `composer audit` verts en CI
- [ ] README aligné sur les chiffres réels

---

## 5. Ce que le plan ne fait pas (décisions à valider)

- **Réécriture d'historique git** (`filter-repo`) : casse tous les clones et PR ouvertes. Nécessite une fenêtre coordonnée. Le repo pèse 132 MB avec 56 MB de `.git` — supportable à court terme.
- **Fusion `edugestdz/` ↔ racine** : gros diff, à faire sur un commit dédié quand aucune PR n'est en vol.
- **Cookies httpOnly** : impose que frontend et API partagent un domaine (ou CORS `credentials` + domaine parent commun). À valider contre le déploiement Vercel/Railway actuel.
