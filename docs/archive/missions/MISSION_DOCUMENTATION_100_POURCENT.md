# 📚 MISSION DEEPSEEK — Documentation 100%
## EduGest DZ · Branche : develop · 8 Juillet 2026
## Tests actuels : 607 ✅ · Objectif : 0 régression · Durée estimée : 1 session

---

## CONTEXTE — État actuel de la documentation (lu dans le repo)

### Ce qui EXISTE déjà
```
✅ /README.md (racine)               — 41 lignes, très minimal (stack + install basique)
✅ /edugestdz/backend/README.md      — 119 lignes, modules listés, artisan commands basiques
✅ /edugestdz/docs/DEPLOIEMENT.md    — Guide 3 niveaux de déploiement (bien)
✅ /edugestdz/ANPDP_DECLARATION.md   — Guide loi 18-07 (bien)
✅ /edugestdz/INCIDENT_RESPONSE_PLAN.md — Plan réponse incidents (bien)
✅ Swagger/OpenAPI dans les controllers — @OA annotations sur AuthController et autres
✅ php artisan l5-swagger:generate    — génère http://localhost/api/documentation
```

### Ce qui MANQUE (documentation à 65/100)
```
❌ README.md racine trop court (41 lignes) — pas de badges, pas d'architecture, pas de sécurité
❌ Pas de CONTRIBUTING.md — comment contribuer au projet
❌ Pas de CHANGELOG.md — historique des versions
❌ Pas de docs/API_GUIDE.md — guide API pour les développeurs frontend/mobile
❌ Pas de docs/GUIDE_UTILISATEUR_DIRECTEUR.md — manuel directeur d'école
❌ Pas de docs/GUIDE_UTILISATEUR_ENSEIGNANT.md — manuel enseignant
❌ Pas de docs/GUIDE_UTILISATEUR_PARENT.md — manuel parent
❌ Pas de docs/SECURITE.md — doc sécurité pour les auditeurs/ANPDP
❌ Pas de docs/ARCHITECTURE.md — architecture technique détaillée
❌ Pas de docs/BASE_DE_DONNEES.md — schéma BDD expliqué
❌ Pas de code comments PHPDoc sur les Services (méthodes sans doc)
❌ backend/README.md non mis à jour (dit "≥ 440 tests" → réalité : 607)
❌ Pas de .env.example commenté ligne par ligne
❌ Pas de wiki GitHub
```

### Philosophie documentation EduGest DZ
```
- Public cible 1 : Développeurs (toi ou futurs collaborateurs) → docs techniques
- Public cible 2 : Directeurs d'école (clients) → guides utilisateur simples
- Public cible 3 : Auditeurs ANPDP / partenaires → docs conformité et sécurité
- Langue principale : Français (Algérie)
- Langue secondaire commentaires code : Français
- Jamais de jargon incompréhensible pour un directeur d'école
```

---

## RÈGLES ABSOLUES
1. 0 régression — ne pas toucher au code PHP/JS, uniquement les fichiers .md et les commentaires
2. Tous les fichiers markdown en UTF-8
3. Langue : Français pour les guides utilisateur, Français/Anglais pour les docs techniques
4. Chemins exacts fournis — créer exactement où indiqué
5. Après chaque étape : vérifier que `php artisan test --parallel` reste vert

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## ═══════════════════════════════════════════
## PARTIE A — README PRINCIPAL (Vitrine du projet)
## ═══════════════════════════════════════════

## ÉTAPE 1 — Remplacer le README.md racine

**Remplacer entièrement** : `README.md` (à la racine du repo)

```markdown
<div align="center">

# 🎓 EduGest DZ

**Plateforme SaaS de gestion des établissements éducatifs — Made in Algeria 🇩🇿**

*Écoles privées · Centres de cours particuliers · Lycées · Collèges*

[![CI](https://github.com/Allintelligence2024/edugest-dz/actions/workflows/ci.yml/badge.svg)](https://github.com/Allintelligence2024/edugest-dz/actions)
[![Tests](https://img.shields.io/badge/tests-607%20✅-brightgreen)](https://github.com/Allintelligence2024/edugest-dz/actions)
[![PHP](https://img.shields.io/badge/PHP-8.2-blue)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11-red)](https://laravel.com)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-blue)](https://postgresql.org)
[![License](https://img.shields.io/badge/Licence-Propriétaire-orange)](LICENSE)
[![Sécurité](https://img.shields.io/badge/Sécurité-6%20niveaux-darkred)](docs/SECURITE.md)
[![ANPDP](https://img.shields.io/badge/Loi%2018--07-Conforme-green)](ANPDP_DECLARATION.md)

</div>

---

## 🚀 Qu'est-ce qu'EduGest DZ ?

EduGest DZ est la **première plateforme SaaS algérienne** de gestion complète des établissements
éducatifs. Conçue spécifiquement pour les réalités du système éducatif algérien :

- ✅ Barèmes **IRG/CNAS** 2026 intégrés
- ✅ Curriculum **ONEC/BEM/BAC** officiel
- ✅ Paiement **CIB et Dahabia** (Satim)
- ✅ **48 wilayas** algériennes seedées
- ✅ Conforme **Loi 18-07** (protection des données personnelles)
- ✅ Interface en **Français, Arabe, Anglais et Darija**

---

## 📦 Ce que la plateforme gère

| Module | Fonctionnalités |
|--------|----------------|
| 👨‍🎓 **Élèves & Parents** | Dossiers, inscriptions, import CSV, QR code |
| 📅 **Planning** | Emploi du temps, séances, conflits automatiques |
| 💰 **Finance** | Factures, paiements CIB/Dahabia, relances automatiques |
| 📝 **Pédagogie** | Notes, moyennes pondérées, bulletins PDF |
| 👨‍🏫 **Enseignants** | Dossiers, contrats, paie IRG/CNAS, pointage |
| 📱 **Communication** | SMS Twilio, WhatsApp Business, Push Firebase |
| 🚌 **Transport** | Circuits, arrêts GPS, pointage bus |
| 🍽️ **Cantine** | Menus, inscriptions, pointage repas |
| 📦 **Stock** | Inventaire, bons de commande, alertes seuil |
| 👷 **Personnel** | Non-enseignant, congés, paie |
| 💹 **Budget** | Dépenses, prévisionnel, bilan annuel |
| 🏗️ **Entretien** | Locaux, interventions, préventif planifié |
| 📚 **Bibliothèque** | Catalogue, prêts, retours, amendes |
| 🎓 **BEM/BAC** | Calendrier examens, salles, surveillants (règles ONEC) |
| 🖥️ **LMS** | Cours en ligne, quiz auto-corrigés, certificats |
| 🔬 **Diagnostic EWS** | Détection précoce élèves en difficulté |
| 📹 **Surveillance** | Intégration caméras Dahua, alertes temps réel |
| 🔗 **Google Classroom** | Synchronisation cours et devoirs OAuth2 |
| 🛡️ **Sécurité** | 6 niveaux (Zero-Trust, Honeypots, Audit Chain Merkle) |

---

## 🏗️ Architecture

```
┌─────────────┐    ┌─────────────────────┐    ┌──────────────────┐
│  React 18   │    │    Laravel 11 API    │    │  React Native    │
│  + Vite     │◄──►│  35+ controllers    │◄──►│  Expo 52         │
│  Vercel     │    │  25+ services       │    │  18 écrans       │
└─────────────┘    └──────────┬──────────┘    └──────────────────┘
                              │
              ┌───────────────┼───────────────┐
              ▼               ▼               ▼
      ┌──────────────┐ ┌──────────┐ ┌───────────────────┐
      │ PostgreSQL16 │ │  Redis 7 │ │ Meilisearch v1.8  │
      │ RLS 40+tables│ │  Cache   │ │ Recherche temps   │
      │ Audit Merkle │ │  JWT BL  │ │ réel              │
      └──────────────┘ └──────────┘ └───────────────────┘
```

**Stack complète :**
- **Backend** : Laravel 11 · PHP 8.2 · PostgreSQL 16 · Redis 7
- **Frontend** : React 18 · Vite · Tailwind CSS
- **Mobile** : React Native 0.76 · Expo 52
- **Recherche** : Meilisearch v1.8
- **CI/CD** : GitHub Actions · Vercel · Docker Compose

---

## 🔐 Sécurité — 6 niveaux

EduGest DZ implémente une sécurité de **niveau bancaire** :

| Niveau | Protection |
|--------|------------|
| 1 | JWT Blacklist Redis + PostgreSQL RLS + Isolation tenant |
| 2 | Chiffrement colonnes sensibles + MFA obligatoire admins |
| 3 | Audit logs HMAC-SHA256 + Password Policy + IP Allowlist |
| 4 | Zero-Trust Engine + Device Fingerprinting + Risk Score 0-100 |
| 5 | Honeypots + Canary Tokens + SSRF Protection + Vault Secrets |
| 6 | Audit Chain Merkle SHA3 + SIEM + Post-Quantum + Kill Switch MPC |

→ [Documentation sécurité complète](docs/SECURITE.md)

---

## ⚡ Installation rapide (5 minutes)

### Prérequis
- Docker Desktop
- Git

### Démarrer

```bash
# 1. Cloner
git clone https://github.com/Allintelligence2024/edugest-dz.git
cd edugestdz

# 2. Configurer
cp backend/.env.example backend/.env
# Éditer backend/.env (DB, Redis, clés)

# 3. Lancer
docker compose up -d

# 4. Initialiser
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan jwt:secret
docker compose exec app php artisan migrate --seed

# 5. Accéder
# → API     : http://localhost/api/v1
# → Frontend: http://localhost:5173
# → Swagger : http://localhost/api/documentation
# → pgAdmin : http://localhost:5050 (admin@edugestdz.local / PgAdmin@2026)
```

→ [Guide d'installation complet](docs/DEPLOIEMENT.md)

---

## 🧪 Tests

```bash
cd edugestdz/backend
php artisan test --parallel
# → 607 tests ✅  0 failures  6 skipped
```

---

## 📁 Structure du projet

```
edugest-dz/
├── edugestdz/
│   ├── backend/          # Laravel 11 — API REST
│   │   ├── app/
│   │   │   ├── Http/Controllers/Api/V1/  # 35+ controllers
│   │   │   ├── Services/                  # 25+ services métier
│   │   │   ├── Models/                    # 55+ modèles Eloquent
│   │   │   ├── Http/Middleware/           # 10 middlewares sécurité
│   │   │   └── Console/Commands/          # Schedulers artisan
│   │   └── database/migrations/           # 60+ migrations
│   ├── frontend/         # React 18 + Vite
│   ├── mobile/           # React Native + Expo 52
│   └── docs/             # Documentation complète
├── docker-compose.yml    # Développement (9 services)
├── docker-compose.prod.yml
└── install.sh            # Installation self-hosted 1 commande
```

---

## 📚 Documentation

| Document | Description |
|----------|-------------|
| [Guide Déploiement](docs/DEPLOIEMENT.md) | 3 niveaux : Cloud DZ, Hybride, Self-hosted |
| [Guide Directeur](docs/GUIDE_UTILISATEUR_DIRECTEUR.md) | Manuel complet directeur d'école |
| [Guide Enseignant](docs/GUIDE_UTILISATEUR_ENSEIGNANT.md) | Manuel enseignant |
| [Guide Parent](docs/GUIDE_UTILISATEUR_PARENT.md) | Manuel application mobile parent |
| [Architecture](docs/ARCHITECTURE.md) | Architecture technique détaillée |
| [Sécurité](docs/SECURITE.md) | Documentation sécurité 6 niveaux |
| [Base de données](docs/BASE_DE_DONNEES.md) | Schéma et structure BDD |
| [API Guide](docs/API_GUIDE.md) | Guide API pour développeurs |
| [Conformité ANPDP](ANPDP_DECLARATION.md) | Loi 18-07 — Guide déclaration |
| [Réponse incidents](INCIDENT_RESPONSE_PLAN.md) | Procédure en cas d'incident |
| [Contribuer](CONTRIBUTING.md) | Guide de contribution |
| [Changelog](CHANGELOG.md) | Historique des versions |

---

## 🤝 Branches

- `main` → Production (protégée, PR obligatoire, CI doit être vert)
- `develop` → Développement actif

---

## 📞 Contact

- **Projet** : EduGest DZ — SaaS Éducatif Algérien
- **Conformité** : Loi 18-07 (ANPDP) · Données hébergées en Algérie

---

<div align="center">
<sub>Made with ❤️ in Oran, Algeria 🇩🇿</sub>
</div>
```

---

## ═══════════════════════════════════════════
## PARTIE B — README BACKEND MIS À JOUR
## ═══════════════════════════════════════════

## ÉTAPE 2 — Mettre à jour backend/README.md

**Remplacer entièrement** : `edugestdz/backend/README.md`

```markdown
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
```

---

## ═══════════════════════════════════════════
## PARTIE C — GUIDES UTILISATEUR
## ═══════════════════════════════════════════

## ÉTAPE 3 — Guide Utilisateur Directeur

**Créer** : `edugestdz/docs/GUIDE_UTILISATEUR_DIRECTEUR.md`

```markdown
# 📋 Guide Directeur d'École — EduGest DZ
## Manuel complet · Version 2026 · En français

---

## Premiers pas — Connexion

1. Ouvrir votre navigateur et aller sur l'URL de votre établissement
2. Entrer votre email et mot de passe
3. Si la double authentification (2FA) est activée → entrer le code à 6 chiffres de votre application (Google Authenticator ou similaire)
4. Vous êtes sur le tableau de bord

> ⚠️ **La 2FA est obligatoire pour les directeurs.** Si vous ne l'avez pas encore configurée,
> allez dans Paramètres → Sécurité → Activer la 2FA.

---

## Le tableau de bord

Le tableau de bord vous montre en un coup d'œil :
- **Élèves inscrits** ce trimestre
- **Présences du jour** (%) — mis à jour en temps réel
- **Factures impayées** — montant total + nombre
- **Alertes** — absences non justifiées, stock bas, entretiens à prévoir
- **Calendrier** — prochains examens, événements

---

## Gestion des élèves

### Inscrire un nouvel élève
1. Menu → **Élèves** → **Nouvel élève**
2. Remplir le formulaire : nom, prénom, date de naissance, niveau scolaire, wilaya
3. Ajouter les parents/tuteurs (nom + téléphone obligatoires pour les SMS)
4. Valider → le numéro de dossier est généré automatiquement

### Importer des élèves en masse (CSV)
1. Menu → **Élèves** → **Importer CSV**
2. Télécharger le modèle CSV (bouton "Modèle")
3. Remplir le fichier Excel → exporter en CSV
4. Importer → les erreurs sont listées ligne par ligne

---

## Planning et séances

### Créer un cours
1. Menu → **Planning** → **Nouveau cours**
2. Choisir : matière, niveau, enseignant, jour, heure, salle
3. Le système détecte automatiquement les conflits (enseignant déjà occupé, salle utilisée)
4. Valider → les séances sont générées pour toute la période

### Voir le planning de la semaine
- Menu → **Planning** → Vue semaine/jour/mois
- Cliquer sur une séance pour voir les détails ou la modifier
- Exporter en PDF pour affichage en salle des profs

---

## Finance

### Générer les factures mensuelles
Les factures sont générées **automatiquement** le 1er de chaque mois à 6h du matin.
Vous pouvez aussi les générer manuellement :
Menu → **Finance** → **Générer factures** → Choisir le mois → Confirmer

### Enregistrer un paiement
1. Menu → **Finance** → **Paiements** → **Nouveau paiement**
2. Chercher l'élève → sélectionner la facture
3. Choisir le mode : Espèces / CIB / Dahabia / Chèque / Virement
4. Entrer le montant et confirmer → le reçu PDF est disponible

### Tableau des impayés
Menu → **Finance** → **Impayés**
- Liste de toutes les factures en retard avec le nombre de jours
- Bouton **SMS relance** → envoie un SMS automatique aux parents

---

## Personnel et paie

### Calculer les paies du mois
Menu → **Personnel** → **Paie** → **Calculer paies** → Choisir le mois → Confirmer
- Le calcul applique automatiquement le barème IRG 2026 et les charges CNAS
- Les fiches de paie PDF sont générées pour chaque employé

---

## Communication avec les parents

### Envoyer un SMS en masse
1. Menu → **Communication** → **Nouveau SMS**
2. Choisir le groupe : tous les parents / une classe / des parents spécifiques
3. Écrire le message (max 160 caractères par SMS)
4. Envoyer → rapport de livraison disponible dans 5 minutes

### Alertes automatiques
Ces messages sont envoyés **automatiquement** :
- **Absence** : SMS aux parents à 8h30 si l'élève est absent ce matin
- **Facture impayée** : SMS J+1, J+3, J+7, J+15 après la date d'échéance
- **Bulletin disponible** : notification push sur l'application mobile parent

---

## Rapports et exports

### Bulletin de notes (PDF)
Menu → **Pédagogie** → **Bulletins** → Choisir le trimestre → **Générer tous les bulletins**
- Les bulletins sont générés en PDF avec le logo de l'école
- Téléchargement en ZIP pour impression

### Rapport d'assiduité
Menu → **Présences** → **Rapport mensuel** → Choisir le mois → **Exporter PDF**

### Rapport financier
Menu → **Finance** → **Rapports** → Choisir la période → **Export Excel** ou **PDF**

---

## Sécurité de votre compte

### Changer votre mot de passe
1. Cliquer sur votre nom en haut à droite → **Paramètres**
2. **Sécurité** → **Changer le mot de passe**
3. Le mot de passe doit contenir : 12 caractères minimum, une majuscule, un chiffre, un caractère spécial

### Appareils connectés
Menu → **Paramètres** → **Sécurité** → **Mes appareils**
Vous pouvez révoquer l'accès à n'importe quel appareil si vous suspectez une utilisation non autorisée.

---

## En cas de problème

### L'application ne répond pas
1. Attendre 30 secondes et rafraîchir (F5)
2. Vider le cache du navigateur (Ctrl+Shift+Suppr)
3. Si ça persiste → contacter votre administrateur EduGest DZ

### Mot de passe oublié
Sur la page de connexion → **Mot de passe oublié** → Entrer votre email → Suivre le lien reçu par email

### Contacter le support
Email : support@edugestdz.dz
Téléphone : disponible dans votre contrat
Horaires : Dimanche–Jeudi 8h–17h
```

---

## ÉTAPE 4 — Guide Utilisateur Enseignant

**Créer** : `edugestdz/docs/GUIDE_UTILISATEUR_ENSEIGNANT.md`

```markdown
# 👨‍🏫 Guide Enseignant — EduGest DZ
## Manuel complet · Version 2026

---

## Connexion et première utilisation

1. Vous avez reçu un email avec vos identifiants de connexion
2. Allez sur l'URL de votre établissement
3. Connectez-vous avec votre email + mot de passe
4. Changez votre mot de passe dès la première connexion (obligatoire)

---

## Mon planning

### Voir mes cours de la semaine
Menu → **Planning** → Onglet **Mes cours**
Vous voyez toutes vos séances avec : matière, groupe, salle, horaire.

### Signaler une absence (de ma part)
Si vous ne pouvez pas assurer un cours :
1. Menu → **Planning** → Cliquer sur la séance concernée
2. **Signaler absence** → Saisir le motif
3. L'administration est notifiée automatiquement

### Exporter mon planning en iCal
Menu → **Planning** → **Exporter iCal**
Vous pouvez importer ce fichier dans Google Calendar, Apple Calendar ou Outlook.

---

## Saisie des notes

### Saisir les notes d'une évaluation
1. Menu → **Notes** → **Nouvelle évaluation**
2. Choisir : groupe, matière, type (contrôle/devoir/examen), date, note maximale
3. Saisir les notes élève par élève (ou importer depuis Excel)
4. **Enregistrer** → les moyennes sont calculées automatiquement

### Voir les moyennes de mes élèves
Menu → **Notes** → **Tableau de bord**
Graphique des moyennes par groupe, identification des élèves en difficulté (rouge = alerte).

---

## Présences

### Faire l'appel
1. Menu → **Présences** → La liste des séances d'aujourd'hui s'affiche
2. Cliquer sur **Faire l'appel** pour une séance
3. Cocher présent/absent pour chaque élève
4. **Valider** → Les parents des absents reçoivent un SMS automatiquement

---

## Communication

### Envoyer un message à un parent
Menu → **Communication** → **Nouveau message**
Chercher l'élève → le parent apparaît → écrire le message → envoyer.

---

## Diagnostic élèves

Menu → **Diagnostic**
Voir les élèves de vos groupes qui sont en difficulté (selon notes + absences + comportement).
Vous pouvez proposer des plans de rattrapage directement depuis cette page.
```

---

## ÉTAPE 5 — Guide Utilisateur Parent (Application Mobile)

**Créer** : `edugestdz/docs/GUIDE_UTILISATEUR_PARENT.md`

```markdown
# 📱 Guide Parent — Application Mobile EduGest DZ
## Manuel complet · Version 2026

---

## Télécharger l'application

- **Android** : Google Play Store → rechercher "EduGest DZ"
- **iPhone** : App Store → rechercher "EduGest DZ"
- **Web** : Accessible aussi depuis votre navigateur mobile

## Première connexion

L'établissement de votre enfant vous a envoyé un SMS avec :
- Un lien de téléchargement
- Votre identifiant (email)
- Un mot de passe provisoire

Connectez-vous et changez votre mot de passe lors de la première utilisation.

---

## Ce que vous pouvez faire

### 📊 Suivi des notes
- Voir toutes les notes de votre enfant par matière et trimestre
- Consulter les bulletins PDF dès leur publication
- Recevoir une notification push à chaque nouvelle note

### 📅 Présences et absences
- Voir le calendrier des présences/absences
- Recevoir un SMS automatique dès que votre enfant est absent le matin
- Consulter les motifs d'absence (justifiée/non justifiée)

### 💰 Factures et paiements
- Consulter les factures en cours et l'historique des paiements
- Payer directement depuis l'application avec votre carte CIB ou Dahabia
- Télécharger les reçus de paiement en PDF

### 📱 Messages
- Recevoir et envoyer des messages à l'enseignant ou à l'administration
- Voir toutes les notifications de l'école

### 📅 Planning
- Consulter l'emploi du temps de votre enfant
- Voir les prochains examens et contrôles

---

## Paramètres des notifications

Vous pouvez choisir de recevoir des notifications pour :
- ✅ Nouvelles notes publiées
- ✅ Absences de votre enfant
- ✅ Nouvelles factures
- ✅ Messages de l'école
- ✅ Bulletin disponible

Menu → **Paramètres** → **Notifications** → Activer/désactiver ce que vous souhaitez.
```

---

## ═══════════════════════════════════════════
## PARTIE D — DOCUMENTATION TECHNIQUE
## ═══════════════════════════════════════════

## ÉTAPE 6 — Architecture technique

**Créer** : `edugestdz/docs/ARCHITECTURE.md`

```markdown
# 🏗️ Architecture Technique — EduGest DZ
## Document technique · Juillet 2026

---

## Vue d'ensemble

EduGest DZ suit une architecture **monolithique modulaire** (Laravel Modular Monolith).
Pas de microservices — un seul déploiement, plus simple à maintenir pour une startup.
La modularité est obtenue via les Services et le Module Manager (activation/désactivation par tenant).

```
[Client Web]──►[React 18 SPA]──►[API Laravel 11]──►[PostgreSQL 16]
[Client Mobile]►[React Native]──►     ↑                   ↑
                                  [Redis 7]         [Meilisearch]
```

---

## Multi-tenancy

Chaque établissement est un **tenant** isolé. L'isolation est triple :

### Niveau 1 — Applicatif (BelongsToTenant trait)
```php
// Chaque modèle utilise ce trait
// Ajoute automatiquement WHERE tenant_id = ? sur toutes les requêtes
use BelongsToTenant;
```

### Niveau 2 — Base de données (PostgreSQL RLS)
```sql
-- Politique appliquée sur 40+ tables
CREATE POLICY tenant_isolation_policy ON eleves
USING (tenant_id::text = current_setting('app.current_tenant_id', true));
```

### Niveau 3 — Middleware (TenantIsolationVerifier)
- Vérifie que le header `X-Tenant-ID` correspond au tenant du token JWT
- Bloque les tentatives de manipulation cross-tenant

---

## Flux d'une requête API

```
Requête HTTP
    ↓
[1] KillSwitchMiddleware       → Vérifie si le kill switch est actif (503 si oui)
    ↓
[2] HoneypotRouteMiddleware    → Route leurre ? → 404 + IP blacklistée
    ↓
[3] SqlInjectionDetector       → Pattern SQL dangereux ? → 400 + IP bannie
    ↓
[4] JwtBlacklistCheck          → Token révoqué ? → 401
    ↓
[5] ZeroTrustMiddleware        → Risk Score → [0-50: OK] [51-75: log] [76+: block]
    ↓
[6] auth:api                   → JWT valide ? → 401 sinon
    ↓
[7] ResolveTenant              → Résoudre tenant + SET LOCAL PostgreSQL
    ↓
[8] TenantIsolationVerifier    → X-Tenant-ID cohérent ? → 403 si manipulation
    ↓
[9] MfaRequired                → Admin sans 2FA ? → 403
    ↓
[10] IntelligentRateLimiter    → Rate limit adaptatif
    ↓
Controller → Service → Model → PostgreSQL (+ RLS au niveau BDD)
    ↓
AuditChainService              → Enregistrement immuable Merkle
    ↓
Réponse JSON standardisée
```

---

## Chaîne de sécurité — Détail

### Risk Score Engine
Chaque requête reçoit un score de risque 0-100 :

| Facteur | Points |
|---------|--------|
| IP jamais vue pour cet utilisateur | +40 |
| Pays inhabituel (non-Algérie) | +30 |
| Appareil non reconnu | +25 |
| Heure anormale (2h-5h) | +20 |
| >50 requêtes en 5 minutes | +20 |
| >3 erreurs 403 en 10 minutes | +15 |
| >3 logins échoués | +15 |
| User-Agent botlike (curl, python...) | +10 |
| Volume de données suspect | +10 |

Actions : 0-50 → OK · 51-75 → Loggé · 76-90 → Bloqué · 91-100 → Compte verrouillé 30min

### Audit Chain (Merkle Tree)
Chaque opération sensible (CREATE/UPDATE/DELETE) est enregistrée dans une chaîne :
```
Bloc N : {contenu} + hash(Bloc N-1) → hash_merkle(N) → HMAC-SHA3-256
```
Toute modification d'un log invalide mathématiquement tous les blocs suivants.

---

## Schéma de la base de données

→ [Voir docs/BASE_DE_DONNEES.md](BASE_DE_DONNEES.md)

---

## Schedulers (tâches planifiées)

| Fréquence | Tâche |
|-----------|-------|
| Toutes les 5 min | SIEM corrélation événements |
| Quotidien 1h | Vérification intégrité Audit Chain |
| Quotidien 2h | Export audit logs signé HMAC |
| Quotidien 7h | Alertes stock bas + entretien préventif |
| Lun-Ven 8h30 | SMS absences parents |
| Quotidien 9h | Relances factures impayées + Dead Man Switch |
| 1er du mois 6h | Génération factures transport + cantine |
| Dimanche 3h | Nettoyage JWT blacklist expirée |
| Lundi 4h | Vérification supply chain (intégrité dépendances) |
| Lundi 5h | Génération séances semaine |
```

---

## ÉTAPE 7 — Guide API développeurs

**Créer** : `edugestdz/docs/API_GUIDE.md`

```markdown
# 🔌 Guide API — EduGest DZ
## Pour les développeurs frontend, mobile et intégrateurs

---

## Base URL

```
Production  : https://api.votre-ecole.dz/api/v1
Développement: http://localhost:8000/api/v1
Documentation: http://localhost:8000/api/documentation (Swagger UI)
```

---

## Authentification

### 1. Se connecter (obtenir un token)
```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "admin@ecole-oran.dz",
  "password": "VotreMotDePasse"
}
```

Réponse :
```json
{
  "success": true,
  "data": {
    "token": "eyJ0eXAiOiJKV1Qi...",
    "token_type": "bearer",
    "expires_in": 3600,
    "user": { "id": "uuid", "nom": "Benali", "role": "admin" }
  }
}
```

### 2. Utiliser le token
Ajouter sur chaque requête :
```http
Authorization: Bearer eyJ0eXAiOiJKV1Qi...
X-Tenant-ID: uuid-du-tenant
```

### 3. Si 2FA activée
Après le login, vous recevrez :
```json
{ "two_factor_required": true, "temp_token": "xxx" }
```

Vérifier le code TOTP :
```http
POST /api/v1/auth/2fa/verify
{ "temp_token": "xxx", "code": "123456" }
```

### 4. Déconnexion (révoque le token)
```http
POST /api/v1/auth/logout
Authorization: Bearer ...
```

---

## Codes de réponse HTTP

| Code | Signification |
|------|--------------|
| 200 | Succès |
| 201 | Créé avec succès |
| 400 | Requête invalide (validation) |
| 401 | Non authentifié (token manquant ou expiré) |
| 403 | Accès refusé (rôle insuffisant, MFA manquante, tenant erroné) |
| 404 | Ressource non trouvée |
| 410 | Lien expiré (fichiers signés) |
| 422 | Erreur de validation des données |
| 428 | Challenge Zero-Trust requis |
| 429 | Trop de requêtes (rate limit atteint) |
| 503 | Service temporairement indisponible (kill switch actif) |

---

## Codes d'erreur métier

| Code | Signification | Action |
|------|--------------|--------|
| `INVALID_CREDENTIALS` | Email ou mot de passe incorrect | Vérifier les identifiants |
| `ACCOUNT_LOCKED` | Compte verrouillé (trop de tentatives) | Attendre 30 min ou contacter admin |
| `BRUTE_FORCE_BLOCKED` | IP bloquée pour brute force | Attendre 15 min |
| `MFA_REQUIRED` | 2FA non activée (admin) | Activer 2FA dans Paramètres |
| `TOKEN_REVOKED` | Token révoqué (logout autre session) | Se reconnecter |
| `TENANT_MANIPULATION` | Tentative d'accès à un autre tenant | Vérifier X-Tenant-ID |
| `ZERO_TRUST_BLOCKED` | Requête bloquée par risk score | Contacter admin |
| `GLOBAL_LOCKDOWN` | Verrouillage d'urgence actif | Contacter super-admin |

---

## Exemples d'endpoints principaux

### Élèves
```http
GET    /api/v1/eleves              # Liste paginée
GET    /api/v1/eleves/{id}         # Détail
POST   /api/v1/eleves              # Créer
PUT    /api/v1/eleves/{id}         # Modifier
DELETE /api/v1/eleves/{id}         # Supprimer (soft delete)
GET    /api/v1/eleves?search=Benali # Recherche Meilisearch
```

### Notes et bulletins
```http
GET    /api/v1/notes               # Toutes les notes du tenant
POST   /api/v1/evaluations         # Nouvelle évaluation
POST   /api/v1/bulletins/generer   # Générer les bulletins PDF
GET    /api/v1/bulletins/{id}/pdf  # Télécharger un bulletin PDF
```

### Finance
```http
GET    /api/v1/factures            # Toutes les factures
POST   /api/v1/paiements           # Enregistrer un paiement
GET    /api/v1/finance/caisse-jour # Récapitulatif journalier
POST   /api/v1/paiement-en-ligne/initier # Initier paiement Satim
```

### Sécurité (super-admin uniquement)
```http
GET    /api/v1/security/dashboard          # Dashboard sécurité
POST   /api/v1/security/kill-switch/voter  # Vote kill switch
GET    /api/v1/security/siem/rapport       # Rapport SIEM 24h
GET    /api/v1/security/breach/incidents   # Incidents déclarés
```

---

## Pagination

```http
GET /api/v1/eleves?page=2&per_page=25
```

Réponse meta :
```json
{
  "meta": {
    "current_page": 2,
    "per_page": 25,
    "total": 348,
    "last_page": 14
  }
}
```

---

## Rate Limiting

Les headers de rate limit sont inclus dans chaque réponse :
```
X-RateLimit-Limit: 500
X-RateLimit-Remaining: 487
```

Limites par rôle (par minute) :
- `super_admin` : 1000 req/min
- `admin` : 500 req/min
- `enseignant` : 300 req/min
- `parent` : 200 req/min

---

## Documentation interactive (Swagger)

Après lancement du serveur :
```bash
php artisan l5-swagger:generate
```
Accéder à : `http://localhost:8000/api/documentation`

Tous les endpoints sont documentés avec exemples de requête et réponse.
```

---

## ÉTAPE 8 — Documentation Sécurité

**Créer** : `edugestdz/docs/SECURITE.md`

```markdown
# 🛡️ Documentation Sécurité — EduGest DZ
## Pour les auditeurs, partenaires et équipes techniques

---

## Résumé de conformité

| Exigence | Statut | Détail |
|----------|--------|--------|
| Loi 18-07 (ANPDP) | ✅ Préparé | Déclaration à déposer avant données réelles |
| Chiffrement données sensibles | ✅ AES-256-CBC | Colonnes tokens, clés API |
| Authentification forte | ✅ JWT + 2FA TOTP | Obligatoire pour admins |
| Isolation données tenants | ✅ Triple couche | Applicatif + RLS PostgreSQL + Middleware |
| Audit logs immuables | ✅ Merkle SHA3-256 | Falsification détectable mathématiquement |
| Politique mots de passe | ✅ 12 chars min + complexité | Blacklist 40+ mots de passe courants |
| Réponse aux incidents | ✅ Documentée | INCIDENT_RESPONSE_PLAN.md |
| Headers sécurité HTTP | ✅ OWASP complets | CSP, HSTS, X-Frame-Options, etc. |

---

## Les 6 niveaux de sécurité

### Niveau 1 — Fondations
- **JWT Blacklist** : tokens révoqués immédiatement à la déconnexion (Redis + BDD)
- **PostgreSQL RLS** : Row-Level Security sur 40+ tables (filet BDD)
- **Isolation tenant** : `BelongsToTenant` + `TenantIsolationVerifier`
- **Fichiers sécurisés** : URLs signées HMAC expirantes (jamais d'URL permanente publique)

### Niveau 2 — Protection des données
- **Chiffrement colonnes** : `EncryptedString` cast sur tokens Satim, Google OAuth, Firebase
- **MFA obligatoire** : 2FA TOTP requis pour admin et super_admin
- **Brute force** : blocage après 10 tentatives, période 15 min
- **Headers OWASP** : CSP, HSTS, X-Content-Type-Options, Referrer-Policy

### Niveau 3 — Conformité
- **Audit logs signés** : SHA-256 + HMAC exportés quotidiennement
- **Politique MDP** : 12 chars, majuscule, chiffre, spécial, blacklist 40+ mots interdits
- **IP Allowlist** : Super-admin restreint aux IPs connues
- **JWT rotation** : renouvellement programmable avec période de grâce 24h
- **Breach API** : déclaration d'incident avec rappel délai 72h ANPDP

### Niveau 4 — Zero-Trust
- **Risk Score 0-100** : 9 facteurs évalués par requête, fail-secure (exception = 100)
- **Device Fingerprinting** : appareils enregistrés + challenge OTP pour nouveaux appareils
- **RBAC granulaire** : permissions au niveau du champ (ex: enseignant voit notes mais pas salaires)
- **Rate Limiter adaptatif** : quotas différents par rôle/heure/type de route

### Niveau 5 — Détection active
- **Honeypots** : 16 routes leurres + champs pièges formulaires → IP blacklistée 24h
- **Canary Tokens** : tokens fictifs en BDD → si utilisés = preuve de dump BDD
- **SSRF Protection** : bloque les requêtes vers métadonnées cloud et réseau interne
- **SQL Injection Layer** : 18 patterns détectés avant d'atteindre Eloquent
- **Vault Secrets** : secrets hors .env (HashiCorp Vault ou BDD chiffrée en fallback)
- **Insider Threat** : détection volume anormal de téléchargement
- **Dead Man Switch** : alerte si aucun admin ne se connecte en 7 jours

### Niveau 6 — Forteresse
- **Audit Chain Merkle** : SHA3-256 + DB::transaction + chunk(1000) — falsification impossible
- **SIEM** : 5 règles de corrélation (credential stuffing, impossible travel, SQLi coordonnée...)
- **Post-Quantum** : Ed25519 (sodium) avec fallback RSA-4096
- **Kill Switch MPC** : 2 super-admins requis + fenêtre 600s pour activer
- **Supply Chain** : vérification hash composer.lock chaque semaine

---

## Contacts sécurité

Pour signaler une vulnérabilité ou un incident :
- Email sécurité : [à configurer par l'établissement]
- Procédure complète : [INCIDENT_RESPONSE_PLAN.md](../INCIDENT_RESPONSE_PLAN.md)
- ANPDP : www.anpdp.dz (délai légal 72h pour notification breach)
```

---

## ÉTAPE 9 — CONTRIBUTING.md

**Créer** : `CONTRIBUTING.md` (à la racine du repo)

```markdown
# 🤝 Guide de contribution — EduGest DZ

Merci de contribuer à EduGest DZ !

---

## Processus de contribution

1. **Fork** le repo
2. Créer une branche depuis `develop` : `git checkout -b feat/ma-fonctionnalite`
3. Coder + tests (obligatoires)
4. Vérifier : `php artisan test --parallel` → 0 failure
5. Commit : `git commit -m "feat(module): description claire"`
6. Push : `git push origin feat/ma-fonctionnalite`
7. Ouvrir une **Pull Request** vers `develop`

---

## Convention des commits (obligatoire)

Format : `type(scope): description`

| Type | Usage |
|------|-------|
| `feat` | Nouvelle fonctionnalité |
| `fix` | Correction de bug |
| `security` | Amélioration sécurité |
| `perf` | Optimisation performance |
| `test` | Ajout/correction de tests |
| `docs` | Documentation uniquement |
| `refactor` | Refactoring sans nouvelle feature |
| `ci` | CI/CD, workflows |

Exemples :
```
feat(eleves): ajout import CSV avec validation
fix(ci): corriger compat PostgreSQL dans les tests
security(niveau2): MFA obligatoire pour les admins
```

---

## Règles obligatoires

### Code
- **0 régression** : les 607 tests existants doivent rester verts
- **PostgreSQL uniquement** — jamais de code compat SQLite dans les migrations
- Toute nouvelle fonctionnalité = au moins 1 test
- Tout nouveau endpoint = annotation @OA Swagger

### Sécurité
- Ne jamais hardcoder de secrets dans le code
- Toujours utiliser les paramètres liés (bindings) Eloquent — jamais de raw SQL avec interpolation
- Toujours vérifier le tenant_id dans les contrôleurs (le trait BelongsToTenant le fait automatiquement)

### Base de données
- Toujours créer une nouvelle migration — ne jamais modifier une migration existante
- Utiliser `Schema::hasColumn()` dans les migrations additives
- Nommage : `YYYY_MM_DD_HHMMSS_description.php`

---

## Branches

- `main` : Production — protégée, PR obligatoire, CI doit être vert
- `develop` : Développement actif — toutes les PRs vont ici
- `feat/*` : Nouvelles fonctionnalités
- `fix/*` : Corrections de bugs
- `security/*` : Améliorations sécurité

---

## Environnement de développement

```bash
git clone https://github.com/Allintelligence2024/edugest-dz.git
cd edugestdz
cp backend/.env.example backend/.env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan jwt:secret
docker compose exec app php artisan migrate --seed
```
```

---

## ÉTAPE 10 — CHANGELOG.md

**Créer** : `CHANGELOG.md` (à la racine du repo)

```markdown
# 📋 Changelog — EduGest DZ

Toutes les versions notables sont documentées ici.
Format basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).

---

## [1.0.0-beta] — 8 Juillet 2026

### Ajouté
- **Sécurité Niveau 6** : Audit Chain Merkle SHA3-256, SIEM 5 règles, Kill Switch MPC, Post-Quantum Crypto (Ed25519/RSA-4096), Supply Chain Verifier — 19 tests
- **Sécurité Niveau 5** : Honeypots actifs (16 routes leurres), Canary Tokens, SSRF Protection, SQL Injection Layer, HashiCorp Vault (fallback BDD chiffrée), Insider Threat Detector, Dead Man Switch — 24 tests
- **Sécurité Niveau 4** : Zero-Trust Risk Score Engine, Device Fingerprinting, RBAC granulaire par champ, Intelligent Rate Limiter — 18 tests
- **Sécurité Niveau 3** : Audit logs HMAC-SHA256, Password Policy (12 chars + blacklist), IP Allowlist super-admin, JWT rotation, Breach Response API — 9 tests
- **Sécurité Niveau 2** : Chiffrement colonnes AES-256, MFA obligatoire admins, Brute force protection, Headers OWASP complets — 11 tests
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
```

---

## ═══════════════════════════════════════════
## PARTIE E — COMMENTAIRES PHPDoc CODE
## ═══════════════════════════════════════════

## ÉTAPE 11 — Ajouter PHPDoc sur les Services sans commentaires

Pour chaque service listé, ajouter les commentaires PHPDoc sur les méthodes publiques.
Priorité : les services les plus utilisés.

**Modifier** : `edugestdz/backend/app/Services/FacturationService.php`
Ajouter avant chaque méthode public :
```php
/**
 * Générer les factures mensuelles pour tous les élèves actifs.
 *
 * @param int $mois  Mois de facturation (1-12)
 * @param int $annee Année de facturation
 * @return array ['generees' => int, 'erreurs' => array]
 */
public function genererFacturesMensuelles(int $mois, int $annee): array
```

**Modifier** : `edugestdz/backend/app/Services/BulletinService.php`
```php
/**
 * Générer le bulletin PDF d'un élève pour un trimestre.
 *
 * @param string $eleveId   UUID de l'élève
 * @param int    $trimestre Numéro du trimestre (1, 2 ou 3)
 * @param int    $annee     Année scolaire (ex: 2026)
 * @return string Chemin du fichier PDF généré
 * @throws \RuntimeException Si l'élève n'a aucune note ce trimestre
 */
public function genererBulletinPdf(string $eleveId, int $trimestre, int $annee): string
```

**Modifier** : `edugestdz/backend/app/Services/DiagnosticService.php`
```php
/**
 * Calculer le score de risque d'un élève (Early Warning System).
 *
 * Score : 0 (aucun risque) → 100 (risque critique)
 * Facteurs : notes sous 10, absences > 20%, signalements comportement
 *
 * @param string $eleveId UUID de l'élève
 * @return array ['score' => int, 'niveau' => string, 'facteurs' => array]
 */
public function calculerScore(string $eleveId): array
```

---

## ═══════════════════════════════════════════
## PARTIE F — .env.example COMMENTÉ
## ═══════════════════════════════════════════

## ÉTAPE 12 — Commenter le .env.example ligne par ligne

**Modifier** : `edugestdz/backend/.env.example`

Ajouter des commentaires explicatifs sur chaque section :

```dotenv
# ══════════════════════════════════════════════════════════
# EDUGEST DZ — Configuration complète
# Copier ce fichier en .env et remplir les valeurs
# JAMAIS committer le .env réel dans Git
# ══════════════════════════════════════════════════════════

# ── Application ───────────────────────────────────────────
APP_NAME="EduGest DZ"
APP_ENV=local          # local | staging | production
APP_KEY=               # Générer avec: php artisan key:generate
APP_DEBUG=true         # false en production OBLIGATOIRE
APP_URL=http://localhost

# ── Base de données PostgreSQL ────────────────────────────
# NE PAS utiliser le user 'postgres' en production
# Créer un user dédié: CREATE USER edugest_user WITH PASSWORD '...'
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=edugestdz
DB_USERNAME=edugest_user
DB_PASSWORD=           # Minimum 16 caractères, aléatoire

# ── Redis ──────────────────────────────────────────────────
# Utilisé pour: cache, sessions, JWT blacklist, rate limiting
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=        # Obligatoire en production
REDIS_PORT=6379

# ── JWT Authentication ────────────────────────────────────
# Générer avec: php artisan jwt:secret
# Rotation: php artisan edugest:jwt-rotate
JWT_SECRET=            # 64+ caractères aléatoires
JWT_TTL=60             # Durée de vie du token en minutes

# ── Meilisearch ───────────────────────────────────────────
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=       # Master key Meilisearch

# ── Email ─────────────────────────────────────────────────
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io    # Changer en production
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@votre-ecole.dz
MAIL_FROM_NAME="${APP_NAME}"

# ── SMS (Twilio) ──────────────────────────────────────────
# Créer un compte sur twilio.com → SMS pour Algérie (+213)
TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=           # Numéro Twilio format international (+1...)

# ── Firebase (Notifications push mobile) ─────────────────
# Télécharger le JSON depuis Firebase Console → Paramètres projet
FIREBASE_PROJECT_ID=
FIREBASE_PRIVATE_KEY=
FIREBASE_CLIENT_EMAIL=

# ── Satim (Paiement CIB/Dahabia) ─────────────────────────
# Contacter votre banque partenaire Satim pour les credentials
# TEST : utiliser https://test.satim.dz
# PROD : utiliser https://satim.dz (après signature contrat)
SATIM_BASE_URL=https://test.satim.dz/payment/rest
SATIM_USERNAME=        # Fourni par Satim
SATIM_PASSWORD=        # Fourni par Satim
SATIM_TERMINAL=        # Fourni par Satim

# ── WhatsApp Business API (Meta) ─────────────────────────
# Créer une application sur developers.facebook.com
WHATSAPP_TOKEN=
WHATSAPP_PHONE_ID=
WHATSAPP_WEBHOOK_SECRET=

# ── Google Classroom OAuth2 ───────────────────────────────
# Créer des credentials OAuth2 sur console.cloud.google.com
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/api/v1/classroom/callback"

# ── Alertes Sécurité (Telegram) ──────────────────────────
# Créer un bot: https://t.me/BotFather → /newbot
# Obtenir votre chat_id: https://t.me/userinfobot
TELEGRAM_BOT_TOKEN=
TELEGRAM_SECURITY_CHAT_ID=

# ── HashiCorp Vault (Optionnel) ───────────────────────────
# Si vide → fallback sur BDD chiffrée (moins sécurisé mais fonctionnel)
VAULT_ADDR=            # Ex: https://vault.votre-ecole.dz:8200
VAULT_TOKEN=

# ── Sécurité ──────────────────────────────────────────────
# IPs autorisées pour le Super-Admin (vide = pas de restriction)
# Format: IP1,IP2,CIDR ou vide pour dev
SUPER_ADMIN_ALLOWED_IPS=

# Email pour alertes critiques de sécurité
SECURITY_ALERT_EMAIL=

# ── Surveillance Dahua ────────────────────────────────────
DAHUA_WEBHOOK_SECRET=  # Secret pour vérifier les webhooks Dahua

# ── Configuration Algérie ─────────────────────────────────
APP_TIMEZONE=Africa/Algiers    # NE PAS CHANGER
APP_LOCALE=fr
```

---

## ═══════════════════════════════════════════
## PARTIE G — BASE DE DONNÉES DOCUMENTÉE
## ═══════════════════════════════════════════

## ÉTAPE 13 — Schéma base de données

**Créer** : `edugestdz/docs/BASE_DE_DONNEES.md`

```markdown
# 🗄️ Base de données — EduGest DZ
## Schéma PostgreSQL 16 · 60+ tables

---

## Conventions

- Toutes les clés primaires sont des **UUID** (non-devinables, non-séquentiels)
- Toutes les tables multi-tenant ont une colonne `tenant_id UUID` avec **RLS PostgreSQL**
- `soft_deletes` sur les modèles critiques (élèves, enseignants, factures)
- `timestamps()` sur toutes les tables (created_at, updated_at)
- Typage strict : VARCHAR avec longueur, pas de TEXT pour les petits champs

---

## Groupes de tables

### 🏢 Tenants & Utilisateurs
| Table | Description |
|-------|-------------|
| `tenants` | Établissements clients (1 ligne = 1 école) |
| `users` | Comptes utilisateurs (admin, enseignant, parent, élève) |
| `roles` | Rôles (admin, enseignant, parent, eleve, super_admin) |
| `permissions` | Permissions granulaires |
| `role_permissions` | Pivot rôle ↔ permission |
| `field_permissions` | Permissions au niveau du champ par tenant |

### 👨‍🎓 Élèves & Parents
| Table | Description |
|-------|-------------|
| `eleves` | Dossiers élèves |
| `parents_eleves` | Parents/tuteurs |
| `parent_eleve` | Pivot élève ↔ parent |
| `inscriptions` | Inscriptions annuelles |

### 📅 Planning & Pédagogie
| Table | Description |
|-------|-------------|
| `groupes` | Classes / groupes |
| `cours` | Définitions des cours (matière, enseignant, horaire) |
| `seances` | Occurrences des cours |
| `presences` | Pointage par séance |
| `absences_journalieres` | Absences journalières |
| `evaluations` | Évaluations (contrôle, devoir, examen) |
| `notes` | Notes individuelles |
| `bulletins` | Bulletins PDF générés |

### 💰 Finance
| Table | Description |
|-------|-------------|
| `factures` | Factures mensuelles |
| `lignes_facture` | Lignes de facture (détail) |
| `paiements` | Paiements enregistrés |
| `depenses` | Dépenses de l'établissement |
| `budgets_previsionnels` | Budget prévisionnel |

### 👨‍🏫 Personnel
| Table | Description |
|-------|-------------|
| `enseignants` | Dossiers enseignants |
| `contrats` | Contrats enseignants |
| `personnel_non_enseignant` | Administratif, gardien, etc. |
| `paies` | Fiches de paie mensuelles |
| `billets` | Billets entrée/retard/sortie/convocation |

### 🚌 Services
| Table | Description |
|-------|-------------|
| `circuits_transport` | Circuits de bus |
| `transport_eleves` | Inscription élève au transport |
| `pointage_bus` | Pointage montée/descente |
| `menus_cantine` | Menus hebdomadaires |
| `inscriptions_cantine` | Abonnements cantine |
| `repas_journaliers` | Pointage repas par élève |
| `articles_stock` | Articles en stock |
| `mouvements_stock` | Entrées/sorties stock |
| `locaux` | Salles, bâtiments |
| `interventions_entretien` | Interventions correctives |
| `entretiens_preventifs` | Maintenance planifiée |

### 🔐 Sécurité
| Table | Description |
|-------|-------------|
| `jwt_blacklist` | Tokens JWT révoqués |
| `trusted_devices` | Appareils de confiance (device fingerprint) |
| `device_challenges` | Codes OTP approbation appareil |
| `security_events` | Journal des événements de sécurité |
| `request_risk_scores` | Historique des scores de risque |
| `honeypot_triggers` | Déclenchements honeypot |
| `canary_tokens` | Tokens de détection de fuite BDD |
| `encrypted_secrets` | Secrets chiffrés (fallback Vault) |
| `audit_log_exports` | Exports d'audit signés HMAC |
| `audit_chain` | Chaîne de blocs Merkle immuable |
| `breach_declarations` | Déclarations d'incidents de sécurité |

### 🔗 Intégrations
| Table | Description |
|-------|-------------|
| `whatsapp_messages` | Messages WhatsApp envoyés/reçus |
| `google_classroom_connexions` | Tokens OAuth2 Google Classroom |
| `device_tokens` | Tokens push Firebase |
| `cameras_config` | Configuration caméras Dahua |
| `alertes_surveillance` | Alertes des caméras |

### 🖥️ LMS & Examens
| Table | Description |
|-------|-------------|
| `lms_cours` | Cours en ligne |
| `lms_chapitres` | Chapitres |
| `lms_lecons` | Leçons (video/PDF/quiz) |
| `lms_inscriptions` | Inscriptions étudiants |
| `lms_progression` | Progression par leçon |
| `examens_officiels` | Examens BEM/BAC |
| `salles_examen` | Salles d'examen |
| `convocations_examen` | Convocations candidats |

### 📚 Bibliothèque & Marketplace
| Table | Description |
|-------|-------------|
| `livres` | Catalogue bibliothèque |
| `prets_livres` | Prêts en cours |
| `profils_marketplace` | Profils centres sur la marketplace |
| `offres_cours` | Offres de cours |
| `reservations_marketplace` | Réservations |

---

## Index importants

Pour les performances, ces index sont créés :

```sql
-- Recherche fréquente par tenant + statut
CREATE INDEX idx_eleves_tenant_statut ON eleves(tenant_id, statut);
CREATE INDEX idx_factures_tenant_statut ON factures(tenant_id, statut);

-- Audit chain — ordre de vérification
CREATE INDEX idx_chain_tenant ON audit_chain(tenant_id, cree_le);

-- Sécurité — lookup rapide
CREATE INDEX idx_blacklist_jti ON jwt_blacklist(jti);
CREATE INDEX idx_trusted_devices_hash ON trusted_devices(device_hash);
CREATE INDEX idx_honeypot_ip ON honeypot_triggers(ip_hash, survenu_le);
```
```

---

## ÉTAPE 14 — Exécution finale

```bash
cd edugestdz/backend

# Vérifier que les tests sont toujours verts (la doc ne doit pas casser le code)
php artisan test --parallel
# → 607 tests ✅  0 failures

# Générer la documentation Swagger (mise à jour automatique)
php artisan l5-swagger:generate

git add .
git commit -m "docs: documentation complète 100% — README principal, guides utilisateurs (directeur/enseignant/parent), architecture technique, guide API, sécurité, BDD, CONTRIBUTING, CHANGELOG, .env.example commenté, PHPDoc services"

git push origin develop
# → PR develop → main (pas de CI check car uniquement des .md)
```

---

## RÉCAPITULATIF — Ce que cette mission produit

| Fichier | Type | Statut |
|---------|------|--------|
| `README.md` (racine) | Vitrine projet | ✅ Remplacé complet |
| `edugestdz/backend/README.md` | Doc technique backend | ✅ Remplacé complet |
| `edugestdz/docs/GUIDE_UTILISATEUR_DIRECTEUR.md` | Guide utilisateur | ✅ Créé |
| `edugestdz/docs/GUIDE_UTILISATEUR_ENSEIGNANT.md` | Guide utilisateur | ✅ Créé |
| `edugestdz/docs/GUIDE_UTILISATEUR_PARENT.md` | Guide utilisateur | ✅ Créé |
| `edugestdz/docs/ARCHITECTURE.md` | Doc technique | ✅ Créé |
| `edugestdz/docs/API_GUIDE.md` | Doc développeurs | ✅ Créé |
| `edugestdz/docs/SECURITE.md` | Doc conformité | ✅ Créé |
| `edugestdz/docs/BASE_DE_DONNEES.md` | Schéma BDD | ✅ Créé |
| `CONTRIBUTING.md` | Guide contribution | ✅ Créé |
| `CHANGELOG.md` | Historique versions | ✅ Créé |
| `edugestdz/backend/.env.example` | Configuration | ✅ Commenté ligne par ligne |
| PHPDoc sur 3 services principaux | Commentaires code | ✅ Ajoutés |

**Score documentation avant : 65/100 → après : 100/100**

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_DOCUMENTATION_100_POURCENT.md — 14 étapes dans l'ordre.

RÈGLES CRITIQUES :
1. NE PAS modifier le code PHP, JS ou les migrations — uniquement les fichiers .md et les commentaires PHPDoc
2. Respecter les chemins exacts indiqués pour chaque fichier
3. Les fichiers README remplacent ENTIÈREMENT les anciens (ne pas fusionner, remplacer)
4. Le .env.example : ajouter les commentaires SANS changer les valeurs existantes
5. PHPDoc : ajouter UNIQUEMENT sur les méthodes public qui n'en ont pas encore
6. Vérifier après chaque étape : php artisan test --parallel → 607 ✅ 0 failures
7. Tout le texte en Français (sauf les termes techniques en Anglais qui sont standards)
8. Les guides utilisateurs (directeur/enseignant/parent) doivent être en langage simple
   → pas de jargon technique pour ces fichiers
9. Le CHANGELOG.md : utiliser les vrais commits du repo (déjà lus et résumés dans ce fichier)
10. Commencer par l'étape 0 (git pull) avant toute modification

git add .
git commit -m "docs: documentation complète 100% — 14 fichiers créés/mis à jour"
git push origin develop → PR develop → main
```
