# EduGest DZ — Backend (Laravel 11)

Plateforme SaaS multi-tenant de gestion de cours particuliers et écoles privées en Algérie.

## Stack

| Couche | Technologie |
|--------|------------|
| Framework | Laravel 11 (PHP 8.2+) |
| BDD | PostgreSQL 16 |
| Cache / Queue | Redis 7 |
| Auth | JWT (tymon/jwt-auth) |
| Frontend | React 18 + Vite (voir `../frontend/`) |
| Mobile | React Native 0.76 + Expo 52 (voir `../mobile/`) |
| Conteneurisation | Docker Compose |

## Installation

```bash
# 1. Cloner et entrer dans le projet
git clone <url> && cd edugestdz/backend

# 2. Copier la configuration
cp .env.example .env
# → Éditer .env : DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD, REDIS_PASSWORD

# 3. Installer les dépendances
composer install

# 4. Générer les clés critiques
php artisan key:generate       # APP_KEY — chiffrement Laravel
php artisan jwt:secret         # JWT_SECRET — signature des tokens

# 5. Vérifier la configuration
php artisan edugest:check-config

# 6. Base de données
php artisan migrate --seed

# 7. Lancer le serveur de dev
php artisan serve
```

Avec Docker :

```bash
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan jwt:secret
docker compose exec app php artisan migrate --seed
```

## Commandes Artisan

| Commande | Description |
|----------|-------------|
| `php artisan edugest:check-config` | Vérifie les clés, BDD, Redis, fuseau, cache |
| `php artisan edugest:check-config --secrets-only` | Vérifie uniquement APP_KEY et JWT_SECRET |
| `php artisan edugest:calculer-paies` | Calcule les paies mensuelles des enseignants |
| `php artisan edugest:generer-seances` | Génère les séances hebdomadaires |
| `php artisan edugest:relances-paiement` | Envoie les relances de paiement |
| `php artisan edugest:sms-absents` | SMS automatique parents pour absences (8h30 semaine) |
| `php artisan edugest:relances-impayes` | Relances SMS échelonnées J+1/J+3/J+7/J+15 |
| `php artisan edugest:alertes-stock` | Alertes articles sous seuil minimum |
| `php artisan edugest:alertes-preventif` | Alertes entretiens préventifs à échéance |

## Modules disponibles (14 modules)

| Module | Description |
|--------|------------|
| M01 Inscriptions | Dossier élève, parents, import CSV |
| M02 Planning | Emploi du temps, séances, conflits |
| M03 Finance | Factures, paiements cash + CIB/Dahabia |
| M04 Pédagogie | Notes, moyennes, bulletins PDF |
| M05 Enseignants | Dossier, contrats, paie IRG/CNAS |
| M06 Communication | SMS, WhatsApp, push notifications |
| M07 Reporting | Dashboards, exports Excel/PDF |
| M08 Auth/RBAC | JWT + 2FA + multi-tenant |
| M09 Transport | Circuits, arrêts, pointage bus |
| M10 Cantine | Menus, inscriptions, pointage repas |
| M11 Stock | Inventaire, mouvements, bons commande |
| M12 Personnel | Non-enseignant, congés, paie |
| M13 Budget | Dépenses, prévisionnel, bilan |
| M14 Entretien | Locaux, interventions, préventif |
| MKT Marketplace | Recherche centres, réservations, avis |

## Tests

```bash
php artisan test
# Ou plus verbeux :
php artisan test --colors --parallel
# → ≥ 440 tests verts
```

## Conventions API

- Base URL : `/api/v1`
- Auth : `Authorization: Bearer <JWT>`
- Succès : `{ "success": true, "data": ..., "meta": {...}, "message": "" }`
- Erreur : `{ "success": false, "error": { "code": "...", "message": "...", "details": {...} }, "status": 4xx }`
- Pagination : `?page=1&per_page=15`

## Documentation API

Après génération Swagger :
```
php artisan l5-swagger:generate
→ http://localhost/api/documentation
```

## Normes Algérie

- Fuseau : `Africa/Algiers`
- Monnaie : DZD (Dinar Algérien)
- Impôt : barème IRG 2026, CNAS 9%
- Wilayas : 58 (data seedée)
- Calendrier : scolaire national algérien
- RGPD local : Loi 18-07
