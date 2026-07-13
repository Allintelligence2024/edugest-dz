# CAT3 — Modèles, scopes et cohérence

## Résumé

Ajout de scopes réutilisables aux modèles Facture, Note et Groupe. Suppression du doublon BelongsToTenant dans Paiement. Ajout de la relation enseignant dans Groupe. Migration du BulletinController::pdf() vers la queue.

## Modifications

### 1. Facture.php — Remplacement complet

| Scope | Description |
|---|---|
| `parEleve($id)` | Filtre par eleve_id |
| `parAnnee($annee)` | Filtre par année |
| `parMois($mois)` | Filtre par mois |
| `enCours()` | statut IN ('émise', 'envoyée') |
| `payees()` | statut = 'payée' |
| `impayees()` | statut = 'impayée' |
| `echeanceProche($jours)` | Échéance dans $jours jours, non payées |

Relations préservées : `eleve()`, `lignes()`, `paiements()`
Factory-compatible : aucun champ obligatoire ajouté.

### 2. Note.php — Remplacement complet

| Scope | Description |
|---|---|
| `parEleve($id)` | Filtre par eleve_id |
| `parEvaluation($id)` | Filtre par evaluation_id |
| `absents()` | absent = true |
| `avecNote()` | absent = false AND note IS NOT NULL |
| `parTrimestre($tri)` | Via relation evaluation |
| `parMatiere($id)` | Via relation evaluation |
| `moyenne()` | Absents exclus, note non nulle |

Relations préservées : `evaluation()`, `eleve()`

### 3. Paiement.php — Fix doublon

- Supprimé : `use App\Traits\BelongsToTenant;` (ligne 5)
- Supprimé : `use BelongsToTenant;` (ligne 10)
- BaseModel utilise déjà BelongsToTenant → pas de perte de fonctionnalité
- Scopes existants préservés : `enLigne()`, `confirmes()`

### 4. Groupe.php — Remplacement complet

| Ajout | Détail |
|---|---|
| `$fillable` | + `enseignant_id` |
| `enseignant()` | BelongsTo(Enseignant::class) |
| `parNiveau($niveau)` | Scope par niveau_scolaire |
| `actifs()` | statut = 'actif' |
| `parEnseignant($id)` | Scope par enseignant_id |
| `parMatiere($id)` | Scope par matiere_id |

Migration : `2026_07_13_100000_add_enseignant_id_to_groupes_table.php`
- `hasColumn()` guard → idempotent
- Colonne nullable + FK vers enseignants

### 5. BulletinController::pdf() — Queue

Avant : `$this->service->genererPDF(...)` synchrone
Après : `GenerateBulletinPdfJob::dispatch($bulletin)` + retour 202

Comportement :
- PDF déjà généré → download direct
- statut_pdf = en_attente/en_cours/null → dispatch job + retour 202
- statut_pdf = erreur → retry dispatch + retour 202

### 6. ModelesScopesTest.php — 9 tests

| Test | Vérification |
|---|---|
| `test_facture_scope_par_eleve` | 2 factures eleve1, 1 eleve2 → count OK |
| `test_facture_scopes_statuts` | enCours=2, payees=1, impayees=1 |
| `test_note_scope_par_eleve` | 3 notes eleve1, 1 eleve2 → count OK |
| `test_note_scope_absents` | 2 absents, 1 présent → count OK |
| `test_paiement_no_duplicate_belongs_to_tenant` | Paiement n'a pas BelongsToTenant |
| `test_groupe_has_enseignant_id_in_fillable` | 'enseignant_id' dans getFillable() |
| `test_groupe_scope_par_niveau` | 2 × 3AS, 1 × 2AS → count OK |
| `test_groupe_scope_actifs` | 2 actifs, 1 inactif → count OK |
| `test_groupe_has_enseignant_relation` | $groupe->enseignant() existe |

## Validation

```bash
php artisan test tests/Unit/Models/ModelesScopesTest.php  # 9 tests ✅
php artisan test                                            # 1020+ tests ✅
```

## Fichiers modifiés

| Fichier | Action |
|---|---|
| `app/Models/Facture.php` | Remplacé — +7 scopes |
| `app/Models/Note.php` | Remplacé — +7 scopes |
| `app/Models/Paiement.php` | -2 lignes (doublon BelongsToTenant) |
| `app/Models/Groupe.php` | Remplacé — +enseignant_id, +4 scopes, +relation |
| `database/migrations/2026_07_13_100000_add_enseignant_id_to_groupes_table.php` | Nouveau |
| `app/Http/Controllers/Api/V1/BulletinController.php` | pdf() → queue dispatch |
| `tests/Unit/Models/ModelesScopesTest.php` | Nouveau — 9 tests |
