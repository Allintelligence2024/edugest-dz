# Quick Start — EduGest DZ

## Installation en 3 commandes

```bash
# 1. Backend
cd edugestdz/backend && composer install && cp .env.example .env
php artisan key:generate && php artisan jwt:secret --force
php artisan migrate --seed --force

# 2. Frontend
cd ../frontend && npm install && npm run dev

# 3. Ouvrir http://localhost:5173
```

## Identifiants démo

| Champ | Valeur |
|-------|--------|
| Email | `admin@edugest-demo.dz` |
| Mot de passe | `password` |

## Architecture

```
edugestdz/
├── backend/     → Laravel 11, PostgreSQL, JWT, API REST v1
├── frontend/    → React 18, Vite, Tailwind CSS
└── mobile/      → Expo (React Native)
```

## Variables d'environnement critiques

| Variable | Valeur par défaut | Description |
|----------|-------------------|-------------|
| `DB_CONNECTION` | `pgsql` | PostgreSQL obligatoire |
| `CACHE_STORE` | `array` | `array` pour les tests, `redis` en prod |
| `JWT_SECRET` | — | Générer avec `php artisan jwt:secret` |
| `TWILIO_SID/TOKEN` | — | Optionnel : appels vocaux absence |
| `GOOGLE_VISION_API_KEY` | — | Optionnel : scan bibliothèque |

## Commandes utiles

```bash
php artisan test                    # Backend : 1012+ tests
cd frontend && npm run test         # Frontend : Vitest
cd frontend && npm run build        # Build production
cd frontend && npm run lint         # ESLint
```
