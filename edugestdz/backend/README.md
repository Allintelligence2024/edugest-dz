# EduGest DZ — Backend (Laravel 11)

Plateforme SaaS multi-tenant de gestion des établissements éducatifs algériens.

## Stack technique

| Couche | Technologie | Version |
|--------|-------------|---------|
| Framework | Laravel | 11 |
| Langage | PHP | 8.2+ |
| Base de données | PostgreSQL | 16 |
| Cache & Queue | Redis | 7 |
| Recherche | Meilisearch | v1.8 |
| Authentification | JWT (tymon/jwt-auth) | — |
| Documentation API | L5-Swagger (OpenAPI 3) | — |
| Audit | Spatie ActivityLog | — |
| Tests | PHPUnit (via Artisan) | — |

## Prérequis

- PHP 8.2+ avec extensions : `pdo_pgsql`, `redis`, `gd`, `intl`, `mbstring`, `zip`
- PostgreSQL 16
- Redis 7
- Composer 2

## Installation développement

```bash
# 1. Dépendances
composer install

# 2. Configuration
cp .env.example .env
# → Éditer .env : DB_*, REDIS_*, JWT_*, APP_KEY

# 3. Clés critiques
php artisan key:generate    # APP_KEY (chiffrement Laravel)
php artisan jwt:secret      # JWT_SECRET (signature tokens)

# 4. Vérification configuration
php artisan edugest:check-config

# 5. Base de données
php artisan migrate --seed

# 6. Serveur de développement
php artisan serve           # → http://localhost:8000

# 7. Documentation API (Swagger)
php artisan l5-swagger:generate
# → http://localhost:8000/api/documentation
```

## Tests

```bash
# Tous les tests en parallèle (recommandé)
php artisan test --parallel
# → 607 tests ✅  0 failures  6 skipped

# Avec couverture de code
php artisan test --coverage --min=50

# Un test spécifique
php artisan test tests/Feature/Security/SecurityNiveau1Test.php

# Un groupe de tests
php artisan test --filter=SecurityTest
```

## Commandes Artisan personnalisées

### Configuration & Santé
| Commande | Description |
|----------|-------------|
| `php artisan edugest:check-config` | Vérifie APP_KEY, JWT, BDD, Redis, fuseau horaire |
| `php artisan edugest:check-config --secrets-only` | Vérifie uniquement les secrets cryptographiques |

### Opérations métier
| Commande | Description | Scheduler |
|----------|-------------|-----------|
| `php artisan edugest:calculer-paies` | Calcule les paies mensuelles (IRG/CNAS) | 1er du mois |
| `php artisan edugest:generer-seances` | Génère les séances hebdomadaires | Lundi 5h |
| `php artisan edugest:sms-absents` | SMS automatiques parents pour absences | Lun-Ven 8h30 |
| `php artisan edugest:relances-impayes` | Relances SMS paiements J+1/J+3/J+7/J+15 | Quotidien 9h |
| `php artisan edugest:alertes-stock` | Alertes articles sous seuil minimum | Quotidien 7h |
| `php artisan edugest:alertes-preventif` | Alertes entretiens préventifs à échéance | Quotidien 7h |

### Sécurité
| Commande | Description | Scheduler |
|----------|-------------|-----------|
| `php artisan edugest:nettoyer-jwt-blacklist` | Supprime les tokens JWT expirés | Dimanche 3h |
| `php artisan edugest:audit-export` | Exporte et signe les logs du jour (HMAC) | Quotidien 2h |
| `php artisan edugest:audit-chain-verify` | Vérifie l'intégrité de la chaîne Merkle | Quotidien 1h |
| `php artisan edugest:jwt-rotate` | Rotation manuelle du JWT_SECRET | Manuel |
| `php artisan edugest:dead-man-switch` | Alerte si aucun admin connecté depuis 7j | Quotidien 9h |
| `php artisan edugest:siem-analyse` | Corrélation événements sécurité (SIEM) | Toutes les 5min |
| `php artisan edugest:supply-chain-verify` | Vérifie intégrité des dépendances Composer | Lundi 4h |

### Données
| Commande | Description |
|----------|-------------|
| `php artisan edugest:chiffrer-donnees` | Chiffre les données sensibles non encore chiffrées |
| `php artisan db:seed --class=CurriculumAlgerienSeeder` | Seed curriculum national algérien |

## Modules disponibles (14 modules + extras)

| Code | Module | Description |
|------|--------|-------------|
| M01 | Inscriptions | Dossier élève, parents, import CSV, QR code |
| M02 | Planning | Emploi du temps, séances, détection conflits |
| M03 | Finance | Factures, paiements CIB/Dahabia, relances |
| M04 | Pédagogie | Notes, moyennes pondérées, bulletins PDF |
| M05 | Enseignants | Dossier, contrats, paie IRG/CNAS barème 2026 |
| M06 | Communication | SMS Twilio, WhatsApp Business, push Firebase |
| M07 | Reporting | Dashboards, exports Excel/PDF |
| M08 | Auth/RBAC | JWT + 2FA TOTP + RBAC granulaire par champ |
| M09 | Transport | Circuits, arrêts, pointage bus temps réel |
| M10 | Cantine | Menus nutritifs, inscriptions, pointage repas |
| M11 | Stock | Inventaire, mouvements, bons de commande |
| M12 | Personnel | Non-enseignant, congés, paie |
| M13 | Budget | Dépenses, prévisionnel, bilan annuel |
| M14 | Entretien | Locaux, interventions, préventif planifié |
| EXT | Bibliothèque | Catalogue, prêts, retours, amendes |
| EXT | BEM/BAC | Calendrier examens, salles, surveillants ONEC |
| EXT | LMS | Cours en ligne, quiz, certificats |
| EXT | Diagnostic EWS | Scoring élèves à risque, convocations |
| EXT | Surveillance | Intégration caméras Dahua, alertes |
| EXT | Marketplace | Cours particuliers, matching, réservations |

## Conventions API

```
Base URL    : /api/v1
Auth header : Authorization: Bearer {JWT_TOKEN}

Réponse succès :
{
  "success": true,
  "data": { ... },
  "meta": { "pagination": {...} },
  "message": ""
}

Réponse erreur :
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Message lisible",
    "details": { ... }
  },
  "status": 4xx
}

Pagination : ?page=1&per_page=15
Tri        : ?sort=nom&order=asc
Recherche  : ?search=terme
```

## Structure des dossiers

```
app/
├── Http/
│   ├── Controllers/Api/V1/    # 35+ controllers REST
│   │   ├── SuperAdmin/        # Gestion multi-tenants
│   │   └── Marketplace/       # Module marketplace
│   └── Middleware/            # 10 middlewares sécurité
├── Models/                    # 55+ modèles Eloquent (tous avec BelongsToTenant)
├── Services/                  # 25+ services métier
│   ├── Marketplace/
│   ├── Paiement/
│   ├── Sms/
│   └── WhatsApp/
├── Casts/                     # EncryptedString (AES-256)
├── Observers/                 # AuditChainObserver, BulletinObserver, NoteObserver
├── Traits/                    # BelongsToTenant
├── Console/Commands/          # Toutes les commandes artisan
└── Policies/                  # Autorisation par ressource
database/
├── migrations/                # 60+ migrations (ordre garanti)
├── factories/                 # 20+ factories pour les tests
└── seeders/                   # CurriculumAlgerien, Wilayas, etc.
tests/
├── Feature/
│   ├── Security/              # SecurityNiveau1Test à SecurityNiveau6Test
│   └── Api/                   # Tests par module
└── Unit/                      # Tests unitaires services
```

## Normes Algérie intégrées

| Domaine | Norme appliquée |
|---------|----------------|
| Fuseau horaire | `Africa/Algiers` (UTC+1) |
| Monnaie | DZD — Dinar Algérien |
| Impôt revenus | Barème IRG 2026 (tranches officielles) |
| Charges sociales | CNAS 9% employé + 26% employeur |
| Divisions administratives | 58 wilayas (données seedées) |
| Calendrier scolaire | National algérien (ONEC) |
| Protection données | Loi 18-07 (ANPDP) |
| Paiement électronique | CIB + Dahabia (réseau Satim) |

## Sécurité — Résumé technique

10 middlewares appliqués sur chaque requête (dans l'ordre) :
1. `KillSwitchMiddleware` — Arrêt d'urgence global (2 admins requis)
2. `HoneypotRouteMiddleware` — Détection bots et scanners
3. `SqlInjectionDetectorMiddleware` — 18 patterns SQL bloqués
4. `JwtBlacklistCheck` — Révocation tokens temps réel
5. `ZeroTrustMiddleware` — Risk Score 0-100 par requête
6. `ResolveTenant` — Contexte tenant + SET LOCAL PostgreSQL RLS
7. `TenantIsolationVerifier` — Détection manipulation header X-Tenant-ID
8. `MfaRequired` — 2FA obligatoire pour admin/super_admin
9. `IntelligentRateLimiter` — Rate limiting adaptatif par rôle
10. `SecurityHeaders` — CSP, HSTS, X-Frame-Options, etc.

→ [Documentation sécurité détaillée](../docs/SECURITE.md)
