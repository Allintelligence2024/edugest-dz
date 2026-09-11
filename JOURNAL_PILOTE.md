# JOURNAL PILOTE — 15 min/jour, 5 lignes, sans mentir

## 2026-09-11 (J1 — Phase 0 code)
- Fait : gating pilote web (Sidebar MODULE_MAP resserré → 10 menus + RGPD ; routes diagnostic/feedback/prediction-ia verrouillées par ModuleProtectedRoute) ; navigation mobile pilote (onglets Marketplace/IA retirés, rôle 'secretariat' routé admin) ; vérifié que edugestdz/ = shim documenté (rien supprimé) ; créé PILOTE_BUSINESS.md.
- École/business : rien encore — liste 10 écoles + script + décision hébergement à faire (P0-B1/B2/B3).
- Bloqué : rien.
- CI Phase 0 (PR #91, run 34608953990) : backend ✅, frontend ✅, qualité ❌ pré-existant (Larastan main, hors scope P0). Pre-Deploy ✅.
- Demain : P0-B1/B2/B3 (liste + 5 appels + décision hébergement).

## 2026-09-11 (J1 — soir : 3 fixes + P1-C1)
- Fait : rôle eleve seedé + test ; $lectureDossier (+parent) + 6 tests dossier ; commande tenant:configurer-pilote + 4 tests ; patch CI ; écran notes prof réécrit + 3 tests mobile (24/24 local) ; PHP 5/5 syntaxe OK.
- École/business : pilote intéressé signalé — démo à préparer (parent notes/factures + prof saisie).
- Bloqué (résolu) : 2 runs CI rouges à `composer install` (6-8 s) + Pre-Deploy rouge. Cause : `$lectureDossier` manquant dans le `use` du groupe parent `core.php` → boot Laravel explosé (variable indéfinie). php-parser ne voit pas ça (syntaxe valide). Fix : 1 ligne. Leçon : toute variable ajoutée dans un fichier de routes doit être traçée dans TOUS les `use` imbriqués ; la CI backend reste le seul garde-fou. Suite : 9/10 puis 10/10 verts après fix UUID. VERDICT FINAL (run 34614971993) : backend ✅, frontend ✅, Pre-Deploy ✅, qualité rouge pré-existant (1 annotation ajoutée par moi, corrigée aussitôt). Tout est poussé.
- Demain : fin P1 (bulletins parent P1-C2, paiements P1-C4, dashboard P1-C3, vérifs P1-C5) + P0 business.
- Humeur/runway (1-5) : _à remplir_
- Humeur/runway (1-5) : _à remplir_

## 2026-09-11 — P1-C2..C5 : 8 écrans mobiles + mes-enfants + planning eleve_id (en attente CI)
- Backend : `GET parents/mes-enfants` (role:parent, avant apiResource) + `GET planning?eleve_id` (validate uuid, 403 périmètre, `groupe_ids` inscriptions validées). 8 tests (`MesEnfantsTest`, `PlanningEleveTest`).
- Mobile : `EnfantContext` + `EnfantSelector` + 8 écrans réécrits sur contrats vérifiés (notes groupées+moyenne officielle, présences plates+stats accentuées, paiements financier+`numero_facture`, bulletins+PDF Linking, plannings groupés/Seance, dashboard 4 cartes, appel défaut présent). Messages/Notifications masqués. 10 tests (8 fichiers).
- Cause racine n°2 (majeure) : intercepteur axios `response => response.data` — convention `body?.data`. Tout le parsing `res?.data?.data` (rogue + ma 1ʳᵉ vague) rendait vide. 16 occurrences corrigées ; reste : admin (gelé) + dashboard prof (hors scope P1).
- Verdict CI : à compléter après run.
