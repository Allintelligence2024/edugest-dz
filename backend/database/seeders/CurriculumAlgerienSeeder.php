<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CurriculumAlgerienSeeder extends Seeder
{
    public function run(): void
    {
        $palierPrimaire  = (string) Str::uuid();
        $palierMoyen     = (string) Str::uuid();
        $palierLycee     = (string) Str::uuid();

        DB::table('paliers')->insert([
            ['id' => $palierPrimaire, 'code' => 'PRIMAIRE', 'nom_fr' => 'Enseignement Primaire',    'nom_ar' => 'التعليم الابتدائي',  'ordre' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $palierMoyen,    'code' => 'MOYEN',    'nom_fr' => 'Enseignement Moyen (CEM)', 'nom_ar' => 'التعليم المتوسط',   'ordre' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $palierLycee,    'code' => 'LYCEE',    'nom_fr' => 'Enseignement Secondaire',  'nom_ar' => 'التعليم الثانوي',   'ordre' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);

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

        foreach (['1AP','2AP'] as $n) {
            $ins($n,null,'Langue Arabe','اللغة العربية',3,10,true,false,1);
            $ins($n,null,'Mathématiques','الرياضيات',3,5,true,false,2);
            $ins($n,null,'Éducation Islamique','التربية الإسلامية',2,3,false,false,3);
            $ins($n,null,'Éveil / Sciences','التربية العلمية',1,2,false,false,4);
            $ins($n,null,'Éducation Artistique','التربية الفنية',1,1,false,false,5);
            $ins($n,null,'Éducation Physique','التربية البدنية',1,2,false,false,6);
        }

        $ins('3AP',null,'Langue Arabe','اللغة العربية',3,8,true,false,1);
        $ins('3AP',null,'Mathématiques','الرياضيات',3,5,true,false,2);
        $ins('3AP',null,'Langue Française','اللغة الفرنسية',2,4,true,false,3);
        $ins('3AP',null,'Éducation Islamique','التربية الإسلامية',2,3,false,false,4);
        $ins('3AP',null,'Éveil / Sciences','التربية العلمية',1,2,false,false,5);
        $ins('3AP',null,'Éducation Artistique','التربية الفنية',1,1,false,false,6);
        $ins('3AP',null,'Éducation Physique','التربية البدنية',1,2,false,false,7);

        $ins('4AP',null,'Langue Arabe','اللغة العربية',3,8,true,false,1);
        $ins('4AP',null,'Mathématiques','الرياضيات',3,5,true,false,2);
        $ins('4AP',null,'Langue Française','اللغة الفرنسية',2,4,true,false,3);
        $ins('4AP',null,'Éducation Islamique','التربية الإسلامية',2,3,false,false,4);
        $ins('4AP',null,'Histoire-Géographie','التاريخ والجغرافيا',1,2,false,false,5);
        $ins('4AP',null,'Sciences Naturelles','العلوم الطبيعية',1,2,false,false,6);
        $ins('4AP',null,'Éducation Artistique','التربية الفنية',1,1,false,false,7);
        $ins('4AP',null,'Éducation Physique','التربية البدنية',1,2,false,false,8);

        $ins('5AP',null,'Langue Arabe','اللغة العربية',3,8,true,false,1);
        $ins('5AP',null,'Mathématiques','الرياضيات',3,5,true,false,2);
        $ins('5AP',null,'Langue Française','اللغة الفرنسية',2,4,true,false,3);
        $ins('5AP',null,'Éducation Islamique','التربية الإسلامية',2,3,false,false,4);
        $ins('5AP',null,'Histoire-Géographie','التاريخ والجغرافيا',2,2,false,false,5);
        $ins('5AP',null,'Sciences Naturelles','العلوم الطبيعية',1,2,false,false,6);
        $ins('5AP',null,'Éducation Civique','التربية المدنية',1,1,false,false,7);
        $ins('5AP',null,'Éducation Physique','التربية البدنية',1,2,false,false,8);

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

        $ins('3AS','LP','Langue Arabe','اللغة العربية',5,6,true,false,1);
        $ins('3AS','LP','Philosophie','الفلسفة',5,5,true,false,2);
        $ins('3AS','LP','Histoire-Géographie','التاريخ والجغرافيا',4,4,true,false,3);
        $ins('3AS','LP','Langue Française','اللغة الفرنسية',3,3,false,false,4);
        $ins('3AS','LP','Langue Anglaise','اللغة الإنجليزية',2,2,false,false,5);
        $ins('3AS','LP','Éducation Islamique','التربية الإسلامية',2,2,false,false,6);
        $ins('3AS','LP','Mathématiques','الرياضيات',2,2,false,false,7);
        $ins('3AS','LP','Éducation Physique','التربية البدنية',1,2,false,false,8);

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
