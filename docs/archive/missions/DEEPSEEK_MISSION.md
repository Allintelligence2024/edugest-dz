# 🤖 MISSION DEEPSEEK — EduGest DZ
## Fichier d'échange · 29 Juin 2026

---

## CONTEXTE DU PROJET

**Repo GitHub :** https://github.com/Allintelligence2024/edugest-dz.git  
**Branche de travail :** `develop`  
**Stack :** Laravel 11 · PHP 8.2 · PostgreSQL 16 · React 18 · React Native (Expo)  
**Structure :** tout le code est dans `edugestdz/backend/` (pas à la racine)

---

## ÉTAT VÉRIFIÉ AU 29 JUIN 2026

### ✅ DÉJÀ FAIT — Ne pas retoucher

| Fichier | État |
|---|---|
| `.github/workflows/ci.yml` | Corrigé — working-directory, sed, jwt:secret --force |
| `app/Models/Eleve.php` | Corrigé — `parentsPrincipaux(): BelongsToMany` |
| `app/Traits/BelongsToTenant.php` | Corrigé — fail-safe `whereRaw('1=0')` + RuntimeException |
| `tests/Feature/Api/TenantIsolationTest.php` | 16 tests passent ✅ |
| CI sur `develop` | **192 tests verts** ✅ |
| Branche `develop` | Créée et active ✅ |

### ❌ PAS ENCORE FAIT — Ta mission

Voir les 5 tâches ci-dessous dans l'ordre de priorité.

---

## TÂCHE 1 — MERGE develop → main + Branch Protection
**Priorité : CRITIQUE · Durée estimée : 15 minutes**

### Étape 1a — Merger develop dans main
```bash
git checkout main
git merge develop --no-ff -m "merge: develop → main (192 tests verts)"
git push origin main
```

### Étape 1b — Branch protection (via GitHub API ou manuellement)
Dans GitHub → Settings → Branches → Add branch protection rule :
- Branch name pattern : `main`
- ✅ Require a pull request before merging
- ✅ Require status checks to pass before merging
  - Status check : `CI — EduGest DZ / backend`
- ✅ Do not allow bypassing the above settings

**Vérification :** après le merge, GitHub Actions sur `main` doit afficher ✅ vert.

---

## TÂCHE 2 — CURRICULUM ALGÉRIEN : Migration + Seeder
**Priorité : HAUTE · Durée estimée : 1 jour**

### Contexte
Le système de notes actuel (`evaluations`, `notes`) utilise un champ `matiere_id` libre.  
Il manque la base de données officielle du curriculum algérien (1AP→3AS, 7 branches BAC).  
Sans ça, les bulletins ont des coefficients faux ou arbitraires.

### Étape 2a — Créer la migration

**Chemin :** `edugestdz/backend/database/migrations/2026_06_29_100000_create_curriculum_algerien_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Paliers ──
        Schema::create('paliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('nom_fr', 100);
            $table->string('nom_ar', 100);
            $table->unsignedTinyInteger('ordre');
            $table->timestamps();
        });

        // ── Niveaux scolaires ──
        Schema::create('niveaux_scolaires', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('palier_id');
            $table->string('code', 10)->unique(); // '1AP','2AM','3AS'
            $table->string('nom_fr', 100);
            $table->string('nom_ar', 100);
            $table->unsignedTinyInteger('ordre');
            $table->timestamps();
            $table->foreign('palier_id')->references('id')->on('paliers')->onDelete('cascade');
        });

        // ── Branches (filières lycée) ──
        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 10)->unique(); // 'SE','MATH','TM','LP','GE','LE'
            $table->string('nom_fr', 100);
            $table->string('nom_ar', 100);
            $table->jsonb('niveaux_applicables'); // ["2AS","3AS"]
            $table->timestamps();
        });

        // ── Matières par niveau + branche avec coefficient ──
        Schema::create('matieres_curriculum', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('niveau_id');
            $table->uuid('branche_id')->nullable();
            $table->string('matiere_fr', 100);
            $table->string('matiere_ar', 100);
            $table->unsignedTinyInteger('coefficient');
            $table->unsignedTinyInteger('volume_horaire_hebdo')->nullable();
            $table->boolean('est_principale')->default(false);
            $table->boolean('est_facultatif')->default(false);
            $table->unsignedTinyInteger('ordre')->default(0);
            $table->timestamps();
            $table->foreign('niveau_id')->references('id')->on('niveaux_scolaires')->onDelete('cascade');
            $table->foreign('branche_id')->references('id')->on('branches')->onDelete('set null');
            $table->unique(['niveau_id', 'branche_id', 'matiere_fr']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matieres_curriculum');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('niveaux_scolaires');
        Schema::dropIfExists('paliers');
    }
};
```

### Étape 2b — Créer le seeder

**Chemin :** `edugestdz/backend/database/seeders/CurriculumAlgerienSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CurriculumAlgerienSeeder extends Seeder
{
    public function run(): void
    {
        // ── PALIERS ──
        $palierPrimaire  = (string) Str::uuid();
        $palierMoyen     = (string) Str::uuid();
        $palierLycee     = (string) Str::uuid();

        DB::table('paliers')->insert([
            ['id' => $palierPrimaire, 'code' => 'PRIMAIRE', 'nom_fr' => 'Enseignement Primaire',    'nom_ar' => 'التعليم الابتدائي',  'ordre' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $palierMoyen,    'code' => 'MOYEN',    'nom_fr' => 'Enseignement Moyen (CEM)', 'nom_ar' => 'التعليم المتوسط',   'ordre' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $palierLycee,    'code' => 'LYCEE',    'nom_fr' => 'Enseignement Secondaire',  'nom_ar' => 'التعليم الثانوي',   'ordre' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // ── NIVEAUX ──
        $niveaux = [
            ['palier' => $palierPrimaire, 'code' => '1AP', 'fr' => '1ère Année Primaire',     'ar' => 'السنة الأولى ابتدائي',  'ordre' => 1],
            ['palier' => $palierPrimaire, 'code' => '2AP', 'fr' => '2ème Année Primaire',     'ar' => 'السنة الثانية ابتدائي', 'ordre' => 2],
            ['palier' => $palierPrimaire, 'code' => '3AP', 'fr' => '3ème Année Primaire',     'ar' => 'السنة الثالثة ابتدائي', 'ordre' => 3],
            ['palier' => $palierPrimaire, 'code' => '4AP', 'fr' => '4ème Année Primaire',     'ar' => 'السنة الرابعة ابتدائي', 'ordre' => 4],
            ['palier' => $palierPrimaire, 'code' => '5AP', 'fr' => '5ème Année Primaire',     'ar' => 'السنة الخامسة ابتدائي', 'ordre' => 5],
            ['palier' => $palierMoyen,    'code' => '1AM', 'fr' => '1ère Année Moyenne',      'ar' => 'السنة الأولى متوسط',   'ordre' => 6],
            ['palier' => $palierMoyen,    'code' => '2AM', 'fr' => '2ème Année Moyenne',      'ar' => 'السنة الثانية متوسط',  'ordre' => 7],
            ['palier' => $palierMoyen,    'code' => '3AM', 'fr' => '3ème Année Moyenne',      'ar' => 'السنة الثالثة متوسط',  'ordre' => 8],
            ['palier' => $palierMoyen,    'code' => '4AM', 'fr' => '4ème Année Moyenne (BEM)','ar' => 'السنة الرابعة متوسط',  'ordre' => 9],
            ['palier' => $palierLycee,    'code' => '1AS', 'fr' => '1ère Année Secondaire',   'ar' => 'السنة الأولى ثانوي',  'ordre' => 10],
            ['palier' => $palierLycee,    'code' => '2AS', 'fr' => '2ème Année Secondaire',   'ar' => 'السنة الثانية ثانوي', 'ordre' => 11],
            ['palier' => $palierLycee,    'code' => '3AS', 'fr' => '3ème Année Secondaire',   'ar' => 'السنة الثالثة ثانوي', 'ordre' => 12],
        ];

        $niveauxIds = [];
        foreach ($niveaux as $n) {
            $id = (string) Str::uuid();
            DB::table('niveaux_scolaires')->insert([
                'id' => $id, 'palier_id' => $n['palier'], 'code' => $n['code'],
                'nom_fr' => $n['fr'], 'nom_ar' => $n['ar'], 'ordre' => $n['ordre'],
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $niveauxIds[$n['code']] = $id;
        }

        // ── BRANCHES ──
        $branches = [
            ['code' => 'TC',   'fr' => 'Tronc Commun',            'ar' => 'جذع مشترك',        'niveaux' => ['1AS']],
            ['code' => 'SE',   'fr' => 'Sciences Expérimentales', 'ar' => 'علوم تجريبية',     'niveaux' => ['2AS','3AS']],
            ['code' => 'MATH', 'fr' => 'Mathématiques',           'ar' => 'رياضيات',           'niveaux' => ['2AS','3AS']],
            ['code' => 'TM',   'fr' => 'Technique-Mathématiques', 'ar' => 'تقني رياضي',        'niveaux' => ['2AS','3AS']],
            ['code' => 'LP',   'fr' => 'Lettres et Philosophie',  'ar' => 'آداب وفلسفة',      'niveaux' => ['2AS','3AS']],
            ['code' => 'GE',   'fr' => 'Gestion et Économie',     'ar' => 'تسيير واقتصاد',    'niveaux' => ['2AS','3AS']],
            ['code' => 'LE',   'fr' => 'Langues Étrangères',      'ar' => 'لغات أجنبية',      'niveaux' => ['2AS','3AS']],
        ];

        $branchesIds = [];
        foreach ($branches as $b) {
            $id = (string) Str::uuid();
            DB::table('branches')->insert([
                'id' => $id, 'code' => $b['code'], 'nom_fr' => $b['fr'], 'nom_ar' => $b['ar'],
                'niveaux_applicables' => json_encode($b['niveaux']),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $branchesIds[$b['code']] = $id;
        }

        // ── MATIÈRES ── helper
        $ins = function(string $niveau, ?string $branche, string $fr, string $ar, int $coeff, int $hh, bool $principale, bool $facultatif, int $ordre) use ($niveauxIds, $branchesIds) {
            DB::table('matieres_curriculum')->insert([
                'id' => (string) Str::uuid(),
                'niveau_id'   => $niveauxIds[$niveau],
                'branche_id'  => $branche ? $branchesIds[$branche] : null,
                'matiere_fr'  => $fr, 'matiere_ar' => $ar,
                'coefficient' => $coeff, 'volume_horaire_hebdo' => $hh,
                'est_principale' => $principale, 'est_facultatif' => $facultatif,
                'ordre' => $ordre, 'created_at' => now(), 'updated_at' => now(),
            ]);
        };

        // ── 1AP & 2AP ──
        foreach (['1AP','2AP'] as $n) {
            $ins($n,null,'Langue Arabe','اللغة العربية',3,10,true,false,1);
            $ins($n,null,'Mathématiques','الرياضيات',3,5,true,false,2);
            $ins($n,null,'Éducation Islamique','التربية الإسلامية',2,3,false,false,3);
            $ins($n,null,'Éveil / Sciences','التربية العلمية',1,2,false,false,4);
            $ins($n,null,'Éducation Artistique','التربية الفنية',1,1,false,false,5);
            $ins($n,null,'Éducation Physique','التربية البدنية',1,2,false,false,6);
        }

        // ── 3AP ──
        $ins('3AP',null,'Langue Arabe','اللغة العربية',3,8,true,false,1);
        $ins('3AP',null,'Mathématiques','الرياضيات',3,5,true,false,2);
        $ins('3AP',null,'Langue Française','اللغة الفرنسية',2,4,true,false,3);
        $ins('3AP',null,'Éducation Islamique','التربية الإسلامية',2,3,false,false,4);
        $ins('3AP',null,'Éveil / Sciences','التربية العلمية',1,2,false,false,5);
        $ins('3AP',null,'Éducation Artistique','التربية الفنية',1,1,false,false,6);
        $ins('3AP',null,'Éducation Physique','التربية البدنية',1,2,false,false,7);

        // ── 4AP ──
        $ins('4AP',null,'Langue Arabe','اللغة العربية',3,8,true,false,1);
        $ins('4AP',null,'Mathématiques','الرياضيات',3,5,true,false,2);
        $ins('4AP',null,'Langue Française','اللغة الفرنسية',2,4,true,false,3);
        $ins('4AP',null,'Éducation Islamique','التربية الإسلامية',2,3,false,false,4);
        $ins('4AP',null,'Histoire-Géographie','التاريخ والجغرافيا',1,2,false,false,5);
        $ins('4AP',null,'Sciences Naturelles','العلوم الطبيعية',1,2,false,false,6);
        $ins('4AP',null,'Éducation Artistique','التربية الفنية',1,1,false,false,7);
        $ins('4AP',null,'Éducation Physique','التربية البدنية',1,2,false,false,8);

        // ── 5AP (CFE) ──
        $ins('5AP',null,'Langue Arabe','اللغة العربية',3,8,true,false,1);
        $ins('5AP',null,'Mathématiques','الرياضيات',3,5,true,false,2);
        $ins('5AP',null,'Langue Française','اللغة الفرنسية',2,4,true,false,3);
        $ins('5AP',null,'Éducation Islamique','التربية الإسلامية',2,3,false,false,4);
        $ins('5AP',null,'Histoire-Géographie','التاريخ والجغرافيا',2,2,false,false,5);
        $ins('5AP',null,'Sciences Naturelles','العلوم الطبيعية',1,2,false,false,6);
        $ins('5AP',null,'Éducation Civique','التربية المدنية',1,1,false,false,7);
        $ins('5AP',null,'Éducation Physique','التربية البدنية',1,2,false,false,8);

        // ── MOYEN 1AM ──
        $ins('1AM',null,'Langue Arabe','اللغة العربية',2,5,true,false,1);
        $ins('1AM',null,'Mathématiques','الرياضيات',2,5,true,false,2);
        $ins('1AM',null,'Langue Française','اللغة الفرنسية',1,4,true,false,3);
        $ins('1AM',null,'Langue Anglaise','اللغة الإنجليزية',1,3,false,false,4);
        $ins('1AM',null,'Éducation Islamique','التربية الإسلامية',1,2,false,false,5);
        $ins('1AM',null,'Histoire','التاريخ',1,1,false,false,6);
        $ins('1AM',null,'Géographie','الجغرافيا',1,1,false,false,7);
        $ins('1AM',null,'Sciences Naturelles','العلوم الطبيعية',1,2,false,false,8);
        $ins('1AM',null,'Sciences Physiques','العلوم الفيزيائية',1,2,false,false,9);
        $ins('1AM',null,'Technologie','التكنولوجيا',1,2,false,false,10);
        $ins('1AM',null,'Éducation Physique','التربية البدنية',1,2,false,false,11);
        $ins('1AM',null,'Tamazight','الأمازيغية',1,2,false,false,12);

        // ── MOYEN 2AM ──
        $ins('2AM',null,'Langue Arabe','اللغة العربية',2,5,true,false,1);
        $ins('2AM',null,'Mathématiques','الرياضيات',2,5,true,false,2);
        $ins('2AM',null,'Langue Française','اللغة الفرنسية',2,4,true,false,3);
        $ins('2AM',null,'Langue Anglaise','اللغة الإنجليزية',1,3,false,false,4);
        $ins('2AM',null,'Éducation Islamique','التربية الإسلامية',1,2,false,false,5);
        $ins('2AM',null,'Histoire','التاريخ',1,1,false,false,6);
        $ins('2AM',null,'Géographie','الجغرافيا',1,1,false,false,7);
        $ins('2AM',null,'Sciences Naturelles','العلوم الطبيعية',1,2,false,false,8);
        $ins('2AM',null,'Sciences Physiques','العلوم الفيزيائية',1,2,false,false,9);
        $ins('2AM',null,'Technologie','التكنولوجيا',1,2,false,false,10);
        $ins('2AM',null,'Éducation Physique','التربية البدنية',1,2,false,false,11);
        $ins('2AM',null,'Tamazight','الأمازيغية',1,2,false,false,12);

        // ── MOYEN 3AM ──
        $ins('3AM',null,'Langue Arabe','اللغة العربية',3,5,true,false,1);
        $ins('3AM',null,'Mathématiques','الرياضيات',3,5,true,false,2);
        $ins('3AM',null,'Langue Française','اللغة الفرنسية',2,4,true,false,3);
        $ins('3AM',null,'Langue Anglaise','اللغة الإنجليزية',1,3,false,false,4);
        $ins('3AM',null,'Éducation Islamique','التربية الإسلامية',1,2,false,false,5);
        $ins('3AM',null,'Histoire','التاريخ',1,1,false,false,6);
        $ins('3AM',null,'Géographie','الجغرافيا',1,1,false,false,7);
        $ins('3AM',null,'Sciences Naturelles','العلوم الطبيعية',1,2,false,false,8);
        $ins('3AM',null,'Sciences Physiques','العلوم الفيزيائية',1,2,false,false,9);
        $ins('3AM',null,'Technologie','التكنولوجيا',1,2,false,false,10);
        $ins('3AM',null,'Éducation Physique','التربية البدنية',1,2,false,false,11);
        $ins('3AM',null,'Tamazight','الأمازيغية',1,2,false,false,12);

        // ── MOYEN 4AM (BEM) ──
        $ins('4AM',null,'Langue Arabe','اللغة العربية',4,5,true,false,1);
        $ins('4AM',null,'Mathématiques','الرياضيات',4,5,true,false,2);
        $ins('4AM',null,'Langue Française','اللغة الفرنسية',3,4,true,false,3);
        $ins('4AM',null,'Langue Anglaise','اللغة الإنجليزية',2,3,true,false,4);
        $ins('4AM',null,'Éducation Islamique','التربية الإسلامية',2,2,false,false,5);
        $ins('4AM',null,'Histoire','التاريخ',2,2,false,false,6);
        $ins('4AM',null,'Géographie','الجغرافيا',2,2,false,false,7);
        $ins('4AM',null,'Sciences Naturelles','العلوم الطبيعية',2,2,false,false,8);
        $ins('4AM',null,'Sciences Physiques','العلوم الفيزيائية',2,2,false,false,9);
        $ins('4AM',null,'Technologie','التكنولوجيا',2,2,false,false,10);
        $ins('4AM',null,'Éducation Physique','التربية البدنية',1,2,false,false,11);
        $ins('4AM',null,'Tamazight','الأمازيغية',1,2,false,false,12);

        // ── LYCÉE 1AS Tronc Commun ──
        $ins('1AS','TC','Mathématiques','الرياضيات',4,5,true,false,1);
        $ins('1AS','TC','Sciences Physiques','العلوم الفيزيائية',3,4,true,false,2);
        $ins('1AS','TC','Langue Arabe','اللغة العربية',3,4,true,false,3);
        $ins('1AS','TC','Sciences Naturelles','علوم الطبيعة والحياة',2,3,false,false,4);
        $ins('1AS','TC','Langue Française','اللغة الفرنسية',2,3,false,false,5);
        $ins('1AS','TC','Langue Anglaise','اللغة الإنجليزية',2,3,false,false,6);
        $ins('1AS','TC','Histoire-Géographie','التاريخ والجغرافيا',2,2,false,false,7);
        $ins('1AS','TC','Éducation Islamique','التربية الإسلامية',2,2,false,false,8);
        $ins('1AS','TC','Philosophie','الفلسفة',1,2,false,false,9);
        $ins('1AS','TC','Technologie','التكنولوجيا',1,2,false,false,10);
        $ins('1AS','TC','Éducation Physique','التربية البدنية',1,2,false,false,11);
        $ins('1AS','TC','Tamazight','الأمازيغية',1,2,false,true,12);

        // ── 3AS Sciences Expérimentales (BAC 2026 ONEC) ──
        $ins('3AS','SE','Sciences Naturelles','علوم الطبيعة والحياة',6,6,true,false,1);
        $ins('3AS','SE','Sciences Physiques','العلوم الفيزيائية',6,5,true,false,2);
        $ins('3AS','SE','Mathématiques','الرياضيات',4,4,true,false,3);
        $ins('3AS','SE','Langue Arabe','اللغة العربية',3,3,false,false,4);
        $ins('3AS','SE','Philosophie','الفلسفة',2,2,false,false,5);
        $ins('3AS','SE','Langue Française','اللغة الفرنسية',2,3,false,false,6);
        $ins('3AS','SE','Langue Anglaise','اللغة الإنجليزية',2,2,false,false,7);
        $ins('3AS','SE','Histoire-Géographie','التاريخ والجغرافيا',2,2,false,false,8);
        $ins('3AS','SE','Éducation Islamique','التربية الإسلامية',2,1,false,false,9);
        $ins('3AS','SE','Éducation Physique','التربية البدنية',1,2,false,false,10);
        $ins('3AS','SE','Tamazight','الأمازيغية',0,2,false,true,11);

        // ── 3AS Mathématiques ──
        $ins('3AS','MATH','Mathématiques','الرياضيات',7,7,true,false,1);
        $ins('3AS','MATH','Sciences Physiques','العلوم الفيزيائية',6,5,true,false,2);
        $ins('3AS','MATH','Sciences Naturelles','علوم الطبيعة والحياة',2,3,false,false,3);
        $ins('3AS','MATH','Langue Arabe','اللغة العربية',3,3,false,false,4);
        $ins('3AS','MATH','Philosophie','الفلسفة',2,2,false,false,5);
        $ins('3AS','MATH','Langue Française','اللغة الفرنسية',2,3,false,false,6);
        $ins('3AS','MATH','Langue Anglaise','اللغة الإنجليزية',2,2,false,false,7);
        $ins('3AS','MATH','Histoire-Géographie','التاريخ والجغرافيا',2,2,false,false,8);
        $ins('3AS','MATH','Éducation Islamique','التربية الإسلامية',2,1,false,false,9);
        $ins('3AS','MATH','Éducation Physique','التربية البدنية',1,2,false,false,10);

        // ── 3AS Technique-Mathématiques ──
        $ins('3AS','TM','Technologie','التكنولوجيا',7,7,true,false,1);
        $ins('3AS','TM','Mathématiques','الرياضيات',6,6,true,false,2);
        $ins('3AS','TM','Sciences Physiques','العلوم الفيزيائية',6,5,true,false,3);
        $ins('3AS','TM','Langue Arabe','اللغة العربية',2,2,false,false,4);
        $ins('3AS','TM','Philosophie','الفلسفة',2,2,false,false,5);
        $ins('3AS','TM','Langue Française','اللغة الفرنسية',2,2,false,false,6);
        $ins('3AS','TM','Langue Anglaise','اللغة الإنجليزية',2,2,false,false,7);
        $ins('3AS','TM','Éducation Islamique','التربية الإسلامية',2,1,false,false,8);
        $ins('3AS','TM','Histoire-Géographie','التاريخ والجغرافيا',2,2,false,false,9);
        $ins('3AS','TM','Éducation Physique','التربية البدنية',1,2,false,false,10);

        // ── 3AS Lettres et Philosophie ──
        $ins('3AS','LP','Langue Arabe','اللغة العربية',5,6,true,false,1);
        $ins('3AS','LP','Philosophie','الفلسفة',5,5,true,false,2);
        $ins('3AS','LP','Histoire-Géographie','التاريخ والجغرافيا',4,4,true,false,3);
        $ins('3AS','LP','Langue Française','اللغة الفرنسية',3,3,false,false,4);
        $ins('3AS','LP','Langue Anglaise','اللغة الإنجليزية',2,2,false,false,5);
        $ins('3AS','LP','Éducation Islamique','التربية الإسلامية',2,2,false,false,6);
        $ins('3AS','LP','Mathématiques','الرياضيات',2,2,false,false,7);
        $ins('3AS','LP','Éducation Physique','التربية البدنية',1,2,false,false,8);

        // ── 3AS Gestion et Économie ──
        $ins('3AS','GE','Comptabilité et Management','المحاسبة والتسيير',6,6,true,false,1);
        $ins('3AS','GE','Mathématiques','الرياضيات',5,4,true,false,2);
        $ins('3AS','GE','Économie','الاقتصاد',5,4,true,false,3);
        $ins('3AS','GE','Histoire-Géographie','التاريخ والجغرافيا',4,3,false,false,4);
        $ins('3AS','GE','Langue Arabe','اللغة العربية',3,3,false,false,5);
        $ins('3AS','GE','Philosophie','الفلسفة',2,2,false,false,6);
        $ins('3AS','GE','Langue Française','اللغة الفرنسية',2,2,false,false,7);
        $ins('3AS','GE','Langue Anglaise','اللغة الإنجليزية',2,2,false,false,8);
        $ins('3AS','GE','Éducation Islamique','التربية الإسلامية',2,1,false,false,9);
        $ins('3AS','GE','Droit','القانون',2,2,false,false,10);
        $ins('3AS','GE','Éducation Physique','التربية البدنية',1,2,false,false,11);

        // ── 3AS Langues Étrangères ──
        $ins('3AS','LE','Langue Française','اللغة الفرنسية',5,5,true,false,1);
        $ins('3AS','LE','Langue Anglaise','اللغة الإنجليزية',5,5,true,false,2);
        $ins('3AS','LE','Langue Arabe','اللغة العربية',4,4,true,false,3);
        $ins('3AS','LE','Traduction','الترجمة',3,3,true,false,4);
        $ins('3AS','LE','Philosophie','الفلسفة',2,2,false,false,5);
        $ins('3AS','LE','Histoire-Géographie','التاريخ والجغرافيا',2,2,false,false,6);
        $ins('3AS','LE','Éducation Islamique','التربية الإسلامية',2,1,false,false,7);
        $ins('3AS','LE','Mathématiques','الرياضيات',1,2,false,false,8);
        $ins('3AS','LE','Éducation Physique','التربية البدنية',1,2,false,false,9);
    }
}
```

### Étape 2c — Enregistrer le seeder dans DatabaseSeeder.php

Dans `database/seeders/DatabaseSeeder.php`, ajouter :
```php
$this->call(CurriculumAlgerienSeeder::class);
```

### Étape 2d — Lancer les migrations et le seeder
```bash
php artisan migrate
php artisan db:seed --class=CurriculumAlgerienSeeder
```

### Étape 2e — Écrire le test de validation

**Chemin :** `tests/Feature/Api/CurriculumTest.php`

```php
<?php
namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumTest extends TestCase
{
    use RefreshDatabase;

    public function test_curriculum_algerien_charge(): void
    {
        $this->artisan('db:seed', ['--class' => 'CurriculumAlgerienSeeder']);

        $this->assertDatabaseCount('paliers', 3);
        $this->assertDatabaseCount('branches', 7);
        // 12 niveaux de 1AP à 3AS
        $this->assertDatabaseCount('niveaux_scolaires', 12);
        // Vérifier 3AS SE
        $this->assertDatabaseHas('matieres_curriculum', [
            'matiere_fr'  => 'Sciences Naturelles',
            'coefficient' => 6,
        ]);
        // Vérifier 3AS MATH
        $this->assertDatabaseHas('matieres_curriculum', [
            'matiere_fr'  => 'Mathématiques',
            'coefficient' => 7,
        ]);
        // Vérifier BAC GE
        $this->assertDatabaseHas('matieres_curriculum', [
            'matiere_fr'  => 'Comptabilité et Management',
            'coefficient' => 6,
        ]);
    }

    public function test_niveaux_sont_dans_le_bon_ordre(): void
    {
        $this->artisan('db:seed', ['--class' => 'CurriculumAlgerienSeeder']);

        $niveaux = \DB::table('niveaux_scolaires')->orderBy('ordre')->pluck('code')->toArray();
        $expected = ['1AP','2AP','3AP','4AP','5AP','1AM','2AM','3AM','4AM','1AS','2AS','3AS'];
        $this->assertEquals($expected, $niveaux);
    }
}
```

---

## TÂCHE 3 — SMS PARENT AUTOMATIQUE (Absent non signalé)
**Priorité : HAUTE · Durée estimée : 2 jours**  
**C'est l'argument de vente N°1 pour convaincre un directeur de pilote.**

### Étape 3a — Migration

**Chemin :** `database/migrations/2026_06_29_200000_create_absences_journalieres_table.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('absences_journalieres', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('eleve_id');
            $table->date('date_absence');
            $table->enum('statut', ['present','absent','retard','demi_journee'])->default('absent');
            $table->time('heure_arrivee')->nullable();
            $table->enum('signale_par', ['admin','badge','parent','auto'])->default('auto');
            $table->boolean('sms_parent_envoye')->default(false);
            $table->timestamp('sms_envoye_at')->nullable();
            $table->string('motif')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
            $table->unique(['tenant_id','eleve_id','date_absence']);
        });

        Schema::create('justificatifs_absence', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('absence_id');
            $table->text('motif');
            $table->string('document_url', 500)->nullable();
            $table->enum('statut', ['en_attente','valide','refuse'])->default('en_attente');
            $table->uuid('valide_par')->nullable();
            $table->timestamp('valide_at')->nullable();
            $table->timestamps();

            $table->foreign('absence_id')->references('id')->on('absences_journalieres')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('justificatifs_absence');
        Schema::dropIfExists('absences_journalieres');
    }
};
```

### Étape 3b — Job de notification

**Chemin :** `app/Jobs/NotifierAbsenceParent.php`

```php
<?php
namespace App\Jobs;

use App\Models\AbsenceJournaliere;
use App\Models\Eleve;
use App\Services\Sms\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifierAbsenceParent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $absenceId
    ) {}

    public function handle(SmsService $sms): void
    {
        $absence = AbsenceJournaliere::with(['eleve.parents'])->find($this->absenceId);

        if (!$absence || $absence->sms_parent_envoye) return;

        $eleve   = $absence->eleve;
        $date    = $absence->date_absence->format('d/m/Y');
        $message = "EduGest DZ : Votre enfant {$eleve->prenom} {$eleve->nom} "
                 . "est absent(e) ce {$date}. Contactez l'établissement si cela est une erreur.";

        $sent = false;
        foreach ($eleve->parents as $parent) {
            if ($parent->telephone_1) {
                $sms->send($parent->telephone_1, $message);
                $sent = true;
            }
        }

        if ($sent) {
            $absence->update([
                'sms_parent_envoye' => true,
                'sms_envoye_at'     => now(),
            ]);
        }

        Log::info("AbsenceNotification: élève {$eleve->nom} {$eleve->prenom} — SMS " . ($sent ? 'envoyé' : 'non envoyé'));
    }
}
```

### Étape 3c — Command Artisan (planifiée à 8h chaque matin)

**Chemin :** `app/Console/Commands/VerifierAbsencesMatin.php`

```php
<?php
namespace App\Console\Commands;

use App\Jobs\NotifierAbsenceParent;
use App\Models\AbsenceJournaliere;
use App\Models\Eleve;
use App\Models\Tenant;
use Illuminate\Console\Command;

class VerifierAbsencesMatin extends Command
{
    protected $signature   = 'absences:verifier-matin';
    protected $description = 'Crée les absences du jour et notifie les parents des élèves non signalés';

    public function handle(): int
    {
        $today = today();
        $heureLimite = '08:30'; // Si pas de pointage avant 8h30 → absent

        Tenant::where('statut', 'actif')->each(function (Tenant $tenant) use ($today, $heureLimite) {
            config(['tenant.current_id' => $tenant->id]);

            // Élèves actifs de ce tenant
            $eleves = Eleve::actifs()->get();

            foreach ($eleves as $eleve) {
                // Si déjà une entrée aujourd'hui → ne rien faire
                $existe = AbsenceJournaliere::where('eleve_id', $eleve->id)
                    ->where('date_absence', $today)
                    ->exists();

                if ($existe) continue;

                // Créer l'absence (statut absent par défaut)
                $absence = AbsenceJournaliere::create([
                    'tenant_id'   => $tenant->id,
                    'eleve_id'    => $eleve->id,
                    'date_absence'=> $today,
                    'statut'      => 'absent',
                    'signale_par' => 'auto',
                ]);

                // Dispatcher le job SMS en queue
                NotifierAbsenceParent::dispatch($absence->id)->onQueue('notifications');
            }
        });

        $this->info('Vérification absences matin terminée : ' . $today->format('d/m/Y'));
        return self::SUCCESS;
    }
}
```

### Étape 3d — Enregistrer la commande dans le Scheduler

Dans `routes/console.php` ou `app/Console/Kernel.php` :
```php
Schedule::command('absences:verifier-matin')
    ->dailyAt('08:30')
    ->timezone('Africa/Algiers')
    ->withoutOverlapping();
```

### Étape 3e — Modèle AbsenceJournaliere

**Chemin :** `app/Models/AbsenceJournaliere.php`

```php
<?php
namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AbsenceJournaliere extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'absences_journalieres';

    protected $fillable = [
        'tenant_id','eleve_id','date_absence','statut',
        'heure_arrivee','signale_par','sms_parent_envoye',
        'sms_envoye_at','motif',
    ];

    protected $casts = [
        'date_absence'      => 'date',
        'sms_parent_envoye' => 'boolean',
        'sms_envoye_at'     => 'datetime',
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function justificatif(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(JustificatifAbsence::class, 'absence_id');
    }
}
```

---

## TÂCHE 4 — POINTAGE ENSEIGNANTS
**Priorité : HAUTE · Durée estimée : 2 jours**

### Migration

**Chemin :** `database/migrations/2026_06_29_300000_create_pointage_enseignants_table.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pointage_enseignants', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('enseignant_id');
            $table->date('date');
            $table->time('heure_arrivee')->nullable();
            $table->time('heure_depart')->nullable();
            $table->enum('methode', ['badge','qr','manuel'])->default('manuel');
            $table->string('badge_uid', 100)->nullable();
            $table->enum('statut', ['present','absent','retard','conge','maladie'])->default('present');
            $table->boolean('notif_eleves_envoye')->default(false);
            $table->boolean('impact_paie')->default(false);
            $table->decimal('retenue_dzd', 10, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('enseignant_id')->references('id')->on('enseignants')->onDelete('cascade');
            $table->unique(['tenant_id','enseignant_id','date']);
        });

        // Table des badges physiques (RFID/NFC) — commune à tous types de personnes
        Schema::create('badges', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->string('badge_uid', 100)->unique();
            $table->uuid('proprietaire_id');
            $table->enum('type_proprietaire', ['eleve','enseignant','personnel']);
            $table->boolean('actif')->default(true);
            $table->date('date_emission')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badges');
        Schema::dropIfExists('pointage_enseignants');
    }
};
```

### Route API badge RFID (à ajouter dans `routes/api.php`)

Dans le groupe protégé par `auth:api` :
```php
// ── Pointage par badge RFID/NFC ──
Route::prefix('pointage')->group(function () {
    Route::post('badge', [\App\Http\Controllers\Api\V1\PointageBadgeController::class, 'scan']);
    Route::get('enseignants/aujourd-hui', [\App\Http\Controllers\Api\V1\PointageEnseignantController::class, 'aujourdhui']);
    Route::post('enseignants/{id}/arrivee', [\App\Http\Controllers\Api\V1\PointageEnseignantController::class, 'arrivee']);
    Route::post('enseignants/{id}/depart', [\App\Http\Controllers\Api\V1\PointageEnseignantController::class, 'depart']);
    Route::get('enseignants/{id}/historique', [\App\Http\Controllers\Api\V1\PointageEnseignantController::class, 'historique']);
});
```

---

## TÂCHE 5 — README D'INSTALLATION
**Priorité : MOYENNE · Durée estimée : 2 heures**

**Chemin :** `README.md` (à la racine du repo)

```markdown
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
```

---

## ORDRE D'EXÉCUTION POUR DEEPSEEK

```
1. git checkout develop
2. Créer migration curriculum (Tâche 2a)
3. Créer CurriculumAlgerienSeeder (Tâche 2b)
4. Enregistrer seeder dans DatabaseSeeder (Tâche 2c)
5. Créer CurriculumTest (Tâche 2e)
6. Créer migration absences_journalieres (Tâche 3a)
7. Créer Job NotifierAbsenceParent (Tâche 3b)
8. Créer Command VerifierAbsencesMatin (Tâche 3c)
9. Enregistrer commande dans scheduler (Tâche 3d)
10. Créer Modèle AbsenceJournaliere (Tâche 3e)
11. Créer migration pointage_enseignants + badges (Tâche 4)
12. Ajouter routes pointage dans api.php (Tâche 4 routes)
13. Créer README.md (Tâche 5)
14. php artisan test --parallel → vérifier que les 192 tests passent encore
15. git add . && git commit -m "feat: curriculum DZ + absences journalières + pointage enseignants + README"
16. git push origin develop
17. Merger develop → main via PR GitHub
```

---

## VÉRIFICATION FINALE

Après exécution, le CI sur `develop` doit afficher :
- ✅ 192+ tests verts (les anciens + les nouveaux CurriculumTest)
- ✅ Coverage non bloquante (continue-on-error: true)
- ✅ Migrations sans erreur

Si un test échoue, corriger avant de merger dans `main`.
