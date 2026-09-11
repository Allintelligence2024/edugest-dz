# Prompt de reprise — EduGest DZ (à donner à OpenCode)

> Copier tout ce qui suit la ligne de séparation dans OpenCode, à la racine du
> dépôt `edugest-dz`.
>
> ⚠️ **État au 2026-09-10 : partiellement obsolète.** La PR #82 est mergée,
> les patches de workflows sont appliqués, la fusion 5.2 est faite
> (`edugestdz/*` → racine, lot F ci-dessous soldé), la couverture backend
> est mesurée (60,71 %) et la licence est tranchée (propriétaire).
> Les lots B–E et G restent d'actualité. Lire `REPRISE_SESSION.md` avant
> ce prompt.

---

Tu reprends la remédiation d'EduGest DZ, une plateforme de gestion scolaire
algérienne (Laravel 11 + React 18/Vite + React Native/Expo). Le travail suit
`PLAN_REMEDIATION_2026.md` (6 sprints). Les sprints 1 à 4 sont livrés, le
sprint 5 est à moitié fait. Tu dois **terminer le sprint 5, faire le sprint 6,
et résorber les dettes ouvertes**.

## Règles du jeu, non négociables

1. **Commentaires, messages de commit, tests et documentation en français.**
   C'est la convention du dépôt, appliquée partout.
2. **Ne jamais annoncer un chiffre que tu n'as pas mesuré.** Ce dépôt a déjà
   affiché « 70 % de couverture » sans jamais exécuter la mesure, et
   « 22 routes leurres » en comptant un tableau PHP au lieu des routes
   servies. Si tu ne peux pas mesurer, écris « non mesuré » et explique
   pourquoi.
3. **Un test qui révèle un défaut ⇒ tu corriges le code, pas le test.**
   Sauf si tu établis d'abord que le test mesure un artefact. Plusieurs tests
   de ce dépôt validaient des anomalies ; ils ont été réécrits, pas
   contournés.
4. **Rendre l'invariant vrai et le prouver par un test-garde AVANT de retirer
   une défense en profondeur.** Voir la section « Pièges ».
5. **Ne décide pas à la place du propriétaire** sur les points listés en
   section « À remonter à l'humain ». Signale, n'invente pas.
6. Avant de supprimer ou déplacer quoi que ce soit, `grep` les références
   depuis le code, les workflows, `docker-compose*.yml`, `vercel.json`,
   `Makefile` et les scripts shell.

## État de départ

- Branche de travail actuelle : `arena/01a080e0-edugest-dz`, **PR #82 ouverte**
  (sprints 4 et 5 partiels), CI verte au dernier commit `0632c6d`.
- **Commence par faire fusionner ou fermer la PR #82.** Le point 5.2 (fusion
  de `edugestdz/`) déplace des milliers de fichiers : toute PR en vol
  deviendrait impossible à relire ou à rebaser.
- Documents à lire en premier, dans cet ordre :
  1. `REPRISE_SESSION.md` — passation, contraintes d'environnement, leçons.
  2. `PLAN_REMEDIATION_2026.md` — le plan, avec le bilan par sprint.
  3. `docs/SPRINT4_QUALITE.md` et
     `docs/SPRINT5_ARCHITECTURE.md` — journaux détaillés.

---

## Étape 0 — Établir ce que ton environnement sait faire (fais-le en premier)

La session précédente travaillait dans un bac à sable **sans PHP**, ce qui a
dicté toutes ses décisions : impossible d'exécuter un test backend en local,
chaque vérification coûtait un aller-retour CI de quatre minutes. **Vérifie si
c'est encore ton cas** — si tu as PHP, tu travailles dix fois plus vite et
plusieurs contournements deviennent inutiles.

```bash
php -v; composer -V; node -v; npm -v; docker -v; psql --version; redis-cli -v
cd backend && php artisan --version
```

Consigne le résultat en tête de ton journal de travail. Trois cas :

- **PHP disponible** → exécute `php artisan test` en local, mesure la
  couverture réellement, génère la baseline PHPStan. Ignore tous les
  contournements « CI seule » ci-dessous.
- **PHP absent mais Docker disponible** → `docker compose` depuis la racine
  du dépôt ; le dépôt fournit `docker-compose.yml` (9 services).
- **Ni l'un ni l'autre** → la CI GitHub Actions est ton seul exécuteur.
  Groupe tes changements, chaque passage coûte ~4 min. Les logs Actions sont
  illisibles sur ce dépôt (`gh run view --log`, l'API `jobs/{id}/logs` et le
  zip renvoient tous `EOF`) ; le seul canal de diagnostic est l'extension
  `backend/tests/Support/CiDiagnosticExtension.php`, qui republie
  les échecs en annotations. Pour les lire :

```bash
RID=$(gh run list --branch <branche> --limit 8 --json databaseId,workflowName,createdAt \
      -q '[.[] | select(.workflowName=="CI — EduGest DZ")] | sort_by(.createdAt) | last | .databaseId')
JID=$(gh api repos/Allintelligence2024/edugest-dz/actions/runs/$RID/jobs -q '.jobs[0].id')
gh api repos/Allintelligence2024/edugest-dz/check-runs/$JID/annotations \
  -q '.[] | select(.annotation_level=="failure") | "\(.title) :: \(.message)"'
```

GitHub tronque une annotation vers 255 caractères : découpe tout message de
diagnostic en morceaux numérotés d'environ 220 caractères.

---

## Lot A — Débloquer la chaîne CI (à faire avant tout le reste)

La session précédente ne pouvait pas pousser de workflow : GitHub refusait
avec `refusing to allow a GitHub App to create or update workflow
'.github/workflows/ci.yml' without 'workflows' permission`. Elle a donc livré
**4 patches** à appliquer à la main. **Vérifie d'abord si toi tu peux pousser
un workflow** (`git push` après une modification triviale de `ci.yml`) : si
oui, applique-les directement.

```bash
git apply docs/ci-secrets.patch
git apply docs/pre-deploy-secrets.patch
git apply docs/ci-qualite.patch
cp    docs/deploy.yml.desactive.patch .github/workflows/deploy.yml
rm    docs/*.patch
git add -A && git commit -m "ci: secrets hors du code + qualité/sécurité"
```

Ils s'appliquent en séquence, dans cet ordre (vérifié). Ce qu'ils font :

- `ci-secrets.patch` / `pre-deploy-secrets.patch` — sortent des secrets
  codés en dur dans les workflows.
- `ci-qualite.patch` — ajoute un job `frontend` (Node 22, `npm ci`, lint non
  bloquant, **`npm run test:coverage`**, build), un job `qualite`
  (`composer audit`, `npm audit --audit-level=high` bloquant sur le frontend
  et non bloquant sur le mobile, Gitleaks 8.21.2 `detect --no-git`,
  `scripts/analyse-statique.sh` non bloquant), et découpe le job `backend` en
  deux étapes : mesure `--min=45` avec `continue-on-error` puis un verdict qui
  republie le pourcentage en `::notice`/`::error` et dans
  `$GITHUB_STEP_SUMMARY`.
- `deploy.yml.desactive.patch` — le workflow `CD — Deploy Production` échoue
  en permanence sur `main` : il déploie encore par SSH vers un serveur
  abandonné au profit de Vercel. Ce rouge est connu, sans conséquence, mais
  il pollue le tableau de bord.

Ensuite :

```bash
./scripts/generer-secrets.sh          # depuis la racine du dépôt
cd backend && php artisan migrate               # 2 migrations en attente
```

Les deux migrations en attente sont
`2026_09_07_200000_add_key_version_to_audit_chain_table.php` (rotation de clé
de la chaîne d'audit) et `2026_09_07_210000_create_refresh_tokens_table.php`.

**Critère de fin du lot A :** la CI est verte, le job frontend s'exécute
réellement, et **tu as lu au moins une fois le pourcentage réel de couverture
backend**. Ce chiffre n'a jamais été mesuré. Tout objectif de couverture
énoncé avant cette lecture est une invention.

Puis génère la baseline PHPStan (niveau 6, `backend/phpstan.neon`)
et rends l'étape bloquante dans `scripts/analyse-statique.sh`.

---

## Lot B — Résorber la dette d'isolation tenant

Contexte indispensable : le point P1-6 de l'audit demandait de supprimer
« 16 checks tenant redondants ». **La prémisse était fausse et l'inverse a été
démontré** : sur les sept modèles visés, quatre n'avaient aucun scope tenant.
Ces `where('tenant_id', …)` sont la seule barrière d'isolation ; les
supprimer ouvre une fuite entre établissements. Sur 63 filtres recensés, **un
seul** est réellement redondant, et il a été conservé volontairement en
défense en profondeur.

Sur 106 modèles : 67 sont scopés, 8 l'ont été au sprint 5, il reste **6
modèles** listés dans la constante `DETTE` de
`backend/tests/Feature/Security/PorteeTenantModelesTest.php` :

`AlerteSurveillance`, `CameraConfig`, `CandidatExamen`, `SalleExamen`,
`SessionExamen`, `SurveiillantExamen` (la faute de frappe est dans le nom de
classe d'origine — ne la corrige pas au passage, ce serait un renommage à
part entière).

Pour chacun, dans cet ordre :

1. Vérifie qu'il ne définit pas déjà une méthode `tenant()` qui entrerait en
   conflit avec la relation du trait.
2. Vérifie les seeders, factories, commandes artisan et jobs qui
   l'instancient. **Le hook `creating` de `BelongsToTenant` lève une exception
   si aucun tenant n'est résolu** : tout chemin hors requête HTTP casse
   immédiatement.
3. Ajoute `use App\Traits\BelongsToTenant;` (import **et** `use` dans le corps
   de la classe — voir le piège `User` plus bas).
4. Retire l'entrée de `DETTE`. Un test symétrique échoue si tu oublies.
5. Exécute la suite. Un test qui casse ici documente probablement une fuite
   préexistante : lis-le avant de le modifier.

**Critère de fin :** `DETTE` est vide, et `PorteeTenantModelesTest` passe.
Ne touche pas à `HORS_PERIMETRE` : chacune de ses entrées est une décision
documentée (marketplace publique, référentiel des rôles, administration
super-admin, et `User` qui est cherché par e-mail avant que le tenant existe).

---

## Lot C — 5.3 Découper les contrôleurs de plus de 350 lignes

Neuf fichiers, mesurés :

| Fichier (`backend/app/Http/Controllers/Api/V1/`) | Lignes |
|---|---|
| `StockInventaireController.php` | 569 |
| `EntretienController.php` | 464 |
| `EleveController.php` | 463 |
| `TransportController.php` | 458 |
| `BudgetController.php` | 442 |
| `AuthController.php` | 440 |
| `PaiementEnLigneController.php` | 422 |
| `CantineController.php` | 411 |
| `LmsController.php` | 377 |

Méthode imposée :

- Extraire la **logique métier** vers `app/Services/` (le dépôt en compte déjà
  ~25) ou des classes d'action à méthode unique. Le contrôleur ne garde que
  validation, autorisation et forme de la réponse.
- **Ne change aucune URL, aucun nom de route, aucune forme de réponse.**
  C'est un refactoring, pas une v2 de l'API. Le versioning est documenté dans
  `docs/VERSIONING_API.md` : lis-le avant de croire qu'un changement
  est anodin. Ajouter une valeur d'énumération **en sortie** est une rupture.
- Conserve la compatibilité avec `route:cache` : les routes utilisent
  `apiResource(...)->only()/->except()`.
- `RoutesIntegriteTest` et `RbacCoverageTest` doivent rester verts. Ils sont
  ton filet : `RbacCoverageTest` vérifie que chaque route est protégée, et
  reconnaît les leurres au **nom de route** `honeypot.*` (pas à leur chemin).
- Attention à `AuthController` : il porte la rotation des refresh tokens et le
  cookie httpOnly. Le scope tenant est **fail-closed** (`1 = 0` sans tenant
  résolu) ; sur les routes publiques comme `/auth/refresh`, le code fait
  `withoutGlobalScope('tenant')` puis rétablit le contexte. Ne casse pas cette
  séquence.

**Critère de fin :** aucun contrôleur au-dessus de 350 lignes, suite verte,
aucune route modifiée. Ajoute un test ou un script qui échoue si un
contrôleur repasse au-dessus du seuil — sinon la dette revient.

---

## Lot D — 5.6 i18n et icônes

**État réel :** il existe déjà une i18n maison,
`frontend/src/context/I18nContext.jsx` (61 lignes), avec quatre
langues dans `src/lang/{fr,ar,en,dz}.json` et une gestion RTL pour `ar` et
`dz`. Ce n'est pas un terrain vierge.

1. **Migrer vers `i18next` + `react-i18next`** pour obtenir ce que la version
   maison ne sait pas faire : pluriels ICU, formatage des dates et des
   nombres, interpolation, fallback de clé manquante. Conserve les quatre
   langues et le comportement RTL — l'arabe et la darija algérienne sont des
   langues de production ici, pas des cas de démonstration.
2. Ajoute un test qui échoue si une clé existe en `fr` mais manque dans une
   autre langue. Une traduction absente doit être visible en CI, pas
   découverte par un utilisateur.
3. **Emoji vers icônes `lucide-react`** (déjà en dépendance, `^0.460.0`) :
   **504 occurrences dans 67 fichiers `.jsx`**, les plus chargés étant
   `ExamensPage.jsx` (43), `LmsPage.jsx` (35), `SurveillancePage.jsx` (35),
   `EleveModal.jsx` (24), `DiagnosticPage.jsx` (24). Chaque icône reçoit un
   `aria-label` explicite en français.
   **Exception :** les drapeaux de `LANG_META` dans `I18nContext.jsx` sont des
   emoji légitimes (sélecteur de langue) — ne les remplace pas par des icônes
   génériques.

**Critère de fin :** plus d'emoji porteur de sens dans le JSX, les 122 tests
frontend existants restent verts, et le rendu RTL est vérifié par au moins un
test.

---

## Lot E — Couverture de tests

Chiffres réels, mesurés le 8 septembre 2026, à ne pas confondre avec les
objectifs affichés jadis :

| Périmètre | Réel | Cliquet actuel | Cible |
|---|---|---|---|
| Frontend (122 tests, 21 fichiers) | lignes 18,85 % · branches 54,92 % · fonctions 35,25 % | 18/52/34/18 dans `vitest.config.js` | 40 % de lignes |
| Backend | **jamais mesuré** | `--min=45` posé dans le patch CI | 60 % |
| Mobile | jest-expo configuré, 4 fichiers de test | aucun | 30 % |

- **Frontend** : environ 30 pages restent à couvrir. Relève le cliquet de
  `vitest.config.js` à chaque palier atteint — un seuil qu'on n'exécute pas ne
  protège rien, et un seuil qu'on ne relève jamais ne protège plus.
- **Backend** : lis d'abord le pourcentage réel (lot A), puis vise 60 % en
  priorisant les services métier et les chemins d'autorisation.
- **Mobile** : l'infrastructure existe (`jest-expo`, `@testing-library/jest-native`,
  scripts `test:ci` et `test:coverage`) mais rien ne tourne en CI. Couvre les
  trois flux demandés : **authentification, présence, paiement**. Ajoute le job
  mobile à `.github/workflows/ci.yml`.

---

## Lot F — 5.2 Fusion de `edugestdz/` dans la racine

**À faire en dernier, sur un commit dédié, aucune autre PR en vol.**

Cartographie déjà faite — trois entrées existent des deux côtés, et **aucune
ne pose de collision de nom de fichier** :

| Entrée | Racine | `edugestdz/` | Traitement |
|---|---|---|---|
| `.github/` | `ci.yml`, `deploy.yml`, `pre-deploy-check.yml` | `frontend-ci.yml` (**inerte** : GitHub ne lit que la racine) | Le job frontend est déjà repris par `ci-qualite.patch`. Vérifie qu'il ne reste rien d'utile dans `frontend-ci.yml`, puis supprime `.github/`. |
| `scripts/` | `analyse-statique.sh`, `audit-securite.sh`, `verify-api-pages.sh` | `generer-secrets.sh`, `restore-backup.sh`, `smoke-test.sh` | Fusion directe, 6 fichiers distincts. |
| `docs/` | `archive/`, `design/`, `business/`, `guides/`, `README.md` | documentation de référence (`SECURITE.md`, `ARCHITECTURE.md`, …) | Fusion directe, aucun nom en commun. Mets `docs/README.md` à jour pour distinguer référence et archive. |

Après la fusion, **13 références au chemin `edugestdz/` sont à réécrire** dans
6 fichiers : `.github/workflows/deploy.yml` (4), `scripts/audit-securite.sh` (2),
`edugestdz/docker-compose.prod.yml` (2), `.github/workflows/pre-deploy-check.yml` (2),
`.github/workflows/ci.yml` (2), `scripts/analyse-statique.sh` (1). Vérifie
aussi `vercel.json` (répertoire racine du projet), le `Makefile`, `install.sh`,
`deploy.sh`, `update.sh`, `server-setup.sh` et les `docker-compose*.yml`.

Enfin, **le README pointera à nouveau vers `docs/...`** : ses liens ont été
réécrits vers `docs/...` au sprint 5 parce que les cibles n'existaient
pas. Après fusion, remets-les et **vérifie chaque lien relatif par un script**,
comme au sprint 5 — onze liens du README étaient morts sans que personne ne le
voie.

Utilise `git mv` (préserve l'historique) et fais la fusion **seule dans son
commit**, sans le moindre changement fonctionnel mêlé.

---

## Lot G — Sprint 6, mise en production

Rien de ce lot n'existe aujourd'hui : aucune trace de k6, Grafana ou
Prometheus dans le dépôt.

1. **Test de charge k6** : 50 tenants × 5 000 élèves. Scénarios : isolation
   RLS, vérification de QR de présence, tableau de bord analytique,
   génération de bulletins. Publie les résultats, ne les résume pas en
   « ça tient ».
2. **Pentest ciblé** : Zero-Trust (calibrer le `RiskScoreEngine`, seuil
   `score > 50`), blacklist JWT, kill-switch MPC, chaîne Merkle d'audit.
3. **Observabilité** — c'est aussi la condition de la politique de versioning
   documentée : il faut des **métriques d'usage par version d'API et par
   client**, sans quoi aucune version ne pourra jamais être retirée en
   connaissance de cause. Sentry est déjà configuré (`config/sentry.php`).
4. **Sauvegardes** : `scripts/restore-backup.sh` existe ;
   `backups/` aussi. **Teste une restauration réelle.** Une
   sauvegarde jamais restaurée n'est pas une sauvegarde.
5. **Loi 18-07** (protection des données personnelles, Algérie) :
   `docs/ANPDP_DECLARATION.md` existe. Vérifie la cohérence entre ce
   qui est déclaré et ce que le code fait réellement — durées de rétention,
   export RGPD (`ExportRgpdController`), caviardage dans la chaîne d'audit.
6. **Vérité du README** : il annonce « 607 tests ✅ 0 failures 6 skipped » et
   « 55+ modèles Eloquent » alors qu'il y en a 106. Reprends chaque chiffre et
   remplace-le par une valeur mesurée, ou retire-le.

---

## Dettes transverses à ne pas oublier

- **CSRF (point 3.1)** : non traité. Le cookie de refresh est `SameSite` et
  scopé `/api/v1/auth`, mais un double-submit token reste souhaitable.
- **`mobile/src/api/axios.js`** : non aligné sur la rotation des refresh
  tokens livrée au sprint 3. Le frontend web l'est
  (`src/api/client.js`, `axiosInstance.js`, avec rafraîchissement
  **sérialisé**) ; le mobile ne l'est pas. Aligne-le sur le même contrat.
- **38 vulnérabilités transitives Expo** sur le mobile, laissées en rapport
  non bloquant. Le frontend est à 0 depuis `npm audit fix`.

---

## À remonter à l'humain, sans trancher toi-même

1. **La licence.** Le README affiche un badge « Licence Propriétaire »
   pointant vers un fichier `LICENSE` **absent du dépôt**, tandis que
   `backend/composer.json` déclare `"license": "MIT"`. Ces deux
   affirmations n'autorisent pas les mêmes usages par des tiers. C'est une
   question juridique : pose-la, n'y réponds pas.
2. **`SECURITY.md` n'existe pas** alors que le plan le liste comme fichier
   attendu à la racine. Il faut une adresse de contact et une politique de
   divulgation — donc une décision humaine.
3. Toute suppression de données, toute modification des durées de rétention,
   tout changement de forme de l'API publique.

---

## Pièges déjà payés — ne les réapprends pas

- **`User` importait `BelongsToTenant` sans jamais l'appliquer** dans le corps
  de la classe. L'import seul suffisait à tromper la relecture humaine *et*
  une analyse statique. Vérifie toujours un trait **à l'exécution**
  (`$modele->getGlobalScopes()`), jamais par `grep`.
- **Deux listes en dur divergent toujours.** Le honeypot en déclarait 22 dans
  le service et en enregistrait 16 dans les routes ; les 6 manquants étaient
  `.env`, `admin`, `debug`, `backup`, `config`, `dump` — les cibles favorites
  des scanners. Un test comptait « 22 » en interrogeant le tableau PHP.
  Interroge toujours le réel : le routeur, la base, la réponse HTTP.
- **`withoutMiddleware()` dans un `setUp` purge les cookies** posés par
  `withCookie()`. Utilise `RateLimiter::clear('auth')` +
  `withUnencryptedCookie()`.
- **`AuditChainObserver::creating()`** ne doit recalculer `data_hash` que s'il
  a effectivement modifié le payload, sinon la chaîne naît avec un HMAC
  invalide.
- **Mesure de performance** : toute comparaison N+1 doit **chauffer les caches
  des deux côtés**. Une fausse alerte sur `/api/v1/eleves` venait des agrégats
  `eleves_stats_` recalculés d'un seul côté. Le dump SQL brut trompe : un
  `in (?, ?, …)` fait paraître « nouvelles » des requêtes d'eager loading.
- **`gh pr edit --body` échoue sur ce dépôt** (`GraphQL: Projects (classic) is
  being deprecated`), en sortant 1 sans message clair et sans rien modifier.
  Passe par l'API REST :
  `gh api -X PATCH repos/Allintelligence2024/edugest-dz/pulls/N --input corps.json`.

---

## Définition de « terminé »

- [ ] CI verte, avec les jobs backend, frontend, mobile et qualité qui
      s'exécutent réellement.
- [ ] Couverture backend ≥ 60 %, frontend ≥ 40 %, mobile ≥ 30 % — **mesurées**,
      avec cliquets bloquants au niveau atteint.
- [ ] `DETTE` vide dans `PorteeTenantModelesTest`.
- [ ] Aucun contrôleur > 350 lignes, avec un garde-fou automatique.
- [ ] `edugestdz/` fusionné, tous les chemins réécrits, tous les liens du
      README vérifiés par script.
- [ ] i18next en place, quatre langues complètes, plus d'emoji porteur de sens.
- [ ] Rapport k6 publié, restauration de sauvegarde testée pour de vrai,
      métriques d'usage par version d'API en place.
- [ ] Chaque chiffre du README mesuré ou retiré.
- [ ] `PLAN_REMEDIATION_2026.md`, `REPRISE_SESSION.md` et les journaux de
      sprint à jour, **écarts assumés inclus**. Un plan qui ne dit que les
      succès ne sert à personne.
