# Sprint 5 — Architecture & maintenabilité

Journal de travail. Ce qui a été fait, ce qui a été trouvé en chemin, et ce
qui reste ouvert. Les écarts au plan sont assumés et justifiés, pas passés
sous silence.

---

## P1-6 — Portée tenant : le constat de l'audit était inversé

L'audit demandait de supprimer « 16 checks tenant redondants » qui
« masquent les vrais bugs de scope ». Le raisonnement se tient : si un
modèle porte le trait `BelongsToTenant`, un `where('tenant_id', …)` dans le
contrôleur ne fait rien, et sa présence peut dissimuler un scope défaillant.

Avant de supprimer, j'ai vérifié la prémisse. Elle est fausse.

Sur les sept modèles visés par ces filtres, **quatre n'ont aucun scope
tenant**. Le `where('tenant_id', …)` n'était pas une redondance : c'était la
seule barrière d'isolation. Le retirer aurait ouvert une fuite entre
établissements — exactement l'inverse du but recherché.

Inventaire complet, sur les 106 modèles :

| Situation | Nombre |
|-----------|--------|
| Scopés (trait direct ou hérité de `BaseModel`) | 67 |
| Colonne `tenant_id` sans aucun scope | 21 |

### Ce qui a été fait

L'ordre a été inversé : rendre l'invariant vrai, le prouver, et ne parler de
retrait qu'ensuite.

**Trait ajouté à 8 modèles** dont les données sont strictement propres à un
établissement : `DiagnosticEleve`, `HistoriqueDiagnostic`,
`SignalementComportement`, `ConvocationParent`, `NotificationParent`,
`PlanRattrapage`, `LmsCours`, `LmsInscription`.

Le lot a été choisi prudemment. Ajouter `BelongsToTenant` ne se contente pas
de filtrer les lectures : le hook `creating` **lève une exception** si aucun
tenant n'est résolu. Un modèle instancié depuis un seeder, une commande
artisan ou un job hors contexte HTTP casse alors immédiatement. Chaque
modèle du lot a donc été vérifié sur trois points : pas de méthode
`tenant()` en conflit avec la relation du trait, aucun seeder problématique,
aucune factory dépendante.

**Test de garde** — `tests/Feature/Security/PorteeTenantModelesTest.php` :
tout modèle dont la table porte `tenant_id` doit enregistrer le scope
global, sauf entrée explicite dans l'une de deux listes.

Les deux listes sont volontairement distinctes :

- `HORS_PERIMETRE` — non scopé **par décision** : la marketplace est un
  catalogue public inter-établissements, `Role` est un référentiel lu avant
  la résolution du tenant, `TenantModule` sert l'administration super-admin.
- `DETTE` — devrait être scopé, ne l'est **pas encore** : six modèles de
  surveillance et d'examens.

Les confondre ferait disparaître la dette dans une liste d'exceptions que
plus personne n'interroge. Un second test empêche la dette de pourrir : un
modèle devenu conforme doit sortir de `DETTE`, sinon échec.

### Ce que le test a trouvé dès son premier passage

`User`. Le modèle **importait** `App\Traits\BelongsToTenant` en tête de
fichier sans jamais l'appliquer dans le corps de la classe. L'import seul
suffisait à tromper la lecture humaine — et il avait trompé mon propre
inventaire statique, qui comptait `User` parmi les modèles scopés.

C'est le genre de défaut qu'aucune relecture ne rattrape et qu'une seule
question à l'exécution règle : *le scope est-il enregistré ?*

Le scope est ici impossible : l'authentification cherche un compte par
e-mail **avant** qu'un tenant soit résolu — c'est le compte trouvé qui
détermine le tenant. Un scope fail-closed rendrait toute connexion
impossible. `User` rejoint donc `HORS_PERIMETRE`, l'import mort a été retiré,
et un docblock consigne le corollaire : **les `where('tenant_id', …)`
portant sur `User` sont les seuls filtres d'isolation des comptes.**

### Verdict sur la demande initiale

Sur 63 filtres tenant recensés dans les contrôleurs, **un seul** est
réellement redondant (`AbsenceJournaliere`, modèle scopé). Il est
**conservé** : une ligne de défense en profondeur sur un modèle correct ne
coûte rien, et le retirer ne rendrait mesurablement rien meilleur. Les
autres sont porteurs et leur suppression provoquerait une fuite.

Le vrai livrable de P1-6 n'est donc pas une suppression, c'est le test qui
empêche 21 trous de devenir 22.

---

## 5.1 — Hygiène racine

119 fichiers à la racine, dont 96 Markdown et 19 HTML. Ramenés à 5.

| Destination | Contenu | Volume |
|-------------|---------|--------|
| `docs/archive/missions/` | Journaux de mission, phases, flux | 85 |
| `docs/archive/audits/` | Audits, rapports, analyse concurrentielle | 7 |
| `docs/design/` | Maquettes HTML, schémas d'architecture | 16 |
| `docs/business/` | Étude de marché, cahier des charges | 3 |
| `docs/guides/` | Guide utilisateur, plan de réponse aux incidents | 2 |
| `backend/database/sql/` | `seed_curriculum_algerie.sql` — donnée, pas documentation | 1 |

Rien n'a été supprimé, et `git mv` préserve l'historique. Aucun fichier
déplacé n'était référencé par du code, un workflow ou une configuration —
vérifié avant de bouger quoi que ce soit.

`docs/README.md` sert d'index : rôle de chaque dossier, liste des audits,
et la commande `grep` pour fouiller les 85 journaux de mission.

### Défaut trouvé au passage : les liens du README

Le README pointait vers `docs/SECURITE.md`, `docs/ARCHITECTURE.md`,
`ANPDP_DECLARATION.md` et six autres. **Aucun de ces chemins n'existait** :
la documentation réelle vit dans `docs/`, et le dossier `docs/`
racine n'existait pas du tout avant ce sprint. Onze liens morts dans le
premier écran du dépôt. Corrigés, puis vérifiés par un contrôle qui résout
chaque lien relatif.

Ces chemins redeviendront `docs/...` après la fusion prévue au 5.2 — c'est
un renommage mécanique, pas une raison de laisser des liens cassés
aujourd'hui.

### Point ouvert : la licence → tranchée le 10 septembre 2026

Le README affichait un badge « Licence Propriétaire » pointant vers un
fichier `LICENSE` **absent du dépôt**, tandis que `backend/composer.json`
déclarait `"license": "MIT"`. Le propriétaire a tranché : **propriétaire**.
`LICENSE` rédigé à la racine (+ `SECURITY.md`, également listé comme cible
du plan), `composer.json` passé à `"proprietary"`, `package.json`
frontend/mobile à `"UNLICENSED"`. Les dépendances tierces gardent leurs
licences d'origine (`LICENSE` § 4).

---

## 5.4 — Honeypot en configuration

Le plan demandait de sortir les routes leurres du code vers
`config/security.php`. En les rassemblant, l'écart est apparu.

Les chemins étaient écrits en dur **à deux endroits** :

- `HoneypotService::$routesLeurres` — 22 chemins,
- `routes/api/honeypot.php` — 16 routes.

Les deux copies avaient divergé. Les six absents :
`.env`, `admin`, `debug`, `backup`, `config`, `dump`. C'est-à-dire les six
chemins que tout scanner automatisé tente en premier. Le dispositif était
aveugle précisément là où il devait voir.

Le plus instructif est qu'un test existait :

```php
public function test_honeypot_22_routes(): void
{
    $honeypot = app(HoneypotService::class);
    $this->assertCount(22, $honeypot->getRoutesLeurres());
}
```

Il comptait le tableau du service. Pas les routes servies. Il mesurait
l'intention, et il était vert.

### Ce qui a été fait

- `config/security.php` porte la liste unique (`nom => chemin`) et un
  drapeau `actif`.
- `routes/api/honeypot.php` boucle sur la configuration ; plus un seul
  chemin en dur. Les noms de route `honeypot.*` sont préservés :
  `RbacCoverageTest` s'en sert pour distinguer un leurre d'une route métier
  non protégée.
- `HoneypotService::getRoutesLeurres()` lit la même configuration.

Collisions vérifiées avant d'enregistrer les six manquants : `/v1/cron`,
`/v1/ping` et `/v1/health/check` ne recouvrent respectivement ni
`cron/{tache}`, ni `health/ping`, ni `health`. Les leurres sont enregistrés
en dernier, donc une collision rendrait le leurre inatteignable sans casser
la route métier — un échec silencieux, et faussement rassurant. D'où le test
dédié.

`HoneypotRoutesTest` interroge le routeur et **frappe réellement** les 22
chemins. Il vérifie que le 404 est indiscernable d'un chemin inexistant
(une réponse distinctive apprendrait au scanner qu'il est repéré), qu'aucun
leurre ne masque une route métier, que service et routeur exposent la même
liste, et que le drapeau `actif` coupe effectivement les leurres — une
bascule sans effet est pire qu'absente, elle donne l'illusion du contrôle.

---

## 5.5 — Versioning de l'API

`docs/VERSIONING_API.md`. Le document décrit d'abord l'état réel
— `v1` seule, versioning par URI, ~439 routes, **aucun** mécanisme de
dépréciation — puis fixe la politique : critères de rupture, en-têtes
`Deprecation` (RFC 9745) et `Sunset` (RFC 8594), `410 Gone` après retrait.

Deux points méritent d'être signalés hors du document :

**L'application mobile est la contrainte dimensionnante.** Un parent qui n'a
pas ouvert l'application depuis trois mois utilise encore la version d'il y
a trois mois. Toute fenêtre de retrait calculée sur le rythme du frontend
est fausse. La fenêtre de six mois est donc **subordonnée** à un critère
mesuré : trois mois après que la version mobile ciblant la nouvelle API
dépasse 95 % du parc.

**Ce critère n'est pas mesurable aujourd'hui.** Il n'existe aucune métrique
d'usage par version. Le document le dit explicitement plutôt que d'énoncer
une règle inapplicable : l'instrumentation relève de l'observabilité du
Sprint 6, et conditionne tout retrait.

---

## 5.2 — Fusion de `edugestdz/` à la racine

Faite le 2026-09-10, sur la branche `arena/01a08b2d-edugest-dz`, en deux
commits dédiés — aucune PR en vol sur nos branches (la #82 est mergée, la
#80 est un vestige de juillet sur `develop`) :

1. `68e4f1d` — renommages purs : 1 052 `git mv`, zéro contenu modifié.
   L'historique est préservé fichier par fichier.
2. `64c53c2` — réécriture des références de chemins dans 23 fichiers.

Le découpage en deux commits rend la relecture possible : le premier se
vérifie d'un `git show --find-renames --stat`, le second contient toute la
partie discutable.

### Ce qui a été vérifié avant de bouger

- **Collisions** : `docs/`, `scripts/` et `.github/` existaient des deux
  côtés — aucun nom de fichier en commun, fusion directe.
- **Chemins relatifs** : `docker-compose*.yml`, `vercel.json` et le
  `Makefile` ne naviguent qu'en relatif (`./backend`, `frontend/…`) : ils
  fonctionnent inchangés à la racine puisque leurs cibles ont déménagé avec
  eux.
- **Inventaire par `grep`, pas à la main.** Le prompt de reprise annonçait
  « 13 références dans 6 fichiers » ; la mesure donne **23 fichiers**, dont
  `ci.yml` seul porte 9 occurrences. Le même inventaire a évité deux faux
  positifs : les noms d'images Docker (`edugestdz/backend:latest`) et les
  documents d'archive, volontairement inchangés.
- **Liens du README** : revérifiés par script après réécriture — seul
  `LICENSE` reste mort, et c'est le commit suivant.

### Deux suppressions, les deux justifiées

- `edugestdz/.github/workflows/frontend-ci.yml` — le workflow inerte du
  P1-8. Son contenu (lint + Vitest + couverture) est repris et dépassé par
  le job `frontend` de `.github/workflows/ci.yml`.
- `docs/ci-qualite.patch` — appliqué puis oublié : la procédure
  d'application manuelle prévoyait `rm docs/*.patch`, trois patchs sur
  quatre avaient été retirés. Celui-ci restait comme poids mort.

### Point de vigilance : les workflows

`.github/workflows/` n'est pas poussable depuis le bac à sable (permission
`workflows` manquante, retestée à chaque sprint). Les 11 chemins des trois
workflows sont donc livrés en `docs/fusion-workflows.patch`, vérifié par
`git apply --check` et YAML parsé après application. **La CI est rouge
entre la fusion et l'application du patch** : les jobs cherchent
`edugestdz/backend` qui n'existe plus. C'est la même procédure que les
quatre patchs précédents, à appliquer depuis un poste normal :

```bash
git apply docs/fusion-workflows.patch
rm docs/fusion-workflows.patch
git add -A && git commit -m "ci: chemins après fusion 5.2" && git push
```

Second point de vigilance, hors dépôt : si le projet Vercel a son
*Root Directory* réglé sur `edugestdz`, le tableau de bord doit être
repassé sur la racine — `vercel.json` a déménagé mais le réglage distant,
lui, ne se versionne pas.

### Racine après fusion

```
edugest-dz/
  backend/ frontend/ mobile/ docs/ scripts/
  docker/ nginx/ backups/
  docker-compose{,.prod,.selfhosted}.yml  vercel.json  Makefile
  install.sh deploy.sh update.sh server-setup.sh setup-vpn.sh run.ps1
  README.md CHANGELOG.md CONTRIBUTING.md
  PLAN_REMEDIATION_2026.md REPRISE_SESSION.md   ← archive en fin de remédiation
```

---

## Non entamé

| Point | Raison |
|-------|--------|
| **5.3** Découpe des contrôleurs > 350 lignes | ✅ Fait le 10 sept. — 9/9 (Budget, Stock, Entretien, Transport, Cantine, Lms, PaiementEnLigne, Eleve, Auth), `scripts/controleurs-baseline.txt` vide. |
| **5.6** i18next + icônes lucide | Phases 1 et 2 faites le 10 sept. (socle i18next + emoji → lucide, § 5.6 ci-dessous : 515 occurrences / 55 fichiers → **0**, garde-fou verrouillé à 0/0, tests 134/134). Reste : phase 3 (littéraux → `t()`, 96 fichiers). |
| Coverage backend 45 → 60 % | **Mesuré : 60,71 %** (run `main`, annotation clover). Seuil rendu réellement bloquant à 45 ; relèvement à 60 à décider sur mesure post-5.3. |
| Tests mobile (auth, présence, paiement) | Report du Sprint 4, toujours ouvert. |

---

## 5.6 — i18next, phase 1 : le socle (10 sept.)

Le point 5.6 demandait « migrer vers i18next + remplacer les emoji JSX par
lucide ». Mesure avant découpage, comme d'habitude :

| Mesure (10 sept., outillage `grep`/python, pas à la main) | Valeur |
|---|---|
| Dicts `src/lang/{fr,ar,en,dz}.json` | 208 clés chacun (+1 en dz), 4 placeholders `{name,from,to,total}` |
| Appels `t(` en production | **7**, sans paramètres — le reste du front est en français codé en dur |
| Fichiers avec littéraux français accentués | **96 / 154** js/jsx |
| Emoji en dur hors tests | **515 occurrences / 55 fichiers** (541 / 65 avec les tests), 101 distincts |
| `lucide-react` | déjà en dépendance (`^0.460.0`), `i18next` absent |

La migration complète (socle + 55 fichiers d'emoji + 96 fichiers de
littéraux) ne tient pas dans un lot relisable. Découpage assumé :

- **Phase 1 (fait)** : socle i18next + façade compatible + garde-fou.
- **Phase 2 (fait le 10 sept.)** : emoji → `lucide-react` (+ `aria-label`),
  table ci-dessous — détail dans « Ce qui a été fait (phase 2) ».
- **Phase 3 (lot 1 fait le 10 sept.)** : littéraux français → `t()` —
  détail dans « Ce qui a été fait (phase 3, lot 1) ». Restent ~60 fichiers
  (pages et modales métier), cliquet `fr-literals-guard.test.js` en place.
- Mobile (`mobile/src/context/I18nContext.js`, ses propres `lang/`) : hors
  scope, noté pour le Sprint 6.

### Ce qui a été fait (phase 1)

- `frontend/src/i18n.js` (nouveau) : init i18next 26 + react-i18next 17,
  ressources = les 4 JSON existants, `fallbackLng: fr`, `useSuspense: false`,
  RTL via `document.dir`/`lang` (init + `languageChanged`), persistance
  `localStorage`, helpers `formatNumber`/`formatDate` (Intl).
- `frontend/src/context/I18nContext.jsx` : réécrit en **façade à API
  inchangée** (`{ lang, t, changeLang, isRTL, LANG_META }`, + les 2 helpers).
  Zéro composant modifié, y compris les 12 tests de pages qui montent le
  provider. Écart sémantique documenté : `t(clé, { count })` déclenche
  désormais les pluriels CLDR (aucun appelant actuel).
- Placeholders `{x}` → `{{x}}` dans les 4 JSON (16 remplacements, diff 8+/8-,
  format préservé). Vérifié : `I18nContext` est le seul importeur des
  `lang/` côté frontend.
- Darija : pluriels = règle arabe CLDR, réutilisée via
  `pluralResolver.getRule('ar')` (pas de CLDR réécrit à la main) ; chiffres
  latins (`ar-DZ-u-nu-latn`, usage algérien). Les deux choix sont gardés par
  `src/i18n.test.js` (9 tests : init, interpolation, repli fr, clé manquante,
  RTL, pluriels fr, pluriels dz 6 formes, formatters, `baseLang`).
- `src/emoji-guard.test.js` : **cliquet bloquant** (plafonds 515 occ. /
  55 fichiers à la création ; **verrouillés à 0/0 par la phase 2**). Mis en
  test vitest plutôt qu'en lint parce que le job CI `lint` est
  `continue-on-error` : en lint, le garde ne garderait rien.
- `package.json` + `package-lock.json` : `i18next ^26.4.2`, `react-i18next
  ^17.0.13` (pairs vérifiés : react-i18next 17 exige i18next ≥ 26.2).
  Lock régénéré (`--package-lock-only`, +67 lignes, `resolved`+`integrity`
  vérifiés) pour que `npm ci` reste vert.
- Vérifié sans `node_modules` : esbuild (5 fichiers syntaxiquement valides),
  compteur du garde rejoué à l'identique (515/55), AST PHP du correctif
  tenant (paragraphe suivant). Build + tests : CI après application du patch
  workflows.

Correctif embarqué (bug réel, trouvé pendant le Lot C) : `tenants` n'a pas
de colonne `nom` (migration `0001` : `nom_etablissement` uniquement), donc
`complete2fa` et `me` renvoyaient `"nom": null`. Passés à
`nom_etablissement`, comme `login` ; idem la ligne console de
`RelancesEcheanceCommand`. Non-régression : le frontend ne lit `tenant.nom`
nulle part (`grep` vide). Fausse alerte refermée au passage : `complete2fa`
**a** sa route (`POST auth/2fa/complete`, groupe public) — mon `grep -o ",
'[a-zA-Z]*'"` l'avait manquée à cause du `2` (`[a-zA-Z]` sans chiffres).
Leçon : ce pattern d'audit est à bannir, utiliser `[a-zA-Z0-9_]*`.

### Phase 2 — table emoji → lucide (appliquée le 10 sept.)

Top occurrences mesurées. Noms lucide récents ; le build CI tranchera les
renommages (`BarChart3`→`ChartColumn`, etc.). `aria-label` en français sur
chaque icône porteuse de sens.

| Occ. | Emoji | Proposition lucide | Note |
|---|---|---|---|
| 60 | ✅ | `CircleCheck` | succès, `aria-label="Valide"` |
| 26 | ➕ | `Plus` | |
| 17 | 🏫 | `School` | |
| 16 | ❌ | `CircleX` | |
| 15 | ⚠️ | `TriangleAlert` | |
| 15 | 👨 | contextuel | parent/enseignant → `User`, selon écran |
| 14 | ✕ | `X` | fermer/retirer (ne pas confondre avec ❌) |
| 14 | 📊 | `ChartColumn` | (ancien `BarChart3`) |
| 14 | ✏️ | `Pencil` | |
| 11 | 📋 | `ClipboardList` | |
| 11 | 🎓 | `GraduationCap` | |
| 11 | 💰 | `Wallet` | (ou `Banknote` selon contexte) |
| 11 | 👦 | contextuel | élève → avatar/`Smile`, selon écran |
| 11 | 💾 | `Save` | |
| 10 | 📚 | `BookOpen` | |
| 10 | 🚨 | `Siren` | |
| 9 | 🎉 | `PartyPopper` | |
| 9 | 🔴 | `Circle` + `fill` | pastille statut (ou span CSS) |
| 8 | 🗑️ | `Trash2` | |
| 7 | 🔔 | `Bell` | |
| 7 | ✓ | `Check` | (distinct de ✅ : coche simple) |
| 7 | 🔄 | `RefreshCw` | |
| 7 | 👤 | `User` | |
| 6 | 📝 | `NotebookPen` | (repli : `FileText`) |
| 6 | 📄 | `FileText` | |
| 6 | 📍 | `MapPin` | |
| 6 | 📱 | `Smartphone` | |
| 5 | 🔍 | `Search` | |
| 9 | ★ ☆ ⭐ | `Star` (+ `fill`) | notation ; ★ plein, ☆ vide |
| 5 | 📅 | `CalendarDays` | (repli : `Calendar`) |
| 5 | 👥 | `Users` | |

Suite du top : ⚙️ `Settings`, 👁️ `Eye`, 👷 `HardHat`, ℹ️ `Info`,
📦 `Package`, 💳 `CreditCard`, 👩 contextuel, 📷 `Camera`, 🔓 `LockOpen`,
🎫 `Ticket`, 🍽️ `UtensilsCrossed`, 📧 `Mail`, ☀️/🌙 `Sun`/`Moon`,
▾ `ChevronDown`. Cas spécial : **drapeaux 🇫🇷🇩🇿🇬🇧** (sélecteur de langue) —
lucide n'a pas de drapeaux ; trancher en phase 2 (codes `FR/AR/EN/DZ`
recommandés, ou images). Règle de fin : baisser les plafonds du garde-fou au
fur et à mesure, fichier par fichier.

### Ce qui a été fait (phase 2, 10 sept.)

**515 occurrences / 55 fichiers `.jsx` → 0.** Recensement par la regex du
garde-fou (`emoji-guard.test.js`), pas à la main. Codemod jscodeshift
(mapping ≈ 100 clusters graphèmes, fusion d'imports) puis passes manuelles
outillées pour les cas non mécanisables.

Décisions de conversion :

- **Accessibilité** : icône seule (bouton sans texte) → `aria-label` en
  français sur l'icône ; icône suivie d'un texte adjacent → `aria-hidden="true"`
  (le texte porte le sens). Recherche/effacement de la `SearchBar` : label
  déplacé sur le bouton (`aria-label="Effacer la recherche"`, icône masquée).
- **Tailles** : `text-4xl+`→36, `text-2xl`→24, `xl/lg`→18, défaut→16,
  `xs/sm` et cellules de tableaux→14 px ; `fontSize` CSS explicite → même
  valeur en px.
- **ZWJ** : enseignants 👨‍🏫/👩‍🏫→`Presentation`, élèves 👨‍🎓→`GraduationCap`,
  famille 👨‍👩‍👦→`Users`. ⏳ statique→`Hourglass`, animé→`LoaderCircle`.
- **Podium** 🥇🥈🥉→`Medal` colorée (or `#FFD700`, argent `#C0C0C0`,
  bronze `#CD7F32`). **Pastilles** 🔴🔵🟡🟢⚪→`Circle` + `fill`/`color` par
  site. **Notation** ★→`Star` avec `fill` conditionnel.
- **Drapeaux** du sélecteur de langue → codes `FR/AR/EN/DZ` (décision
  ci-dessus) dans `LANG_META` de `I18nContext.jsx`.
- Noms vérifiés contre le `lucide-react` **0.460.0 verrouillé** (les alias
  historiques `CheckCircle`, `BarChart3`, `AlertTriangle`… y vivent encore) ;
  0 dépendance ajoutée.
- Refactors induits : `msg.includes('✅')` → état booléen dans 4 pages ;
  toasts 🎉 → `toast.success(msg, { icon: <PartyPopper/> })` (API `icon:`
  de react-hot-toast v2) ; icônes des cartes `<option>` retirées (HTML
  interdit dans `<option>`).

Vérifications : esbuild sur les 154 `.jsx` → 0 erreur ; `vitest run` →
**134/134 verts** (23 assertions dans 9 fichiers de tests cherchaient le
texte avec emoji — adaptées, ex. `getByText('Élèves')`, ou
`getByRole('heading', …)` pour distinguer le bouton du titre de modal) ;
ESLint sans nouvelle alerte (−8 `no-unused-vars` au passage). Garde-fou
emoji **verrouillé à 0/0** : toute régression casse la CI.

Bonus i18next : correctif des **pluriels darija** — i18next ≥ 25 a supprimé
`PluralResolver.addRule` (l'appel silencieux ne faisait rien, et 'dz' désigne
le dzongkha côté `Intl.PluralRules`, une seule catégorie) → délégation
`getRule('dz')` → règle de l'arabe dans `i18n.js`. Le test « pluriels darija =
règles arabes », rouge depuis la phase 1, repasse au vert.

### Ce qui a été fait (phase 3, lot 1 — 10 sept.)

**Lot 1 : le chrome de l'application** — ce qui s'affiche sur toutes les
pages. 13 fichiers vivants convertis, 56 nouvelles clés × 4 langues
(208 → 264 clés par dictionnaire) :

- `layout/Topbar.jsx` : la carte `PAGE_META` (titres + fils d'Ariane de 26
  routes) porte désormais des clés i18n, traduites au rendu ; date du jour
  localisée via `formatDate(date, lang, options)` (était `fr-DZ` en dur) ;
- `SearchModal` (types, placeholder, hint, « aucun résultat », pluriel CLDR
  du compteur), `ui/QuickActions`, `ui/DiagBadge`, `ui/OfflineBanner`
  (dont pluriel des actions en attente), `ui/SchoolBadge`, `common/Pagination`
  (réutilise la clé `showing` existante), `common/FilterBar` (`reset`),
  `common/DataTable` + `ui/Table` (défaut de prop `null` → `t('no_data')`
  au rendu), `ModuleProtectedRoute` (message module désactivé interpolé) ;
- hors composants React : `hooks/useApi.js` (toasts erreur/succès) et
  `api/axiosInstance.js` (« Erreur réseau » → clé `error_network`) passent
  par l'instance i18next directement — pas de hook hors rendu.

Deux garde-fous nouveaux :

- `src/i18n-parity.test.js` : les 4 dictionnaires exposent le même jeu de
  clés (les `_`-préfixées comme `_comment` sont des métadonnées, ignorées)
  et aucune traduction vide. Écart hérité détecté au passage : dz.json
  portait `_comment` en plus — documenté, pas une régression.
- `src/fr-literals-guard.test.js` : cliquet sur le nombre de fichiers avec
  littéraux français (accents hors commentaires), **plafond 60** après ce
  lot. Chaque lot suivant le baisse. Exclusions documentées : tests, dicts,
  socle i18n, et code mort (`components/Header.jsx`, `components/DataTable.jsx`,
  `services/api.js` — plus aucun import nulle part ; suppression notée pour
  un lot de nettoyage, on ne traduit pas du code qui ne s'affiche pas).

Vérifications : `vitest run` → **137/137 verts** (5 fichiers de tests de
composants enveloppés dans `<I18nProvider>` — ils montaient `render` nu et
auraient levé `useI18n must be used within <I18nProvider>` ; 2 assertions
adaptées : « Aucune donnée » → « Aucune donnée disponible », résumé de
pagination désormais une seule phrase) ; esbuild 154 `.jsx` → 0 erreur ;
ESLint sans nouvelle alerte ; clés littérales référencées vérifiées contre
fr.json (les pluriels `_one`/`_other` exceptés, résolution CLDR normale).
