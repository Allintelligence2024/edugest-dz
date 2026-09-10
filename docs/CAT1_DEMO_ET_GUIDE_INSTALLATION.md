# CAT1 — Démonstration & Guide d'installation

> Démarrer EduGest DZ en 5 minutes sur votre machine.

---

## Étape 1 — Prérequis

| Outil | Version minimale | Vérification |
|-------|-------------------|--------------|
| PHP | 8.2+ | `php -v` |
| Composer | 2.x | `composer -V` |
| Node.js | 18+ | `node -v` |
| PostgreSQL | 14+ | `psql --version` |
| Redis | 7+ | `redis-cli ping` |

---

## Étape 2 — Backend (Laravel)

```bash
cd edugestdz/backend

# 1. Dépendances
composer install

# 2. Environnement
cp .env.example .env
php artisan key:generate
php artisan jwt:secret --force

# 3. Configurer la base PostgreSQL dans .env
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=edugestdz
# DB_USERNAME=edugest_user
# DB_PASSWORD=...

# 4. Migrations + Données démo
php artisan migrate --seed --force
```

Le seeder `EcoleDemoSeeder` crée automatiquement :
- Un tenant « École Demo EduGest » (centre, plan pro)
- Un admin : **admin@edugest-demo.dz** / **password**
- 3 matières (Maths, Physique, Français)
- 3 salles
- 3 enseignants avec horaires
- 15 élèves avec notes, présences, factures
- 5 évaluations, 4 bulletins trimestriels
- Des diagnostics EWS, des signalements comportement
- Des plans de fractionnement

---

## Étape 3 — Frontend (React)

```bash
cd edugestdz/frontend

# 1. Dépendances
npm install

# 2. Lancer le serveur dev
npm run dev
```

Le frontend démarre sur `http://localhost:5173`.

---

## Étape 4 — Connexion à la démo

1. Ouvrir `http://localhost:5173`
2. Entrer les identifiants :
   - **Email** : `admin@edugest-demo.dz`
   - **Mot de passe** : `password`
3. Le tableau de bord s'affiche avec les données réelles de la démo

### Fonctionnalités disponibles en démo

| Module | Description |
|--------|-------------|
| Tableau de bord | KPI, graphiques CA, taux de recouvrement, alertes |
| Élèves | Liste, détails, inscriptions, présences |
| Planning | Séances, emplois du temps |
| Notes & Bulletins | Évaluations, notes, bulletins trimestriels |
| Finance | Factures, paiements, impayés |
| Diagnostics | Early Warning System (EWS), prédiction IA |
| Transport | Gestion des navettes |
| Cantine | Repas et réservations |
| LMS | Cours en ligne, quiz, progression |
| Scan Bibliothèque | Scan photo de livres via Google Vision API |

---

## Étape 5 — Tests (optionnel)

### Backend (PHP)
```bash
cd edugestdz/backend
php artisan test
# Résultat attendu : 1012+ tests passés
```

### Frontend (Vitest)
```bash
cd edugestdz/frontend
npm run test
```

---

## Dépannage

| Problème | Solution |
|----------|----------|
| `SQLSTATE[08006]` | PostgreSQL non lancé. Vérifier le service. |
| `Cache driver not valid` | Ajouter `CACHE_STORE=array` dans `.env` |
| `JWT_SECRET not set` | Relancer `php artisan jwt:secret --force` |
| Frontend ne charge pas | Vérifier que le proxy Vite pointe vers le backend (port 8000) |
| `GOOGLE_VISION_API_KEY` manquant | Optionnel. Sans clé, le scan bibliothèque retourne un message « non configuré » |
