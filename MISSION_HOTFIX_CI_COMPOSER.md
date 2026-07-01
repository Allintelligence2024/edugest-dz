# 🚨 MISSION DEEPSEEK — HOTFIX : CI Rouge sur main
## EduGest DZ · Branche : main → hotfix/composer-php82
## Priorité : CRITIQUE · À faire avant tout le reste

---

## DIAGNOSTIC EXACT

Le CI #22 (run `fb6683f`) échoue sur `main` avec cette erreur :

```
Your lock file does not contain a compatible set of packages.
Please run composer update.

Problem 1
- maennchen/zipstream-php is locked to version 3.2.2
- maennchen/zipstream-php 3.2.2 requires php-64bit ^8.3
  → your php-64bit version (8.2.31) does not satisfy that requirement.

Problem 2
- phpoffice/phpspreadsheet 1.30.5 requires maennchen/zipstream-php ^2.1 || ^3.0
  → satisfiable by maennchen/zipstream-php[3.2.2]
  → mais 3.2.2 nécessite PHP 8.3, on tourne sur PHP 8.2
```

**Cause racine :** `maennchen/zipstream-php` a été mis à jour automatiquement
vers la version 3.2.2 qui exige PHP 8.3, mais le CI tourne sur PHP 8.2.
Le `composer.lock` est désynchronisé avec la contrainte PHP du projet.

**Ce qu'il NE FAUT PAS faire :** passer à PHP 8.3 dans le CI
(risque de casser d'autres dépendances non testées).

**Ce qu'il FAUT faire :** contraindre `maennchen/zipstream-php` à une version
compatible PHP 8.2, puis regénérer le `composer.lock`.

---

## ÉTAPES À EXÉCUTER

### Étape 1 — Créer une branche hotfix depuis main

```bash
git checkout main
git pull origin main
git checkout -b hotfix/composer-php82
```

---

### Étape 2 — Contraindre la version zipstream dans composer.json

**Modifier :** `edugestdz/backend/composer.json`

Dans la section `"require"`, ajouter cette contrainte **après** `"maatwebsite/excel"` :

```json
"maennchen/zipstream-php": "^2.4",
```

Le bloc `"require"` doit ressembler à ceci après modification :

```json
"require": {
    "php": "^8.2",
    "barryvdh/laravel-dompdf": "^3.0",
    "http-interop/http-factory-guzzle": "^1.0",
    "laravel/framework": "^11.31",
    "laravel/scout": "^10.0",
    "laravel/tinker": "^2.9",
    "maannchen/zipstream-php": "^2.4",
    "maatwebsite/excel": "^3.1",
    "meilisearch/meilisearch-php": "^1.0",
    "pusher/pusher-php-server": "^7.0",
    "spatie/laravel-activitylog": "^4.0",
    "spatie/laravel-permission": "^6.0",
    "spatie/laravel-query-builder": "^5.0",
    "tymon/jwt-auth": "^2.1"
},
```

---

### Étape 3 — Regénérer le composer.lock

Dans `edugestdz/backend/` :

```bash
cd edugestdz/backend
composer update maennchen/zipstream-php --with-dependencies
```

Cette commande va :
- Rétrograder `maennchen/zipstream-php` de 3.2.2 vers la dernière 2.x compatible PHP 8.2
- Mettre à jour `phpoffice/phpspreadsheet` si nécessaire pour satisfaire la contrainte
- Regénérer `composer.lock` avec les versions compatibles

---

### Étape 4 — Vérifier que l'installation fonctionne localement

```bash
composer install --no-interaction --prefer-dist
php artisan test --parallel
```

→ Attendu : **260+ tests verts**, 0 erreur Composer.

---

### Étape 5 — Commit et push

```bash
cd ../..   # retour à la racine du repo
git add edugestdz/backend/composer.json edugestdz/backend/composer.lock
git commit -m "fix: contraindre zipstream-php ^2.4 pour compatibilité PHP 8.2 (CI hotfix)"
git push origin hotfix/composer-php82
```

---

### Étape 6 — PR hotfix → main

Sur GitHub :
```
https://github.com/Allintelligence2024/edugest-dz/compare/main...hotfix/composer-php82
```

- Titre : `fix: zipstream-php ^2.4 — compatibilité PHP 8.2 CI`
- Description :
```
Hotfix CI #22 — main rouge.

Cause : maennchen/zipstream-php 3.2.2 exige PHP ^8.3,
le CI tourne sur PHP 8.2.31.

Fix : contrainte ^2.4 dans composer.json + composer.lock regénéré.
Tests : 260+ verts confirmés localement.
```

→ Merger immédiatement après que le CI passe au vert sur la PR.

---

### Étape 7 — Après le merge : resync develop

```bash
git checkout develop
git pull origin main
git push origin develop
```

---

## VÉRIFICATION FINALE

Après le merge du hotfix dans main :
- CI sur main → ✅ vert
- `php artisan test --parallel` → 260+ tests
- `composer install` → 0 erreur
- 0 PR ouverte

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git

URGENCE : CI rouge sur main (run #22, commit fb6683f).

Cause confirmée : maennchen/zipstream-php 3.2.2 exige PHP 8.3,
le CI tourne sur PHP 8.2. Le composer.lock est incompatible.

Fichier : MISSION_HOTFIX_CI_COMPOSER.md

Exécute les 7 étapes dans l'ordre.
Ne pas passer à PHP 8.3 — contraindre zipstream à ^2.4.
Après le merge hotfix → main : CI doit être vert.
Reporter le résultat du CI et le nombre de tests.
```
