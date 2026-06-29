# EduGest DZ — SaaS Gestion Établissements Éducatifs

Plateforme SaaS multi-tenant de gestion des cours particuliers et écoles privées en Algérie.

## Stack
- **Backend** : Laravel 11 · PHP 8.2
- **Frontend** : React 18 + Vite + Tailwind CSS
- **Mobile** : React Native 0.76 + Expo 52
- **BDD** : PostgreSQL 16 + Redis 7 + Meilisearch v1.8
- **Infra** : Docker Compose · GitHub Actions CI

## Prérequis
- Docker Desktop
- Git

## Installation (5 minutes)

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

**Accès :**
- API : http://localhost/api/v1
- Frontend : http://localhost:5173
- pgAdmin : http://localhost:5050 (admin@edugestdz.local / PgAdmin@2026)

## Tests
```bash
docker compose exec app php artisan test --parallel
```

## Branches
- `main` → production (protégée, PR obligatoire)
- `develop` → développement actif
