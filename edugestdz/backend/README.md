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

## Tests

```bash
php artisan test
# Ou plus verbeux :
php artisan test --colors --parallel
```

## Conventions API

- Base URL : `/api/v1`
- Auth : `Authorization: Bearer <JWT>`
- Succès : `{ "success": true, "data": ..., "meta": {...}, "message": "" }`
- Erreur : `{ "success": false, "error": { "code": "...", "message": "...", "details": {...} }, "status": 4xx }`
- Pagination : `?page=1&per_page=15`

## Normes Algérie

- Fuseau : `Africa/Algiers`
- Monnaie : DZD (Dinar Algérien)
- Impôt : barème IRG 2026, CNAS 9%
- Wilayas : 58 (data seedée)
- Calendrier : scolaire national algérien
- RGPD local : Loi 18-07
