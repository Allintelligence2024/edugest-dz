# PLAN PILOTE 30 OCTOBRE — EduGest DZ

> Solo. 3 mois de runway. 1 école pilote payante le 30 octobre 2026.
> Ce plan fige le scope, liste chaque fix discuté, et interdit tout le reste.
> Règle d'or : **si ce n'est pas dans ce plan, c'est gelé jusqu'au 31 octobre.**

---

## 0. Règles de survie (non négociables)

1. **CI verte en permanence.** Rien ne merge sur rouge. La CI est le seul exécuteur backend (pas de PHP local).
2. **Backend : changements minimaux.** Chaque modif backend coûte 4–20 min de CI à l'aveugle. Privilégier le câblage mobile/web sur les endpoints existants.
3. **1 seul chantier code à la fois.** Solo = pas de parallélisme. Finir > commencer.
4. **Bugfix pilote > features.** À partir de la Phase 5, seules les corrections demandées par l'école pilote sont autorisées.
5. **Business chaque semaine.** Minimum : 5 contacts/suivis écoles par semaine jusqu'à l'accord signé. Le code ne remplace pas la vente.
6. **Journal : 15 min/jour.** Fichier `JOURNAL_PILOTE.md` : fait / bloqué / demain. Sans journal, tu te mens sur l'avancement.
7. **Santé : 1 jour off/semaine.** À 7 semaines solo, le burn-out est le risque n°1. Planifié, pas subi.

---

## 1. Scope pilote : IN / OUT / CUT

### IN — le seul périmètre qui compte

| Bloc | Web (directeur/secrétaire) | Mobile parent | Mobile élève | Mobile prof |
|---|---|---|---|---|
| Inscriptions | Créer, importer CSV, dossier | — | — | — |
| Factures | Générer, envoyer, PDF, caisse jour, reçus | Voir + reçus | — | — |
| Notes | Saisie, listes | Voir + moyenne | Voir + moyenne | Saisir (groupes/évals) |
| Bulletins | Générer PDF, publier | Voir + PDF | Voir + PDF | — (CUT, voir ci-dessous) |
| Emploi du temps | Créer séances, conflits | Voir (enfant) | Voir (moi) | Voir (moi) |
| Présences | Listes, appel | Voir (enfant) | — | Faire l'appel |
| Mon salaire (prof) | Calcul/validation (direction) | — | — | Voir mes paies |
| Auth | Login, 2FA direction | Login | Login | Login |

### CUT — décidé, justifié, définitif

| Élément coupé | Pourquoi |
|---|---|
| "Bulletin" côté prof | Un prof ne reçoit pas de bulletin. Besoin inexistant. |
| Dashboard parent à tirets | Écran qui affiche `-` = démo suicidaire. Branché en 1 jour ou retiré de la nav. |
| App élève séparée | Zéro écran existant. L'élève réutilise les écrans parent (notes/bulletins/planning) via un rôle `eleve`. |
| Prédiction IA / EWS exposée | Coefficients en dur, non validés, sur des mineurs. Masquée du pilote (ni menu, ni écran, ni API appelée). Audit ultérieur ou retrait. |
| Paiement SATIM en prod | Pas d'homologation. Pilote = cash/virement + reçus. SATIM sandbox testé seulement si temps en Phase 6. |
| SMS Twilio | Coût + délivrabilité DZ non prouvés. Pilote = notifications in-app + relances imprimables/WhatsApp manuel. |
| Marketplace, cantine, transport, stock, budget, personnel, entretien, surveillance, LMS, examens, diagnostic, billets, pointage, bibliothèque | Gelés. Tenant pilote = modules core uniquement (gating `ModuleCheck` + `ModulesContext`). |
| Zero-Trust strict branché | Conditionné au frontend (reste connu). Mode normal suffisant pour le pilote. |
| Refonte design / design system | Interdite. On répare, on ne redesigne pas. |

---

## 2. Phase 0 — J1-J3 (11 → 14 sept) : Décisions & nettoyage

**Objectif :** partir sur un repo propre, des décisions écrites, et 10 écoles ciblées.

### Code
- [x] **P0-C1 Layout `edugestdz/` vérifié (CORRIGÉ le 11 sept. — voir Modifications).**
  Conclusion : **rien à supprimer.** `edugestdz/frontend`, `edugestdz/mobile`,
  `edugestdz/scripts`, `edugestdz/deploy.sh` = symlinks vers la racine (mode 120000),
  `edugestdz/backend/` = répertoire réel canonique, `backend/` racine = symlink.
  C'est le shim de compat CI documenté dans `edugestdz/README.md` (pont en attendant
  `docs/fusion-workflows.patch` sur main depuis un poste autorisé). Ne pas y toucher
  avant le 30 octobre. Preuve : `git ls-files -s edugestdz/` + `diff -r` root vs cibles = 0.
- [x] **P0-C2 Gating pilote (fait le 11 sept.).**
  - Web Sidebar (`frontend/src/components/Sidebar.jsx`, `MODULE_MAP` étendu, fail-closed) :
    messages/campagnes/notifications → clé `communication` (inexistante backend → masqués),
    feedback-enseignant → `feedback`, audit-logs → `audit`, prediction-ia → `diagnostic`.
    Menus restants tenant pilote (14 modules désactivés) : **Accueil, Élèves, Planning,
    Présences, Absences, Notes, Bulletins, Devoirs, Factures, Profil (+ RGPD si admin) = 10 (+1).**
  - Routes (`frontend/src/App.jsx`, pattern `ModuleProtectedRoute` existant) : `/diagnostic`,
    `/feedback-enseignant`, `/prediction-ia` bloquées quand module inactif (même en URL directe).
  - Mobile (`mobile/src/navigation/AppNavigator.js`) : onglets Marketplace (parent) et
    Marketplace + IA (admin) retirés, écrans conservés (tests verts) ; rôle `secretariat`
    (seedé) routé vers AdminTabs — sans ça la secrétaire tombait sur les onglets parent.
  - **Procédure tenant pilote** (fail-open constaté : `estActif()` et `actifs()` retournent
    tout-actif sans lignes BDD, cache busté par activer/desactiver) : après création du
    tenant, 1 appel `POST /api/v1/modules/bulk` avec les 14 clés à `false`
    (transport, cantine, stock, budget, personnel, entretien, surveillance, lms,
    marketplace, examens, diagnostic, billets, pointage, bibliotheque).
    Vérifier ensuite `GET /modules/actifs` = `["core"]`, sidebar = 10 menus, URLs
    /diagnostic et /prediction-ia = page bloquée.
  - **Connus, acceptés pilote, à traiter après :** `ModulesContext` fail-open au chargement
    et en erreur (tous menus flashés/affichés) ; `estActif()` fail-open par défaut ;
    routage mobile `gestionnaire`/`comptable` → ParentTabs (à refaire avec le rôle eleve en P2).
- [x] **P0-C3 Freeze déclaré.** Ce plan committé. Tout commit hors scope après ce point doit référencer une tâche du plan.

### Business
- [ ] **P0-B1 Liste 10 écoles.** Modèle prêt : `PILOTE_BUSINESS.md` § 1. À remplir : 10 écoles privées / centres de cours à Oran (+ environs) : nom établissement, nom directeur, téléphone, taille estimée, contact existant (oui/non).
- [ ] **P0-B2 Script d'appel (1 page).** Prêt : `PILOTE_BUSINESS.md` § 2 + objections § 3. Zéro mot technique.
- [ ] **P0-B3 Décision hébergement écrite (10 lignes).** Modèle prêt : `PILOTE_BUSINESS.md` § 4. Recommandation : **SaaS central hébergé par toi** (un VPS, multi-tenant via isolation existante). Contenu : où (2 options chiffrées : VPS EU vs hébergeur DZ — prix réels), coût mensuel, qui onboard (toi, import CSV + comptes en 1 jour), que se passe-t-il si ça tombe (backup quotidien, RTO/RPO annoncés), où vont les données (réponse Loi 18-07 prête).
- [ ] **P0-B4 Premiers appels.** 5 appels minimum avant lundi 15. Objectif : 3 visites planifiées. Journal : `PILOTE_BUSINESS.md` § 5.

### Critères de sortie Phase 0
- [x] Layout `edugestdz/` vérifié et documenté, CI verte (à confirmer au push).
- [x] Tenant pilote = 10 menus + RGPD (captures à faire en P4 sur données réelles).
- [ ] Liste 10 écoles + script + décision hébergement écrits.
- [ ] 5 appels passés, 3 visites planifiées.

### Interdit Phase 0
Coder des features. Redesigner. Toucher au backend (sauf bug bloquant CI).

---

## 3. Phase 1 — S1 (15 → 21 sept) : Réparer le cassé

**Objectif :** tout ce qui existe dans le scope pilote fonctionne de bout en bout. Rien de neuf.

### Code
- [ ] **P1-C1 Réécrire `mobile/src/screens/enseignant/NotesScreen.js`.** État actuel : URL prod en dur, token via AsyncStorage, header `X-Tenant-ID` maison — contourne AuthContext/SecureStore/tenant. Réécriture sur le client API officiel (`api/endpoints.js` + token SecureStore via AuthContext). Parcours : mes groupes → mes évaluations → saisie notes → sauvegarde. Tester : saisie 5 notes, rechargement, déconnexion/reconnexion.
- [ ] **P1-C2 Brancher `mobile/src/screens/parent/BulletinsScreen.js`.** État actuel : placeholder `noData`. Brancher : liste bulletins de l'enfant (`GET eleves/{id}/bulletins`) + ouverture PDF (endpoint bulletin PDF existant). Tester avec 2 bulletins réels.
- [ ] **P1-C3 Dashboard parent : brancher ou retirer.** Si branchage > 1 jour → retirer de la navigation pilote et rediriger l'accueil parent vers Notes. Aucun `-` visible le jour de la démo.
- [ ] **P1-C4 Vérifier `PaiementsScreen` parent.** Liste factures de l'enfant + statuts + reçus. SATIM : bouton paiement en ligne **masqué** pour le pilote (pas d'homologation) — mention "paiement au secrétariat". Vérifier statuts `payée/émise/en_retard/partielle/annulée` mappés à l'API réelle.
- [ ] **P1-C5 Vérifier planning parent + appel prof.** `parent/PlanningScreen` (planning de l'enfant) et `enseignant/PresencesScreen` (appel par séance) de bout en bout contre l'API. Noter tout écart 428/403 (rôles, tenant) et corriger côté backend **uniquement si bloquant**.
- [ ] **P1-C6 Tests existants verts.** `mobile npm test` (4 suites) + `frontend npx vitest run` verts. Aucune régression. Pas de nouveaux tests sauf sur code réécrit (P1-C1 : 3 tests min : chargement groupes, saisie, erreur réseau).

### Business
- [ ] **P1-B1** 5 appels restants + relances. **3 visites effectuées** avec démo (même imparfaite) + prise de notes douleurs par école.
- [ ] **P1-B2** Choisir l'école pilote cible n°1 (critères : directeur décideur direct, < 500 élèves, secrétaire motivée, paie cash aujourd'hui).

### Critères de sortie Phase 1
- [ ] Parent : notes + bulletins PDF + factures + planning enfant = E2E OK sur build mobile branchée à l'API (captures).
- [ ] Prof : notes + planning + appel = E2E OK, zéro URL en dur, zéro AsyncStorage auth (`grep` de contrôle).
- [ ] 3 visites effectuées, 1 école cible n°1 choisie.

### Interdit Phase 1
Nouveaux écrans. Nouveau backend (sauf déblocage 403/tenant). SATIM. SMS.

---

## 4. Phase 2 — S2 (22 → 28 sept) : App élève par réutilisation

**Objectif :** un compte élève voit ses notes, son bulletin, son emploi du temps. Zéro écran dupliqué.

### Code
- [ ] **P2-C1 Rôle `eleve` + scoping backend.** Constat P0 : le rôle `eleve` n'est seedé nulle part (rôles : super_admin, admin, gestionnaire, enseignant, parent, comptable, secretariat). Donc : (1) ajouter `eleve` à `RolePermissionSeeder` (+ permissions lecture seule notes/bulletins/planning) ; (2) scope : un élève ne lit que `eleve_id = moi` (résolu via `user->eleve_id`, jamais via paramètre client). Fail-closed : sans `eleve_id` → 403. Tests backend : élève lit ses notes (200), élève lit celles d'un autre (403), sans eleve_id (403).
- [ ] **P2-C2 `EleveNavigator` mobile.** Nouveau navigateur qui **réutilise** les écrans parent (Notes, Bulletins, Planning) — import direct, pas de copie. Routage login : `role === 'eleve'` → EleveNavigator **avant** le défaut ParentTabs (corriger aussi `gestionnaire`/`comptable` qui tombent sur ParentTabs : routage explicite ou écran "rôle non supporté sur mobile", au choix, mais jamais un mauvais tableau).
- [ ] **P2-C3 Moyenne correcte.** `NotesScreen` calcule une moyenne simple non pondérée — soit afficher "moyenne indicative (non officielle)", soit brancher la moyenne pondérée backend si l'endpoint existe. Ne jamais afficher comme officiel un calcul faux.
- [ ] **P2-C4 PWA = plan B.** Vérifier que le build web PWA s'installe sur Android (manifest + icons présents). Si le navigateur élève bloque en fin de semaine, l'élève passe par la PWA. Décision vendredi 26 : natif ou PWA pour le pilote.

### Business
- [ ] **P2-B1** 2e visite école n°1 : démo sur **leurs vrais noms de classes/matières** (données fictives mais réalistes). Objectif : accord de principe.
- [ ] **P2-B2** Accord pilote écrit (1 page — modèle `PILOTE_BUSINESS.md` § 6) : périmètre, durée (1 mois renouvelable), prix symbolique (proposition : 10 000–20 000 DA/mois — à ajuster au terrain), résiliation libre, qui fait quoi (toi : install + formation 2h + support WhatsApp ; eux : 1 référent + données élèves en CSV).

### Critères de sortie Phase 2
- [ ] Compte élève E2E : notes + bulletin PDF + EDT (captures). Test 403 inter-élèves vert en CI.
- [ ] Zéro fichier dupliqué parent→élève (`git diff --stat` : que de la réutilisation + navigateur).
- [ ] Accord de principe école n°1 (oral + date de signature).

### Interdit Phase 2
Design élève spécifique. Gamification. Contenu LMS. Messages élève-prof.

---

## 5. Phase 3 — S3 (29 sept → 5 oct) : Prof complet + parcours directeur

**Objectif :** le prof voit son salaire, le directeur fait inscrire→facturer→noter→bulletin en < 30 min.

### Code
- [ ] **P3-C1 Endpoint "mes paies" (backend, seul ajout backend de la semaine).** `GET /api/v1/paies/mes` : paies de l'enseignant connecté uniquement (résolu serveur, pas de paramètre). Inclure : période, brut, retenues IRG/CNAS, net, statut, lien bulletin PDF. Tests : enseignant voit les siennes (200), pas celles d'un autre (403/absent), non-enseignant (403).
- [ ] **P3-C2 Écran mobile "Mon salaire".** Liste mes paies + détail + bulletin PDF. Accessible depuis l'accueil prof. Aucune donnée d'un autre salarié visible (vérifié par test manuel avec 2 comptes).
- [ ] **P3-C3 Parcours directeur web < 30 min.** Avec tenant pilote (10 menus) : créer 1 élève → l'inscrire → générer facture → saisir 3 notes → générer bulletin PDF → publier. Chronométrer. Tout clic inutile = bug à corriger (pas de refonte, du raccourci : valeurs par défaut, pré-remplissage).
- [ ] **P3-C4 Import CSV école pilote.** Tester avec un CSV au format de l'école n°1 (colonnes réelles, 50 lignes min) : erreurs ligne par ligne, rollback si échec, import idempotent (relançable sans doublons). Documenter le modèle CSV en 1 page pour la secrétaire.

### Business
- [ ] **P3-B1 ACCORD PILOTE SIGNÉ.** 1 page, prix, dates, référent nommé. Sans signature, la Phase 4 ne démarre pas (règle).
- [ ] **P3-B2** Récupérer : CSV élèves (ou Excel à convertir), liste classes/matières, 1 bulletin papier modèle (mise en page à reproduire), barème facturation (montants, échéances).

### Critères de sortie Phase 3
- [ ] Prof : salaire E2E avec 2 comptes (captures + test 403).
- [ ] Directeur : parcours < 30 min chronométré (vidéo ou pas-à-pas horodaté).
- [ ] Import CSV réel : 50 lignes, 0 erreur bloquante, idempotent.
- [ ] Accord signé. Prix et dates écrits.

### Interdit Phase 3
Nouveaux endpoints hors "mes paies". Nouveaux menus. Paramétrages exotiques.

---

## 6. Phase 4 — S4 (6 → 12 oct) : Déploiement central + données réelles

**Objectif :** prod en ligne, école onboardée, 4 flux verts avec de vraies données.

### Code / Infra
- [ ] **P4-C1 Déploiement central ("1-click").** VPS + `docker-compose.prod.yml` + `deploy.sh` : Postgres, Redis, app, queue, scheduler, frontend (ou Vercel selon décision P0-B3 — une seule cible, pas deux). `.env` prod complet (secrets générés, `APP_DEBUG=false`, Sentry DSN). Backups quotidiens automatiques + **test de restauration** (restaurer 1 fois pour de vrai, sinon le backup est un mensonge).
- [ ] **P4-C2 Domaine + HTTPS + santé.** Domaine (ou sous-domaine) + certificats + page `/api/health` + alertes (Sentry + uptime basique). Noter RTO/RPO réels.
- [ ] **P4-C3 Onboarding école.** Tenant n°1 : **appliquer la procédure P0-C2** (`POST /modules/bulk` 14 clés à false, vérifier `actifs = ["core"]`, sidebar = 10 menus), import CSV réel, comptes (1 direction, 1 secrétaire, 3 profs, 10 parents test, 10 élèves test). Mots de passe provisoires + procédure reset. Checklist onboarding écrite (réutilisable école n°2).
- [ ] **P4-C4 Bulletin PDF réel validé.** Générer avec vraies données, comparer au bulletin papier de l'école (mise en page, FR/AR, moyennes, mentions). Faire valider par le directeur : signature "bon pour générer".
- [ ] **P4-C5 Gel prod.** Après onboarding : tout changement prod passe par `deploy.sh` uniquement, jamais de bricolage SSH. 1 créneau de déploiement/jour max.

### Business
- [ ] **P4-B1 Formation : 1h secrétaire + 1h prof référent.** Sur leurs données, leurs mots. Fiche mémo 1 page par rôle (pas de manuel de 60 pages).
- [ ] **P4-B2** Définir les métriques pilote (relevées chaque vendredi) : inscriptions saisies, factures émises, montant recouvré, notes saisies, bulletins générés, appels parents au secrétariat (avant/après), bugs bloquants.

### Critères de sortie Phase 4
- [ ] URL prod + HTTPS + health OK + Sentry reçoit les erreurs.
- [ ] Backup + restauration testée (preuve : base restaurée sur un 2e conteneur).
- [ ] 10 parents + 10 élèves connectés sur leurs comptes, 4 flux E2E en prod.
- [ ] Bulletin validé par le directeur. Formations faites. Métriques J0 relevées.

### Interdit Phase 4
Features. 2e école. Optimisations prématurées.

---

## 7. Phase 5 — S5 (13 → 19 oct) : Pilote réel, bugfix only

**Objectif :** une semaine d'usage réel. Tu corriges ce qu'ils cassent. Rien d'autre.

### Règles
- [ ] **Priorités :** P0 bloquant (l'école ne travaille plus) < 24h · P1 gênant (contournement possible) < 72h · P2 cosmétique → backlog post-pilote, **jamais codé cette semaine**.
- [ ] **Rituel :** 15 min chaque soir avec le référent (WhatsApp/appel) : quoi de cassé, quoi de compris, quoi de manquant. Noté au journal.
- [ ] **Métriques vendredi 17 :** tableau 1 page vs J0. C'est ton argument de vente n°1 pour la suite.
- [ ] **Interdit :** toute feature non demandée par écrit par le référent. Toute refacto. Toute "amélioration" spontanée.

### Critères de sortie Phase 5
- [ ] 5 jours d'usage réel consécutifs (preuve : logs, factures, notes créées par l'école, pas par toi).
- [ ] Zéro P0 ouvert vendredi soir.
- [ ] Tableau métriques J0→J7 rempli.

---

## 8. Phase 6 — S6 (20 → 26 oct) : Encaisser + stabiliser

**Objectif :** de l'argent réel, un produit stabilisé, une décision pour le mois 3.

### Business
- [ ] **P6-B1 ENCAISSER.** Facture émise (via EduGest lui-même — dogfooding) + paiement reçu (virement/cash, reçu signé). Montant symbolique accepté, zéro accepté **uniquement** si contrat payant signé pour novembre.
- [ ] **P6-B2 Pitch avec chiffres.** 1 page : école, effectif, recouvrement mois 1, temps secrétaire avant/après, verbatim directeur (1 phrase autorisée). C'est ça qui vend l'école n°2, pas la techno.
- [ ] **P6-B3 Pipeline école n°2.** 5 nouveaux contacts + 1 visite avec le pitch chiffré.

### Code
- [ ] **P6-C1** Top 5 bugs P1/P2 du pilote corrigés, puis gel.
- [ ] **P6-C2** Fiches mémo 1 page/rôle finalisées (parent, élève, prof, secrétaire, directeur).
- [ ] **P6-C3 (optionnel, seulement si tout est vert)** : test SATIM sandbox de bout en bout (pas de prod, pas d'homologation engagée). Sinon : reporté, sans culpabilité.

### Critères de sortie Phase 6
- [ ] Argent encaissé OU contrat novembre signé.
- [ ] Pitch 1 page chiffré + 5 contacts école n°2.
- [ ] Zéro P0, fiches mémo livrées.

---

## 9. Phase 7 — S7 (27 → 30 oct) : Buffer + clôture

**Objectif :** absorber le retard (il y en aura), tagger, décider.

- [ ] **P7-1** Vider les P0/P1 restants. CI verte. Tag `v0.1-pilote`.
- [ ] **P7-2 Bilan écrit (2 pages max) :** ce qui marche (preuves), ce qui casse (liste), métriques pilote finales, coût réel (heures, infra), décision mois 3 : **GO** (école n°2 + pricing définitif) / **PIVOT** (scope ou cible change, quoi exactement) / **STOP** (critères d'arrêt atteints — les écrire à l'avance : ex. pilote non payant + 0 pipeline = stop).
- [ ] **P7-3** Roadmap novembre en 10 lignes max, dérivée du bilan, pas des envies.

### Critères de sortie Phase 7 = DONE 30 OCTOBRE
- [ ] 1 école pilote **payante** (ou contrat payant signé).
- [ ] 4 rôles E2E en prod : directeur, parent, élève, prof.
- [ ] Tag + bilan + roadmap novembre.

---

## 10. Risques & mitigations

| Risque | Probabilité | Mitigation dans ce plan |
|---|---|---|
| Backend lent à itérer (pas de PHP local) | Certaine | Backend minimal (2 ajouts : rôle+scope élève, mes paies). Pré-vérification syntaxe via `php-parser` avant chaque push. |
| École pilote introuvable | Haute | 10 contacts semaine 1, visites avec démo imparfaite, prix symbolique, cible < 500 élèves. |
| Scope creep (directeur demande "juste un petit module") | Certaine | Accord écrit = périmètre fermé. Toute demande → backlog post-pilote, daté et signé. |
| SATIM/homologation | Certaine (délais) | Exclue du pilote. Cash/virement + reçus. |
| SMS/notifications | Moyenne | In-app + manuel. Pas d'infra SMS avant preuve du besoin. |
| Burn-out solo | Haute | 1 jour off/semaine, 1 chantier à la fois, journal 15 min, Phase 7 = buffer, pas du bonus. |
| Prod qui tombe | Moyenne | 1 déploiement/jour max, backups + restauration testée, Sentry, health check. |
| Données mineurs / Loi 18-07 | Moyenne | Réponse écrite prête (P0-B3) : où, qui accède, combien de temps, suppression sur demande. Registre existant réutilisé. |

---

## 11. Budget & charge (ordres de grandeur)

- **Temps :** ~7 semaines × 6 jours × 6h focus = ~250h. 70% code, 20% business/école, 10% journal/admin.
- **Infra :** 1 VPS + domaine ≈ à chiffrer en P0-B3 (ne pas dépasser ~3 000 DA/mois sans revenu pilote).
- **Revenu cible 30 oct :** ≥ 1 paiement pilote (même symbolique) OU contrat novembre signé. Zéro = échec du plan, décision STOP/PIVOT obligatoire (pas de Phase 8 fantôme).

---

## 12. Journal

`JOURNAL_PILOTE.md` créé. Format quotidien (5 lignes) — voir fichier.

---

*Plan gelé le 2026-09-11. Toute modification = entrée datée ci-dessous avec raison.*

## Modifications
- **2026-09-11 (P0-C1) :** « résidu edugestdz/ à supprimer » ANNULÉ après vérification : ce sont des symlinks documentés (shim CI, voir `edugestdz/README.md`), pas un doublon. Rien supprimé. Leçon : vérifier (`git ls-files -s`, `diff -r`) avant de détruire.
- **2026-09-11 (P0-C2) :** critère « 7 menus max » ajusté à « 10 menus + RGPD (liste explicitée) » : 7 exigeait un filtrage par rôle (gros chantier), le gating par modules donne 10. Listes web + mobile + procédure tenant (`POST /modules/bulk`) écrites. Connus acceptés : fail-open `estActif()`/`ModulesContext`, routage mobile gestionnaire/comptable.
- **2026-09-11 (P2-C1) :** ajouté seed du rôle `eleve` (inexistant dans `RolePermissionSeeder`) + routage mobile explicite gestionnaire/comptable.
