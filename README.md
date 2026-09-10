<div align="center">

# 🎓 EduGest DZ

**Plateforme SaaS de gestion des établissements éducatifs — Made in Algeria 🇩🇿**

*Écoles privées · Centres de cours particuliers · Lycées · Collèges*

[![CI](https://github.com/Allintelligence2024/edugest-dz/actions/workflows/ci.yml/badge.svg)](https://github.com/Allintelligence2024/edugest-dz/actions)
[![Tests](https://img.shields.io/badge/tests-1%20270%2B%20✅-brightgreen)](https://github.com/Allintelligence2024/edugest-dz/actions)
[![PHP](https://img.shields.io/badge/PHP-8.2-blue)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11-red)](https://laravel.com)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-blue)](https://postgresql.org)
[![License](https://img.shields.io/badge/Licence-Propriétaire-orange)](LICENSE)
[![Sécurité](https://img.shields.io/badge/Sécurité-6%20niveaux-darkred)](docs/SECURITE.md)
[![ANPDP](https://img.shields.io/badge/Loi%2018--07-Outils%20livrés-yellowgreen)](docs/ANPDP_DECLARATION.md)

</div>

---

## 🚀 Qu'est-ce qu'EduGest DZ ?

EduGest DZ est une plateforme SaaS algérienne de gestion complète des établissements
éducatifs. Conçue spécifiquement pour les réalités du système éducatif algérien :

- ✅ Barèmes **IRG/CNAS** 2026 intégrés
- ✅ Curriculum **ONEC/BEM/BAC** officiel
- ✅ Paiement **CIB et Dahabia** (Satim)
- ✅ **58 wilayas** algériennes seedées (y compris les 10 wilayas du Sud créées en 2019)
- ✅ Outils **Loi 18-07** : registre des traitements, consentements parentaux tracés, rétention automatique (la déclaration ANPDP relève de chaque établissement)
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
│  + Vite     │◄──►│  79 contrôleurs     │◄──►│  Expo 52         │
│  Vercel     │    │  75 services        │    │  25 écrans       │
└─────────────┘    └──────────┬──────────┘    └──────────────────┘
                              │
              ┌───────────────┼───────────────┐
              ▼               ▼               ▼
      ┌──────────────┐ ┌──────────┐ ┌───────────────────┐
      │ PostgreSQL16 │ │  Redis 7 │ │ Meilisearch v1.8  │
      │ RLS + isolation│ │  Cache  │ │ Recherche temps   │
      │ Audit Merkle │ │  JWT     │ │ réel              │
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

EduGest DZ implémente une sécurité en 6 niveaux, documentée avec ses
restes ouverts (révélés par le pentest Sprint 6) :

| Niveau | Protection |
|--------|------------|
| 1 | Révocation JWT à la déconnexion + PostgreSQL RLS + isolation tenant triple |
| 2 | Chiffrement colonnes sensibles (AES-256) + 2FA TOTP (obligatoire super-admin) |
| 3 | Audit logs HMAC-SHA-256 + Password Policy + IP Allowlist |
| 4 | Risk Score 0-100 + Device Fingerprinting (calculé, journalisé, signalé Sentry — enforcement strict en reste ouvert) |
| 5 | Honeypots + Canary Tokens + SSRF Protection + Vault Secrets |
| 6 | Chaîne d'audit Merkle HMAC-SHA-256 vérifiée chaque nuit + SIEM + Kill Switch MPC (2 admins) |

→ [Documentation sécurité complète](docs/SECURITE.md) · [Pentest Sprint 6 (7 constats, 3 corrigés)](docs/SPRINT6_EXPLOITATION.md)

---

## ⚡ Installation rapide (5 minutes)

### Prérequis
- Docker Desktop
- Git

### Démarrer

```bash
# 1. Cloner
git clone https://github.com/Allintelligence2024/edugest-dz.git
cd edugest-dz

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
# → pgAdmin : http://localhost:5050 (admin@edugestdz.local / mot de passe = PGADMIN_PASSWORD du .env)
```

→ [Guide d'installation complet](docs/DEPLOIEMENT.md)

---

## 🧪 Tests

```bash
cd backend && php artisan test          # ≈ 1 140 tests (PHPUnit, Unit + Feature)
cd frontend && npx vitest run           # 137 tests (Vitest)
cd mobile && npm test                   # 14 tests (Jest — 2 suites à réparer, hors CI)
```

L'exactitude de ces comptes est vérifiée par la CI à chaque push ; les
suites backend et frontend doivent rester vertes pour merger.

---

## 📁 Structure du projet

```
edugest-dz/
├── backend/              # Laravel 11 — API REST
│   ├── app/
│   │   ├── Http/Controllers/Api/V1/  # 79 contrôleurs
│   │   ├── Services/                  # 75 services métier
│   │   ├── Models/                    # 106 modèles Eloquent
│   │   ├── Http/Middleware/           # 19 middlewares (sécurité, RBAC, tenant)
│   │   └── Console/Commands/          # Schedulers artisan
│   └── database/migrations/           # 95 migrations
├── frontend/             # React 18 + Vite
├── mobile/               # React Native 0.76 + Expo 52
├── tests/k6/             # Scénarios de charge (7 scénarios + lanceur)
├── docs/                 # Documentation de référence + archives (voir docs/README.md)
├── scripts/              # Outillage : secrets, audit, analyse statique, backups
├── docker/ nginx/ backups/
├── docker-compose.yml            # Développement (9 services)
├── docker-compose.prod.yml
├── docker-compose.selfhosted.yml
├── vercel.json  Makefile  install.sh  deploy.sh  update.sh
└── LICENSE  SECURITY.md  CHANGELOG.md  CONTRIBUTING.md
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
| [Conformité ANPDP](docs/ANPDP_DECLARATION.md) | Loi 18-07 — Guide déclaration |
| [Registre des traitements](docs/REGISTRE_TRAITEMENTS.md) | Loi 18-07 — Registre pré-rempli (responsable : l'établissement) |
| [Tests de charge](docs/PERF_TESTS_K6.md) | Scénarios k6 : smoke, charge, isolation RLS, throttles |
| [Monitoring](docs/MONITORING_CHECKLIST.md) | Health checks, Sentry, alertes, réponse incidents |
| [Réponse incidents](docs/guides/INCIDENT_RESPONSE_PLAN.md) | Procédure en cas d'incident |
| [Contribuer](CONTRIBUTING.md) | Guide de contribution |
| [Changelog](CHANGELOG.md) | Historique des versions |
| [Index des archives](docs/README.md) | Missions, audits, maquettes et études — historique projet |

---

## 🤝 Branches

- `main` → Production (protégée, PR obligatoire, CI doit être vert)
- Développement via branches de travail + pull requests (pas de branche `develop` de longue durée)

---

## 📞 Contact

- **Projet** : EduGest DZ — SaaS Éducatif Algérien
- **Conformité** : Loi 18-07 (outils fournis, déclaration ANPDP par établissement) · Hébergement Algérie en niveau 1

---

<div align="center">
<sub>Made with ❤️ in Oran, Algeria 🇩🇿</sub>
</div>
