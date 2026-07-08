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
