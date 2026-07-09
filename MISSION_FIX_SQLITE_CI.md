# 🚨 MISSION DEEPSEEK — Fix CI : SQLite → PostgreSQL
## EduGest DZ · URGENT · 2 Juillet 2026

---

## PROBLÈME IDENTIFIÉ

Le commit `3513a06` dit **"migrate to SQLite"** — c'est une erreur critique.
Le CI GitHub Actions utilise **PostgreSQL 16**, pas SQLite.
Tous les tests qui utilisent SQLite échouent en CI.

---

## RÈGLE ABSOLUE

EduGest DZ utilise **PostgreSQL uniquement**.
Ne jamais utiliser SQLite — même pour les tests.
Le CI est configuré avec PostgreSQL 16 + Redis 7.

---

## ÉTAPE 1 — Vérifier phpunit.xml

**Lire :** `edugestdz/backend/phpunit.xml`

Il ne doit PAS contenir :
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

**Si ces lignes existent → les supprimer ou remplacer par :**

```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_HOST" value="127.0.0.1"/>
<env name="DB_PORT" value="5432"/>
<env name="DB_DATABASE" value="edugestdz_test"/>
<env name="DB_USERNAME" value="edugest_user"/>
<env name="DB_PASSWORD" value="EduGest@2026!"/>
<env name="REDIS_HOST" value="127.0.0.1"/>
<env name="REDIS_PORT" value="6379"/>
<env name="CACHE_DRIVER" value="array"/>
<env name="SESSION_DRIVER" value="array"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="MAIL_MAILER" value="array"/>
```

**Le fichier phpunit.xml complet doit être :**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         stopOnFailure="false"
>
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
    </testsuites>

    <source>
        <include>
            <directory suffix=".php">./app</directory>
        </include>
    </source>

    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="APP_KEY" value="base64:PLACEHOLDER_WILL_BE_OVERWRITTEN"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="DB_CONNECTION" value="pgsql"/>
        <env name="DB_HOST" value="127.0.0.1"/>
        <env name="DB_PORT" value="5432"/>
        <env name="DB_DATABASE" value="edugestdz_test"/>
        <env name="DB_USERNAME" value="edugest_user"/>
        <env name="DB_PASSWORD" value="EduGest@2026!"/>
        <env name="REDIS_HOST" value="127.0.0.1"/>
        <env name="REDIS_PORT" value="6379"/>
        <env name="REDIS_PASSWORD" value="null"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
        <env name="L5_SWAGGER_GENERATE_ALWAYS" value="false"/>
    </php>
</phpunit>
```

---

## ÉTAPE 2 — Vérifier .env.testing (si existe)

**Vérifier :** `edugestdz/backend/.env.testing`

Si ce fichier existe et contient `DB_CONNECTION=sqlite` → le corriger :

```dotenv
APP_ENV=testing
APP_KEY=base64:PLACEHOLDER
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=edugestdz_test
DB_USERNAME=edugest_user
DB_PASSWORD=EduGest@2026!
REDIS_HOST=127.0.0.1
CACHE_DRIVER=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
```

---

## ÉTAPE 3 — Vérifier les factories pour UUID

Le commit mentionnait "fix UUID inscriptions" — vérifier que les factories
n'utilisent pas `$this->faker->uuid()` là où PostgreSQL attend un vrai UUID v4.

Dans toutes les factories, remplacer :
```php
// ❌ Peut causer des problèmes
'id' => $this->faker->uuid()

// ✅ Correct
'id' => \Illuminate\Support\Str::uuid()->toString()
```

Si les modèles utilisent `HasUuids` (Laravel natif), **ne pas mettre `id` dans la factory** — Laravel le génère automatiquement.

---

## ÉTAPE 4 — Vérifier les tests qui utilisaient SQLite

Chercher dans tous les fichiers de tests :

```bash
grep -r "sqlite" edugestdz/backend/tests/ --include="*.php" -l
grep -r "sqlite" edugestdz/backend/config/ --include="*.php" -l
grep -r ":memory:" edugestdz/backend/ --include="*.php" -l
```

Pour chaque fichier trouvé → supprimer la référence SQLite.

---

## ÉTAPE 5 — Tester localement avec PostgreSQL

```bash
cd edugestdz/backend

# Nettoyer
php artisan optimize:clear
composer dump-autoload -o

# Base propre
php artisan migrate:fresh --seed --force

# Tests complets
php artisan test --parallel 2>&1

# Vérifier : 0 failed, tout vert
```

**Si un test échoue encore :**
- Lire le message d'erreur exact
- S'il dit "table not found" → `migrate:fresh` n'a pas tourné → vérifier DB_CONNECTION
- S'il dit "column not found" → migration manquante → `php artisan migrate`
- S'il dit "UUID invalid" → corriger la factory (voir Étape 3)
- S'il dit "Route not found (404)" → commenter le test avec `// TODO: route à créer`

---

## ÉTAPE 6 — Commit et push

Une fois **0 tests échoués** :

```bash
git add phpunit.xml
git add .env.testing  # si modifié
git add tests/        # si tests modifiés
git add database/factories/  # si factories modifiées
git commit -m "fix: CI — revenir PostgreSQL, supprimer SQLite, corriger factories UUID"
git push origin develop
```

→ Le CI relancera automatiquement sur la PR #14.
→ Attendu : ✅ 2/2 checks verts.

---

## CE QUE TU DIS À DEEPSEEK

```
PROBLÈME : le commit "migrate to SQLite" a cassé le CI.
Le CI GitHub Actions utilise PostgreSQL 16, pas SQLite.

Actions :
1. Lire phpunit.xml → supprimer toute référence sqlite/:memory:
2. Corriger avec la config PostgreSQL fournie dans MISSION_FIX_SQLITE_CI.md
3. grep -r "sqlite" tests/ config/ → corriger tous les fichiers trouvés
4. php artisan migrate:fresh --seed --force
5. php artisan test --parallel → 0 failed obligatoire
6. git commit -m "fix: CI — PostgreSQL, no SQLite" && git push origin develop

Ne jamais utiliser SQLite dans ce projet — PostgreSQL uniquement.
```
