# 🤖 MISSION DEEPSEEK — Gestionnaire de Modules (Activer/Désactiver)
## EduGest DZ · Branche : develop · 7 Juillet 2026
## Tests actuels : 513+ ✅ · Objectif : ≥ 523 ✅ · 0 régression

---

## CONTEXTE — Pourquoi ce mécanisme

Chaque école est différente :
- Un centre de cours particuliers → pas besoin de Transport, Cantine, Stock
- Une école maternelle → pas besoin de BAC/BEM, Surveillance Dahua
- Un lycée privé → a besoin de tout
- Une école dans le Sud → besoin de LMS (cours en ligne) mais pas de Marketplace

**La solution :** Un système de modules activables/désactivables par tenant,
géré par le directeur lui-même depuis les paramètres de son école.

### Modules gérables (14 modules optionnels)
```
OBLIGATOIRES (jamais désactivables) :
  → Dashboard, Élèves, Planning, Présences, Notes, Bulletins, Factures, Auth

OPTIONNELS (activables/désactivables) :
  → transport, cantine, stock, budget, personnel, entretien
  → surveillance, lms, marketplace, examens, diagnostic
  → billets, pointage, bibliotheque
```

### RÈGLES ABSOLUES
1. 0 régression — les tests existants restent verts
2. PostgreSQL uniquement — jamais SQLite
3. Multi-tenant — chaque école a ses propres modules actifs
4. Les modules OBLIGATOIRES ne peuvent jamais être désactivés
5. Le middleware ne bloque jamais le health check (/api/health)
6. Ne pas modifier les contrôleurs existants

---

## ÉTAPES RÉALISÉES

### Étape 1 — Migration : table tenant_modules
`database/migrations/2026_07_07_200000_create_tenant_modules_table.php`

### Étape 2 — Model TenantModule
`app/Models/TenantModule.php` — Définition complète de tous les modules (15 modules)
Helpers statiques : getActifs, estActif, activer, desactiver, getEtatComplet

### Étape 3 — Middleware ModuleCheck
`app/Http/Middleware/ModuleCheck.php` — Mapping ROUTE_MAP, détection auto, 403 si inactif

### Étape 4 — Middleware alias
`bootstrap/app.php` — `$middleware->alias(['module' => ModuleCheck::class])`

### Étape 5 — Middleware sur routes existantes
`routes/api.php` — `'module:transport'`, `'module:cantine'`, etc. ajouté aux groupes prefix

### Étape 6 — ModuleController
`app/Http/Controllers/Api/V1/ModuleController.php` — index, activer, desactiver, bulk, actifs

### Étape 7 — Routes modules
`routes/api.php` — `Route::prefix('modules')->middleware(['auth:api', 'resolve.tenant'])`

### Étape 8 — ModulesContext React
`frontend/src/context/ModulesContext.jsx` — ModulesProvider + useModules() + fail-safe

### Étape 9 — ModulesProvider dans App.jsx
`frontend/src/App.jsx` — ModulesProvider entoure tout APRÈS AuthProvider

### Étape 10 — ModulesPage (interface on/off)
`frontend/src/pages/ModulesPage.jsx` — Toggle switches par catégorie, modal désactivation

### Étape 11 — Sidebar filtrage
`frontend/src/components/Sidebar.jsx` — .filter(isActive) + lien "Gestion des modules"

### Étape 12 — Tests
`tests/Feature/Controllers/ModuleControllerTest.php` — 10 tests

### Étape 13 — Migration + Tests
```bash
php artisan migrate
composer dump-autoload -o
php artisan test --parallel  # → 523+ verts (0 régression)
```

### Étape 14 — Commit + Push + PR
```bash
git add .
git commit -m "feat: Module Manager"
git push origin develop
# → PR develop → main
```
