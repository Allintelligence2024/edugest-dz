-- ============================================================
-- EduGest DZ — Base de données du cursus scolaire algérien
-- Matières + Coefficients + Branches · 1ère AP → 3ème AS
-- Source : MEN Algérie, ONEC, El Watan 2017, JoliMatin BAC 2026
-- ============================================================
-- ⚠️  AVERTISSEMENT : Les coefficients du primaire (1AP→5AP)
--     ne sont pas publiés officiellement par le MEN.
--     Les valeurs ci-dessous sont basées sur les pratiques
--     courantes des établissements privés algériens.
--     À faire valider par un directeur pédagogique avant prod.
-- ============================================================

-- ─────────────────────────────────────────
-- PALIERS
-- ─────────────────────────────────────────
INSERT INTO paliers (id, code, nom_fr, nom_ar, ordre) VALUES
  ('pal-01', 'PRIMAIRE', 'Enseignement Primaire',    'التعليم الابتدائي',   1),
  ('pal-02', 'MOYEN',    'Enseignement Moyen (CEM)', 'التعليم المتوسط',    2),
  ('pal-03', 'LYCEE',    'Enseignement Secondaire',  'التعليم الثانوي',    3);

-- ─────────────────────────────────────────
-- NIVEAUX SCOLAIRES
-- ─────────────────────────────────────────
INSERT INTO niveaux (id, palier_id, code, nom_fr, nom_ar, ordre) VALUES
  -- Primaire
  ('niv-01', 'pal-01', '1AP', '1ère Année Primaire',     'السنة الأولى ابتدائي',  1),
  ('niv-02', 'pal-01', '2AP', '2ème Année Primaire',     'السنة الثانية ابتدائي', 2),
  ('niv-03', 'pal-01', '3AP', '3ème Année Primaire',     'السنة الثالثة ابتدائي', 3),
  ('niv-04', 'pal-01', '4AP', '4ème Année Primaire',     'السنة الرابعة ابتدائي', 4),
  ('niv-05', 'pal-01', '5AP', '5ème Année Primaire',     'السنة الخامسة ابتدائي', 5),
  -- Moyen
  ('niv-06', 'pal-02', '1AM', '1ère Année Moyenne',      'السنة الأولى متوسط',   6),
  ('niv-07', 'pal-02', '2AM', '2ème Année Moyenne',      'السنة الثانية متوسط',  7),
  ('niv-08', 'pal-02', '3AM', '3ème Année Moyenne',      'السنة الثالثة متوسط',  8),
  ('niv-09', 'pal-02', '4AM', '4ème Année Moyenne (BEM)','السنة الرابعة متوسط',  9),
  -- Lycée tronc commun
  ('niv-10', 'pal-03', '1AS', '1ère Année Secondaire',   'السنة الأولى ثانوي',  10),
  -- Lycée 2ème et 3ème AS par branche
  ('niv-11', 'pal-03', '2AS', '2ème Année Secondaire',   'السنة الثانية ثانوي', 11),
  ('niv-12', 'pal-03', '3AS', '3ème Année Secondaire',   'السنة الثالثة ثانوي', 12);

-- ─────────────────────────────────────────
-- BRANCHES (FILIÈRES) — Lycée uniquement
-- ─────────────────────────────────────────
INSERT INTO branches (id, code, nom_fr, nom_ar, niveaux_applicables) VALUES
  ('bra-00', 'TC',    'Tronc Commun',             'جذع مشترك',                    '["1AS"]'),
  ('bra-01', 'SE',    'Sciences Expérimentales',  'علوم تجريبية',                 '["2AS","3AS"]'),
  ('bra-02', 'MATH',  'Mathématiques',            'رياضيات',                       '["2AS","3AS"]'),
  ('bra-03', 'TM',    'Technique-Mathématiques',  'تقني رياضي',                   '["2AS","3AS"]'),
  ('bra-04', 'LP',    'Lettres et Philosophie',   'آداب وفلسفة',                  '["2AS","3AS"]'),
  ('bra-05', 'GE',    'Gestion et Économie',      'تسيير واقتصاد',                '["2AS","3AS"]'),
  ('bra-06', 'LE',    'Langues Étrangères',       'لغات أجنبية',                  '["2AS","3AS"]'),
  ('bra-07', 'ART',   'Arts',                     'فنون',                          '["2AS","3AS"]');

-- ============================================================
-- MATIÈRES PAR PALIER + COEFFICIENTS
-- ============================================================

-- ─────────────────────────────────────────
-- 1. PRIMAIRE — 1ère Année (1AP)
-- ─────────────────────────────────────────
INSERT INTO matieres_niveau (id, niveau_id, branche_id, matiere_fr, matiere_ar, coefficient, volume_horaire_hebdo, est_principale, ordre) VALUES
  ('m01-01', 'niv-01', NULL, 'Langue Arabe',           'اللغة العربية',      3, 10, true,  1),
  ('m01-02', 'niv-01', NULL, 'Mathématiques',          'الرياضيات',          3,  5, true,  2),
  ('m01-03', 'niv-01', NULL, 'Éducation Islamique',    'التربية الإسلامية',  2,  3, false, 3),
  ('m01-04', 'niv-01', NULL, 'Éveil (Sciences)',        'التربية العلمية',    1,  2, false, 4),
  ('m01-05', 'niv-01', NULL, 'Éducation Artistique',   'التربية الفنية',     1,  1, false, 5),
  ('m01-06', 'niv-01', NULL, 'Éducation Physique',     'التربية البدنية',    1,  2, false, 6);

-- ─────────────────────────────────────────
-- 2. PRIMAIRE — 2ème Année (2AP)
-- ─────────────────────────────────────────
INSERT INTO matieres_niveau (id, niveau_id, branche_id, matiere_fr, matiere_ar, coefficient, volume_horaire_hebdo, est_principale, ordre) VALUES
  ('m02-01', 'niv-02', NULL, 'Langue Arabe',           'اللغة العربية',      3, 10, true,  1),
  ('m02-02', 'niv-02', NULL, 'Mathématiques',          'الرياضيات',          3,  5, true,  2),
  ('m02-03', 'niv-02', NULL, 'Éducation Islamique',    'التربية الإسلامية',  2,  3, false, 3),
  ('m02-04', 'niv-02', NULL, 'Éveil (Sciences)',        'التربية العلمية',    1,  2, false, 4),
  ('m02-05', 'niv-02', NULL, 'Éducation Artistique',   'التربية الفنية',     1,  1, false, 5),
  ('m02-06', 'niv-02', NULL, 'Éducation Physique',     'التربية البدنية',    1,  2, false, 6);

-- ─────────────────────────────────────────
-- 3. PRIMAIRE — 3ème Année (3AP)
-- ─────────────────────────────────────────
INSERT INTO matieres_niveau (id, niveau_id, branche_id, matiere_fr, matiere_ar, coefficient, volume_horaire_hebdo, est_principale, ordre) VALUES
  ('m03-01', 'niv-03', NULL, 'Langue Arabe',           'اللغة العربية',      3,  8, true,  1),
  ('m03-02', 'niv-03', NULL, 'Mathématiques',          'الرياضيات',          3,  5, true,  2),
  ('m03-03', 'niv-03', NULL, 'Langue Française',       'اللغة الفرنسية',     2,  4, true,  3),
  ('m03-04', 'niv-03', NULL, 'Éducation Islamique',    'التربية الإسلامية',  2,  3, false, 4),
  ('m03-05', 'niv-03', NULL, 'Éveil (Sciences)',        'التربية العلمية',    1,  2, false, 5),
  ('m03-06', 'niv-03', NULL, 'Éducation Artistique',   'التربية الفنية',     1,  1, false, 6),
  ('m03-07', 'niv-03', NULL, 'Éducation Physique',     'التربية البدنية',    1,  2, false, 7);

-- ─────────────────────────────────────────
-- 4. PRIMAIRE — 4ème Année (4AP)
-- ─────────────────────────────────────────
INSERT INTO matieres_niveau (id, niveau_id, branche_id, matiere_fr, matiere_ar, coefficient, volume_horaire_hebdo, est_principale, ordre) VALUES
  ('m04-01', 'niv-04', NULL, 'Langue Arabe',           'اللغة العربية',      3,  8, true,  1),
  ('m04-02', 'niv-04', NULL, 'Mathématiques',          'الرياضيات',          3,  5, true,  2),
  ('m04-03', 'niv-04', NULL, 'Langue Française',       'اللغة الفرنسية',     2,  4, true,  3),
  ('m04-04', 'niv-04', NULL, 'Éducation Islamique',    'التربية الإسلامية',  2,  3, false, 4),
  ('m04-05', 'niv-04', NULL, 'Histoire-Géographie',    'التاريخ والجغرافيا', 1,  2, false, 5),
  ('m04-06', 'niv-04', NULL, 'Sciences Naturelles',    'العلوم الطبيعية',    1,  2, false, 6),
  ('m04-07', 'niv-04', NULL, 'Éducation Artistique',   'التربية الفنية',     1,  1, false, 7),
  ('m04-08', 'niv-04', NULL, 'Éducation Physique',     'التربية البدنية',    1,  2, false, 8);

-- ─────────────────────────────────────────
-- 5. PRIMAIRE — 5ème Année (5AP) — Examen CFE
-- ─────────────────────────────────────────
INSERT INTO matieres_niveau (id, niveau_id, branche_id, matiere_fr, matiere_ar, coefficient, volume_horaire_hebdo, est_principale, ordre) VALUES
  ('m05-01', 'niv-05', NULL, 'Langue Arabe',           'اللغة العربية',      3,  8, true,  1),
  ('m05-02', 'niv-05', NULL, 'Mathématiques',          'الرياضيات',          3,  5, true,  2),
  ('m05-03', 'niv-05', NULL, 'Langue Française',       'اللغة الفرنسية',     2,  4, true,  3),
  ('m05-04', 'niv-05', NULL, 'Éducation Islamique',    'التربية الإسلامية',  2,  3, false, 4),
  ('m05-05', 'niv-05', NULL, 'Histoire-Géographie',    'التاريخ والجغرافيا', 2,  2, false, 5),
  ('m05-06', 'niv-05', NULL, 'Sciences Naturelles',    'العلوم الطبيعية',    1,  2, false, 6),
  ('m05-07', 'niv-05', NULL, 'Éducation Civique',      'التربية المدنية',    1,  1, false, 7),
  ('m05-08', 'niv-05', NULL, 'Éducation Artistique',   'التربية الفنية',     1,  1, false, 8),
  ('m05-09', 'niv-05', NULL, 'Éducation Physique',     'التربية البدنية',    1,  2, false, 9);

-- ============================================================
-- MOYEN (CEM)
-- Source : MEN correspondance 1547 / sept. 2017 (El Watan)
-- ============================================================

-- ─────────────────────────────────────────
-- 6. MOYEN — 1ère Année Moyenne (1AM)
-- ─────────────────────────────────────────
INSERT INTO matieres_niveau (id, niveau_id, branche_id, matiere_fr, matiere_ar, coefficient, volume_horaire_hebdo, est_principale, ordre) VALUES
  ('m06-01', 'niv-06', NULL, 'Langue Arabe',           'اللغة العربية',      2,  5, true,  1),
  ('m06-02', 'niv-06', NULL, 'Mathématiques',          'الرياضيات',          2,  5, true,  2),
  ('m06-03', 'niv-06', NULL, 'Langue Française',       'اللغة الفرنسية',     1,  4, true,  3),
  ('m06-04', 'niv-06', NULL, 'Langue Anglaise',        'اللغة الإنجليزية',   1,  3, false, 4),
  ('m06-05', 'niv-06', NULL, 'Éducation Islamique',    'التربية الإسلامية',  1,  2, false, 5),
  ('m06-06', 'niv-06', NULL, 'Histoire',               'التاريخ',             1,  1, false, 6),
  ('m06-07', 'niv-06', NULL, 'Géographie',             'الجغرافيا',           1,  1, false, 7),
  ('m06-08', 'niv-06', NULL, 'Sciences Naturelles',    'العلوم الطبيعية',    1,  2, false, 8),
  ('m06-09', 'niv-06', NULL, 'Sciences Physiques',     'العلوم الفيزيائية',  1,  2, false, 9),
  ('m06-10', 'niv-06', NULL, 'Technologie',            'التكنولوجيا',         1,  2, false, 10),
  ('m06-11', 'niv-06', NULL, 'Éducation Civique',      'التربية المدنية',    1,  1, false, 11),
  ('m06-12', 'niv-06', NULL, 'Éducation Artistique',   'التربية الفنية',     1,  1, false, 12),
  ('m06-13', 'niv-06', NULL, 'Éducation Physique',     'التربية البدنية',    1,  2, false, 13),
  ('m06-14', 'niv-06', NULL, 'Tamazight',              'الأمازيغية',          1,  2, false, 14);

-- ─────────────────────────────────────────
-- 7. MOYEN — 2ème Année Moyenne (2AM)
-- ─────────────────────────────────────────
INSERT INTO matieres_niveau (id, niveau_id, branche_id, matiere_fr, matiere_ar, coefficient, volume_horaire_hebdo, est_principale, ordre) VALUES
  ('m07-01', 'niv-07', NULL, 'Langue Arabe',           'اللغة العربية',      2,  5, true,  1),
  ('m07-02', 'niv-07', NULL, 'Mathématiques',          'الرياضيات',          2,  5, true,  2),
  ('m07-03', 'niv-07', NULL, 'Langue Française',       'اللغة الفرنسية',     2,  4, true,  3),
  ('m07-04', 'niv-07', NULL, 'Langue Anglaise',        'اللغة الإنجليزية',   1,  3, false, 4),
  ('m07-05', 'niv-07', NULL, 'Éducation Islamique',    'التربية الإسلامية',  1,  2, false, 5),
  ('m07-06', 'niv-07', NULL, 'Histoire',               'التاريخ',             1,  1, false, 6),
  ('m07-07', 'niv-07', NULL, 'Géographie',             'الجغرافيا',           1,  1, false, 7),
  ('m07-08', 'niv-07', NULL, 'Sciences Naturelles',    'العلوم الطبيعية',    1,  2, false, 8),
  ('m07-09', 'niv-07', NULL, 'Sciences Physiques',     'العلوم الفيزيائية',  1,  2, false, 9),
  ('m07-10', 'niv-07', NULL, 'Technologie',            'التكنولوجيا',         1,  2, false, 10),
  ('m07-11', 'niv-07', NULL, 'Éducation Civique',      'التربية المدنية',    1,  1, false, 11),
  ('m07-12', 'niv-07', NULL, 'Éducation Physique',     'التربية البدنية',    1,  2, false, 12),
  ('m07-13', 'niv-07', NULL, 'Tamazight',              'الأمازيغية',          1,  2, false, 13);

-- ─────────────────────────────────────────
-- 8. MOYEN — 3ème Année Moyenne (3AM)
-- ─────────────────────────────────────────
INSERT INTO matieres_niveau (id, niveau_id, branche_id, matiere_fr, matiere_ar, coefficient, volume_horaire_hebdo, est_principale, ordre) VALUES
  ('m08-01', 'niv-08', NULL, 'Langue Arabe',           'اللغة العربية',      3,  5, true,  1),
  ('m08-02', 'niv-08', NULL, 'Mathématiques',          'الرياضيات',          3,  5, true,  2),
  ('m08-03', 'niv-08', NULL, 'Langue Française',       'اللغة الفرنسية',     2,  4, true,  3),
  ('m08-04', 'niv-08', NULL, 'Langue Anglaise',        'اللغة الإنجليزية',   1,  3, false, 4),
  ('m08-05', 'niv-08', NULL, 'Éducation Islamique',    'التربية الإسلامية',  1,  2, false, 5),
  ('m08-06', 'niv-08', NULL, 'Histoire',               'التاريخ',             1,  1, false, 6),
  ('m08-07', 'niv-08', NULL, 'Géographie',             'الجغرافيا',           1,  1, false, 7),
  ('m08-08', 'niv-08', NULL, 'Sciences Naturelles',    'العلوم الطبيعية',    1,  2, false, 8),
  ('m08-09', 'niv-08', NULL, 'Sciences Physiques',     'العلوم الفيزيائية',  1,  2, false, 9),
  ('m08-10', 'niv-08', NULL, 'Technologie',            'التكنولوجيا',         1,  2, false, 10),
  ('m08-11', 'niv-08', NULL, 'Éducation Civique',      'التربية المدنية',    1,  1, false, 11),
  ('m08-12', 'niv-08', NULL, 'Éducation Physique',     'التربية البدنية',    1,  2, false, 12),
  ('m08-13', 'niv-08', NULL, 'Tamazight',              'الأمازيغية',          1,  2, false, 13);

-- ─────────────────────────────────────────
-- 9. MOYEN — 4ème Année Moyenne (4AM) — Examen BEM
-- ─────────────────────────────────────────
INSERT INTO matieres_niveau (id, niveau_id, branche_id, matiere_fr, matiere_ar, coefficient, volume_horaire_hebdo, est_principale, ordre) VALUES
  ('m09-01', 'niv-09', NULL, 'Langue Arabe',           'اللغة العربية',      4,  5, true,  1),
  ('m09-02', 'niv-09', NULL, 'Mathématiques',          'الرياضيات',          4,  5, true,  2),
  ('m09-03', 'niv-09', NULL, 'Langue Française',       'اللغة الفرنسية',     3,  4, true,  3),
  ('m09-04', 'niv-09', NULL, 'Langue Anglaise',        'اللغة الإنجليزية',   2,  3, true,  4),
  ('m09-05', 'niv-09', NULL, 'Éducation Islamique',    'التربية الإسلامية',  2,  2, false, 5),
  ('m09-06', 'niv-09', NULL, 'Histoire',               'التاريخ',             2,  2, false, 6),
  ('m09-07', 'niv-09', NULL, 'Géographie',             'الجغرافيا',           2,  2, false, 7),
  ('m09-08', 'niv-09', NULL, 'Sciences Naturelles',    'العلوم الطبيعية',    2,  2, false, 8),
  ('m09-09', 'niv-09', NULL, 'Sciences Physiques',     'العلوم الفيزيائية',  2,  2, false, 9),
  ('m09-10', 'niv-09', NULL, 'Technologie',            'التكنولوجيا',         2,  2, false, 10),
  ('m09-11', 'niv-09', NULL, 'Éducation Physique',     'التربية البدنية',    1,  2, false, 11),
  ('m09-12', 'niv-09', NULL, 'Tamazight',              'الأمازيغية',          1,  2, false, 12);

-- ============================================================
-- LYCÉE — 1ère AS (TRONC COMMUN)
-- ============================================================
INSERT INTO matieres_niveau (id, niveau_id, branche_id, matiere_fr, matiere_ar, coefficient, volume_horaire_hebdo, est_principale, ordre) VALUES
  ('m10-01', 'niv-10', 'bra-00', 'Langue Arabe',           'اللغة العربية',      3,  4, true,  1),
  ('m10-02', 'niv-10', 'bra-00', 'Mathématiques',          'الرياضيات',          4,  5, true,  2),
  ('m10-03', 'niv-10', 'bra-00', 'Sciences Physiques',     'العلوم الفيزيائية',  3,  4, true,  3),
  ('m10-04', 'niv-10', 'bra-00', 'Sciences Naturelles',    'العلوم الطبيعية',    2,  3, false, 4),
  ('m10-05', 'niv-10', 'bra-00', 'Langue Française',       'اللغة الفرنسية',     2,  3, false, 5),
  ('m10-06', 'niv-10', 'bra-00', 'Langue Anglaise',        'اللغة الإنجليزية',   2,  3, false, 6),
  ('m10-07', 'niv-10', 'bra-00', 'Histoire-Géographie',    'التاريخ والجغرافيا', 2,  2, false, 7),
  ('m10-08', 'niv-10', 'bra-00', 'Éducation Islamique',    'التربية الإسلامية',  2,  2, false, 8),
  ('m10-09', 'niv-10', 'bra-00', 'Philosophie',            'الفلسفة',             1,  2, false, 9),
  ('m10-10', 'niv-10', 'bra-00', 'Éducation Physique',     'التربية البدنية',    1,  2, false, 10),
  ('m10-11', 'niv-10', 'bra-00', 'Technologie',            'التكنولوجيا',         1,  2, false, 11),
  ('m10-12', 'niv-10', 'bra-00', 'Informatique',           'الإعلام الآلي',       1,  1, false, 12),
  ('m10-13', 'niv-10', 'bra-00', 'Tamazight',              'الأمازيغية',          1,  2, false, 13);

-- ============================================================
-- LYCÉE — 2ème & 3ème AS · Sciences Expérimentales (SE)
-- ============================================================
INSERT INTO matieres_niveau (id, niveau_id, branche_id, matiere_fr, matiere_ar, coefficient, volume_horaire_hebdo, est_principale, ordre) VALUES
  -- 2AS SE
  ('m11-01', 'niv-11', 'bra-01', 'Mathématiques',          'الرياضيات',          5,  5, true,  1),
  ('m11-02', 'niv-11', 'bra-01', 'Sciences Physiques',     'العلوم الفيزيائية',  5,  5, true,  2),
  ('m11-03', 'niv-11', 'bra-01', 'Sciences Naturelles',    'علوم الطبيعة والحياة',4, 4, true,  3),
  ('m11-04', 'niv-11', 'bra-01', 'Langue Arabe',           'اللغة العربية',      3,  3, false, 4),
  ('m11-05', 'niv-11', 'bra-01', 'Langue Française',       'اللغة الفرنسية',     2,  3, false, 5),
  ('m11-06', 'niv-11', 'bra-01', 'Langue Anglaise',        'اللغة الإنجليزية',   2,  2, false, 6),
  ('m11-07', 'niv-11', 'bra-01', 'Philosophie',            'الفلسفة',             2,  2, false, 7),
  ('m11-08', 'niv-11', 'bra-01', 'Histoire-Géographie',    'التاريخ والجغرافيا', 2,  2, false, 8),
  ('m11-09', 'niv-11', 'bra-01', 'Éducation Islamique',    'التربية الإسلامية',  2,  1, false, 9),
  ('m11-10', 'niv-11', 'bra-01', 'Éducation Physique',     'التربية البدنية',    1,  2, false, 10),
  ('m11-11', 'niv-11', 'bra-01', 'Tamazight',              'الأمازيغية',          1,  2, false, 11),
  -- 3AS SE (BAC)
  ('m12-01', 'niv-12', 'bra-01', 'Sciences Naturelles',    'علوم الطبيعة والحياة',6, 6, true,  1),
  ('m12-02', 'niv-12', 'bra-01', 'Sciences Physiques',     'العلوم الفيزيائية',  6,  5, true,  2),
  ('m12-03', 'niv-12', 'bra-01', 'Mathématiques',          'الرياضيات',          4,  4, true,  3),
  ('m12-04', 'niv-12', 'bra-01', 'Langue Arabe',           'اللغة العربية',      3,  3, false, 4),
  ('m12-05', 'niv-12', 'bra-01', 'Philosophie',            'الفلسفة',             2,  2, false, 5),
  ('m12-06', 'niv-12', 'bra-01', 'Langue Française',       'اللغة الفرنسية',     2,  3, false, 6),
  ('m12-07', 'niv-12', 'bra-01', 'Langue Anglaise',        'اللغة الإنجليزية',   2,  2, false, 7),
  ('m12-08', 'niv-12', 'bra-01', 'Histoire-Géographie',    'التاريخ والجغرافيا', 2,  2, false, 8),
  ('m12-09', 'niv-12', 'bra-01', 'Éducation Islamique',    'التربية الإسلامية',  2,  1, false, 9),
  ('m12-10', 'niv-12', 'bra-01', 'Éducation Physique',     'التربية البدنية',    1,  2, false, 10),
  ('m12-11', 'niv-12', 'bra-01', 'Tamazight',              'الأمازيغية',          0,  2, false, 11); -- facultatif

-- ============================================================
-- LYCÉE — 2ème & 3ème AS · Mathématiques (MATH)
-- ============================================================
INSERT INTO matieres_niveau (id, niveau_id, branche_id, matiere_fr, matiere_ar, coefficient, volume_horaire_hebdo, est_principale, ordre) VALUES
  -- 2AS MATH
  ('m13-01', 'niv-11', 'bra-02', 'Mathématiques',          'الرياضيات',          6,  7, true,  1),
  ('m13-02', 'niv-11', 'bra-02', 'Sciences Physiques',     'العلوم الفيزيائية',  5,  5, true,  2),
  ('m13-03', 'niv-11', 'bra-02', 'Sciences Naturelles',    'علوم الطبيعة والحياة',2, 3, false, 3),
  ('m13-04', 'niv-11', 'bra-02', 'Langue Arabe',           'اللغة العربية',      3,  3, false, 4),
  ('m13-05', 'niv-11', 'bra-02', 'Langue Française',       'اللغة الفرنسية',     2,  3, false, 5),
  ('m13-06', 'niv-11', 'bra-02', 'Langue Anglaise',        'اللغة الإنجليزية',   2,  2, false, 6),
  ('m13-07', 'niv-11', 'bra-02', 'Philosophie',            'الفلسفة',             2,  2, false, 7),
  ('m13-08', 'niv-11', 'bra-02', 'Histoire-Géographie',    'التاريخ والجغرافيا', 2,  2, false, 8),
  ('m13-09', 'niv-11', 'bra-02', 'Éducation Islamique',    'التربية الإسلامية',  2,  1, false, 9),
  ('m13-10', 'niv-11', 'bra-02', 'Éducation Physique',     'التربية البدنية',    1,  2, false, 10),
  -- 3AS MATH (BAC)
  ('m14-01', 'niv-12', 'bra-02', 'Mathématiques',          'الرياضيات',          7,  7, true,  1),
  ('m14-02', 'niv-12', 'bra-02', 'Sciences Physiques',     'العلوم الفيزيائية',  6,  5, true,  2),
  ('m14-03', 'niv-12', 'bra-02', 'Sciences Naturelles',    'علوم الطبيعة والحياة',2, 3, false, 3),
  ('m14-04', 'niv-12', 'bra-02', 'Langue Arabe',           'اللغة العربية',      3,  3, false, 4),
  ('m14-05', 'niv-12', 'bra-02', 'Philosophie',            'الفلسفة',             2,  2, false, 5),
  ('m14-06', 'niv-12', 'bra-02', 'Langue Française',       'اللغة الفرنسية',     2,  3, false, 6),
  ('m14-07', 'niv-12', 'bra-02', 'Langue Anglaise',        'اللغة الإنجليزية',   2,  2, false, 7),
  ('m14-08', 'niv-12', 'bra-02', 'Histoire-Géographie',    'التاريخ والجغرافيا', 2,  2, false, 8),
  ('m14-09', 'niv-12', 'bra-02', 'Éducation Islamique',    'التربية الإسلامية',  2,  1, false, 9),
  ('m14-10', 'niv-12', 'bra-02', 'Éducation Physique',     'التربية البدنية',    1,  2, false, 10),
  ('m14-11', 'niv-12', 'bra-02', 'Tamazight',              'الأمازيغية',          0,  2, false, 11);

-- ============================================================
-- LYCÉE — 2ème & 3ème AS · Technique-Mathématiques (TM)
-- ============================================================
INSERT INTO matieres_niveau (id, niveau_id, branche_id, matiere_fr, matiere_ar, coefficient, volume_horaire_hebdo, est_principale, ordre) VALUES
  ('m15-01', 'niv-12', 'bra-03', 'Technologie',            'التكنولوجيا',         7,  7, true,  1),
  ('m15-02', 'niv-12', 'bra-03', 'Mathématiques',          'الرياضيات',           6,  6, true,  2),
  ('m15-03', 'niv-12', 'bra-03', 'Sciences Physiques',     'العلوم الفيزيائية',   6,  5, true,  3),
  ('m15-04', 'niv-12', 'bra-03', 'Langue Arabe',           'اللغة العربية',       2,  2, false, 4),
  ('m15-05', 'niv-12', 'bra-03', 'Philosophie',            'الفلسفة',              2,  2, false, 5),
  ('m15-06', 'niv-12', 'bra-03', 'Langue Française',       'اللغة الفرنسية',      2,  2, false, 6),
  ('m15-07', 'niv-12', 'bra-03', 'Langue Anglaise',        'اللغة الإنجليزية',    2,  2, false, 7),
  ('m15-08', 'niv-12', 'bra-03', 'Éducation Islamique',    'التربية الإسلامية',   2,  1, false, 8),
  ('m15-09', 'niv-12', 'bra-03', 'Histoire-Géographie',    'التاريخ والجغرافيا',  2,  2, false, 9),
  ('m15-10', 'niv-12', 'bra-03', 'Éducation Physique',     'التربية البدنية',     1,  2, false, 10),
  ('m15-11', 'niv-12', 'bra-03', 'Tamazight',              'الأمازيغية',           0,  2, false, 11);

-- ============================================================
-- LYCÉE — 2ème & 3ème AS · Lettres et Philosophie (LP)
-- ============================================================
INSERT INTO matieres_niveau (id, niveau_id, branche_id, matiere_fr, matiere_ar, coefficient, volume_horaire_hebdo, est_principale, ordre) VALUES
  ('m16-01', 'niv-12', 'bra-04', 'Langue Arabe',           'اللغة العربية',      5,  6, true,  1),
  ('m16-02', 'niv-12', 'bra-04', 'Philosophie',            'الفلسفة',             5,  5, true,  2),
  ('m16-03', 'niv-12', 'bra-04', 'Histoire-Géographie',    'التاريخ والجغرافيا', 4,  4, true,  3),
  ('m16-04', 'niv-12', 'bra-04', 'Langue Française',       'اللغة الفرنسية',     3,  3, false, 4),
  ('m16-05', 'niv-12', 'bra-04', 'Langue Anglaise',        'اللغة الإنجليزية',   2,  2, false, 5),
  ('m16-06', 'niv-12', 'bra-04', 'Éducation Islamique',    'التربية الإسلامية',  2,  2, false, 6),
  ('m16-07', 'niv-12', 'bra-04', 'Mathématiques',          'الرياضيات',          2,  2, false, 7),
  ('m16-08', 'niv-12', 'bra-04', 'Éducation Physique',     'التربية البدنية',    1,  2, false, 8),
  ('m16-09', 'niv-12', 'bra-04', 'Tamazight',              'الأمازيغية',          0,  2, false, 9);

-- ============================================================
-- LYCÉE — 2ème & 3ème AS · Gestion et Économie (GE)
-- ============================================================
INSERT INTO matieres_niveau (id, niveau_id, branche_id, matiere_fr, matiere_ar, coefficient, volume_horaire_hebdo, est_principale, ordre) VALUES
  ('m17-01', 'niv-12', 'bra-05', 'Comptabilité et Management','المحاسبة والتسيير', 6, 6, true,  1),
  ('m17-02', 'niv-12', 'bra-05', 'Mathématiques',          'الرياضيات',           5,  4, true,  2),
  ('m17-03', 'niv-12', 'bra-05', 'Économie',               'الاقتصاد',             5,  4, true,  3),
  ('m17-04', 'niv-12', 'bra-05', 'Histoire-Géographie',    'التاريخ والجغرافيا',  4,  3, false, 4),
  ('m17-05', 'niv-12', 'bra-05', 'Langue Arabe',           'اللغة العربية',       3,  3, false, 5),
  ('m17-06', 'niv-12', 'bra-05', 'Philosophie',            'الفلسفة',              2,  2, false, 6),
  ('m17-07', 'niv-12', 'bra-05', 'Langue Française',       'اللغة الفرنسية',      2,  2, false, 7),
  ('m17-08', 'niv-12', 'bra-05', 'Langue Anglaise',        'اللغة الإنجليزية',    2,  2, false, 8),
  ('m17-09', 'niv-12', 'bra-05', 'Éducation Islamique',    'التربية الإسلامية',   2,  1, false, 9),
  ('m17-10', 'niv-12', 'bra-05', 'Droit',                  'القانون',              2,  2, false, 10),
  ('m17-11', 'niv-12', 'bra-05', 'Éducation Physique',     'التربية البدنية',     1,  2, false, 11),
  ('m17-12', 'niv-12', 'bra-05', 'Tamazight',              'الأمازيغية',           0,  2, false, 12);

-- ============================================================
-- LYCÉE — 2ème & 3ème AS · Langues Étrangères (LE)
-- ============================================================
INSERT INTO matieres_niveau (id, niveau_id, branche_id, matiere_fr, matiere_ar, coefficient, volume_horaire_hebdo, est_principale, ordre) VALUES
  ('m18-01', 'niv-12', 'bra-06', 'Langue Française',       'اللغة الفرنسية',     5,  5, true,  1),
  ('m18-02', 'niv-12', 'bra-06', 'Langue Anglaise',        'اللغة الإنجليزية',   5,  5, true,  2),
  ('m18-03', 'niv-12', 'bra-06', 'Langue Arabe',           'اللغة العربية',      4,  4, true,  3),
  ('m18-04', 'niv-12', 'bra-06', 'Traduction',             'الترجمة',             3,  3, true,  4),
  ('m18-05', 'niv-12', 'bra-06', 'Philosophie',            'الفلسفة',             2,  2, false, 5),
  ('m18-06', 'niv-12', 'bra-06', 'Histoire-Géographie',    'التاريخ والجغرافيا', 2,  2, false, 6),
  ('m18-07', 'niv-12', 'bra-06', 'Éducation Islamique',    'التربية الإسلامية',  2,  1, false, 7),
  ('m18-08', 'niv-12', 'bra-06', 'Mathématiques',          'الرياضيات',          1,  2, false, 8),
  ('m18-09', 'niv-12', 'bra-06', 'Éducation Physique',     'التربية البدنية',    1,  2, false, 9),
  ('m18-10', 'niv-12', 'bra-06', 'Tamazight',              'الأمازيغية',          0,  2, false, 10);

-- ============================================================
-- SCHÉMA DES TABLES (Migration Laravel)
-- ============================================================
-- À créer via: php artisan make:migration create_curriculum_algerien_tables
--
-- Schema::create('paliers', function (Blueprint $table) {
--     $table->uuid('id')->primary();
--     $table->string('code', 20)->unique();
--     $table->string('nom_fr', 100);
--     $table->string('nom_ar', 100);
--     $table->integer('ordre');
-- });
--
-- Schema::create('niveaux', function (Blueprint $table) {
--     $table->uuid('id')->primary();
--     $table->uuid('palier_id');
--     $table->string('code', 10)->unique();  -- '1AP','2AM','3AS'...
--     $table->string('nom_fr', 100);
--     $table->string('nom_ar', 100);
--     $table->integer('ordre');
--     $table->foreign('palier_id')->references('id')->on('paliers');
-- });
--
-- Schema::create('branches', function (Blueprint $table) {
--     $table->uuid('id')->primary();
--     $table->string('code', 10)->unique();
--     $table->string('nom_fr', 100);
--     $table->string('nom_ar', 100);
--     $table->jsonb('niveaux_applicables'); -- ["2AS","3AS"]
-- });
--
-- Schema::create('matieres_niveau', function (Blueprint $table) {
--     $table->uuid('id')->primary();
--     $table->uuid('niveau_id');
--     $table->uuid('branche_id')->nullable();
--     $table->string('matiere_fr', 100);
--     $table->string('matiere_ar', 100);
--     $table->unsignedTinyInteger('coefficient');
--     $table->unsignedTinyInteger('volume_horaire_hebdo')->nullable();
--     $table->boolean('est_principale')->default(false);
--     $table->integer('ordre')->default(0);
--     $table->foreign('niveau_id')->references('id')->on('niveaux');
--     $table->foreign('branche_id')->references('id')->on('branches');
--     $table->unique(['niveau_id','branche_id','matiere_fr']);
-- });
