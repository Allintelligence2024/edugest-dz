# CAT2 — Messages d'erreur français + états vides

## Résumé

Ajout du support linguistique français pour les messages d'erreur API, gestion des états vides dans les pages principales, et renderers d'erreur HTTP personnalisés.

## Modifications

### 1. config/app.php — Locale française

```php
'locale'         => env('APP_LOCALE', 'fr'),
'fallback_locale' => env('APP_FALLBACK_LOCALE', 'fr'),
'faker_locale'   => env('APP_FAKER_LOCALE', 'fr_FR'),
```

### 2. resources/lang/fr/validation.php

Fichier de traduction des messages de validation Laravel en français. Couvre :
- Toutes les règles de validation courantes (`required`, `email`, `max`, `min`, etc.)
- Les attributs traduits (`nom`, `prenom`, `date_naissance`, etc.)

### 3. bootstrap/app.php — Renderers d'erreur

6 renderers ajoutés **AVANT** les reporters Sentry/Telegram :

| Exception | Code HTTP | Message |
|---|---|---|
| `ValidationException` | 422 | `Données invalides` + détails |
| `ModelNotFoundException` | 404 | `Ressource introuvable : {Model}` |
| `NotFoundHttpException` | 404 | `La ressource demandée est introuvable.` |
| `MethodNotAllowedHttpException` | 405 | `Cette méthode HTTP n'est pas autorisée.` |
| `TokenMismatchException` | 419 | `Session expirée.` |
| `ThrottleRequestsException` | 429 | `Trop de requêtes.` |

Format de réponse cohérent :
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Données invalides",
    "details": { "nom": ["Le nom est obligatoire"] }
  }
}
```

### 4. EmptyState.jsx — Composant réutilisable

Composant `src/components/ui/EmptyState.jsx` :
- Props : `icon`, `title`, `description`, `action`
- Styles inline + Tailwind (compatible tout contexte)
- Utilisé dans les 3 pages

### 5. ElevesListPage.jsx

- Import `EmptyState`
- Quand `!isLoading && data.data.length === 0 && !search && pas de filtres` → affiche EmptyState avec bouton "Ajouter un élève"
- Sinon → DataTable avec `emptyMessage="Aucun élève trouvé"` (si recherche/filtres actifs)

### 6. FacturesPage.jsx

- Import `EmptyState`
- Quand `!isLoading && factures.length === 0` → EmptyState avec bouton "Nouvelle facture"
- Sinon → tableau classique

### 7. PlanningPage.jsx

- Import `EmptyState`
- Quand `semaine.length === 0 && pas de filtres` → EmptyState avec bouton "Nouveau cours"
- Sinon → grille planning classique

### 8. ValidationFrancaiseTest.php — 5 tests

| Test | Vérification |
|---|---|
| `test_champ_requis_message_en_francais` | POST /eleves sans données → message contient "obligatoire" |
| `test_email_invalide_message_en_francais` | Email invalide → message contient "e-mail" |
| `test_404_message_en_francais` | GET /eleves/{fake} → message contient "introuvable" |
| `test_405_message_en_francais` | DELETE /eleves → message contient "méthode" |
| `test_validation_exception_format_coherent` | Format `{success, error: {code, message, details}}` |

## Validation

```bash
php artisan test tests/Feature/ValidationFrancaiseTest.php   # 5 tests ✅
php artisan test --parallel                                    # 1027+ tests ✅
npm run build                                                  # 0 erreurs ✅
npm run test                                                   # 51+ tests ✅
```

## Fichiers modifiés

| Fichier | Action |
|---|---|
| `config/app.php` | locale → fr |
| `resources/lang/fr/validation.php` | Nouveau — traductions FR |
| `bootstrap/app.php` | +6 renderers avant Sentry |
| `src/components/ui/EmptyState.jsx` | Nouveau — composant |
| `src/pages/ElevesListPage.jsx` | +EmptyState |
| `src/pages/FacturesPage.jsx` | +EmptyState |
| `src/pages/PlanningPage.jsx` | +EmptyState |
| `tests/Feature/ValidationFrancaiseTest.php` | Nouveau — 5 tests |
