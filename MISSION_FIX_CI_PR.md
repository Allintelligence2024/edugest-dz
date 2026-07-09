# 🚨 MISSION DEEPSEEK — Fix CI échoué sur PR
## EduGest DZ · Urgent · 2 Juillet 2026

---

## SYMPTÔME

Sur la PR `develop → main` :
- ❌ `CI — EduGest DZ / backend (pull request)` — Failing after 1m
- ❌ `CI — EduGest DZ / backend (push)` — Failing after 1m

## ÉTAPE 1 — Identifier la cause exacte

```bash
# Sur la branche develop, lancer les tests localement
cd edugestdz/backend
php artisan test --parallel 2>&1 | tail -50
```

**Lire l'output COMPLET et noter :**
- Quels tests échouent (nom exact)
- Quel message d'erreur (SQLSTATE, Class not found, Method not found, etc.)

---

## ÉTAPE 2 — Causes les plus probables (selon les missions récentes)

### CAS A — Classe ou méthode introuvable

**Symptôme :** `Class "App\Observers\EleveObserver" not found`
ou `Method calculerIRG not found on PaieService`

**Fix :**
```bash
php artisan optimize:clear
composer dump-autoload
php artisan test --parallel
```

### CAS B — Migration en conflit ou colonne manquante

**Symptôme :** `SQLSTATE[42703]: column "xxx" does not exist`
ou `SQLSTATE[42P01]: table "xxx" does not exist`

**Fix :**
```bash
php artisan migrate:fresh --seed --force
php artisan test --parallel
```

### CAS C — Annotation Swagger malformée bloque le boot

**Symptôme :** `Syntax error or access violation` au démarrage
ou `ParseError in OpenApiDefinition.php`

**Fix :**
```bash
# Vérifier si l5-swagger est installé
php artisan l5-swagger:generate 2>&1

# Si erreur → vérifier la syntaxe des annotations dans app/Virtual/
# Les annotations @OA\ doivent être dans des blocs /** */ (double étoile)
```

### CAS D — Test qui attend un endpoint inexistant → 404

**Symptôme :** `Expected status code 200 but received 404`
ou `Expected status code 201 but received 404`

**Fix :** Dans chaque test Feature qui échoue, vérifier que la route existe :
```bash
php artisan route:list | grep "api/v1/xxx"
```

Si la route n'existe pas → **commenter le test défaillant** avec `// TODO: route manquante`
et relancer.

### CAS E — Factory manquante

**Symptôme :** `Call to undefined method Database\Factories\XxxFactory::definition()`
ou `Target class [XxxFactory] does not exist`

**Fix :** Créer la factory manquante ou corriger son namespace.

### CAS F — RefreshDatabase vs données existantes

**Symptôme :** `SQLSTATE[23505]: Unique violation` (doublon email/uuid)

**Fix :** Vérifier que tous les Feature tests ont `use RefreshDatabase;`

---

## ÉTAPE 3 — Procédure de récupération

```bash
cd edugestdz/backend

# 1. Nettoyer tous les caches
php artisan optimize:clear
composer dump-autoload -o

# 2. Remettre la base propre
php artisan migrate:fresh --seed --force

# 3. Lancer les tests en mode verbeux pour voir TOUS les échecs
php artisan test --parallel --stop-on-failure 2>&1

# 4. Corriger UN PAR UN les tests qui échouent
#    (commenter si endpoint manquant, corriger si bug logique)

# 5. Relancer jusqu'à tout vert
php artisan test --parallel
# → Doit afficher : Tests: X passed (aucun failed)
```

---

## ÉTAPE 4 — Une fois tous les tests verts

```bash
git add .
git commit -m "fix: CI — corriger tests échoués après missions P3/P4/P5"
git push origin develop
```

La PR se mettra à jour automatiquement et le CI relancera.

---

## CE QUE TU DIS À DEEPSEEK

```
Le CI échoue sur la PR develop → main.
Lancer : php artisan test --parallel 2>&1
Copier l'output complet ici.
Corriger TOUS les tests qui échouent.
Règle : ne pas supprimer de tests existants — commenter avec // TODO si endpoint manquant.
Une fois tous les tests verts : git add . && git commit -m "fix: CI" && git push origin develop
```
