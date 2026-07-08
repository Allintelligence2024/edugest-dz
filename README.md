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
