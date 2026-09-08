# Sprint 4 — Qualité & tests

> Date : 2026-09-08 · Branche : `arena/01a080e0-edugest-dz` · PR #82
> Fait suite aux sprints 1 à 3 (PR #81, mergée le 2026-09-08).

Ce document décrit ce qui a été livré, ce qui s'en écarte et pourquoi.
Les écarts sont assumés et argumentés : un plan qu'on suit sans le
discuter produit des seuils décoratifs, ce que ce sprint corrige
justement à plusieurs endroits.

---

## 1. Détection des N+1 — rendue bloquante

`QueryMonitor` existait depuis longtemps et comptait déjà les requêtes
d'un cycle HTTP. Il se contentait d'un `Log::warning` avec des seuils
codés en dur : personne ne lisait ce log, et aucune régression n'a jamais
été arrêtée par lui.

**Ce qui change**

| Élément | Rôle |
|---|---|
| `config/performance.php` | Seuils et budgets par endpoint — source unique partagée par le middleware et les tests. |
| `app/Http/Middleware/QueryMonitor.php` | Lit la configuration, expose `X-Query-Count` et `X-Query-Budget`. |
| `tests/Support/BudgetRequetes.php` | Deux assertions : plafond absolu et **croissance nulle**. |
| `tests/Feature/Performance/BudgetRequetesEndpointsTest.php` | Applique les deux à `eleves`, `factures`, `groupes`, `enseignants`. |

**La mesure différentielle plutôt que le nombre magique.** Un plafond
absolu ne détecte pas un N+1 sur une petite collection, et se périme dès
qu'une requête légitime s'ajoute. On mesure donc le même endpoint avec 1
puis 5 enregistrements : si le nombre de requêtes croît, la relation
n'est pas chargée en amont. C'est la définition littérale d'un N+1, et
elle ne dépend d'aucune valeur arbitraire.

### Deux enseignements de mise au point

**L'écouteur `DB::listen` était réenregistré par requête.** Sans effet en
production — un processus sert une requête — mais dans une suite de
tests qui enchaîne des centaines d'appels dans le même processus, les
closures s'accumulaient. Corrigé, puis re-cassé autrement : un drapeau
statique `déjà enregistré` ne tient pas, car **chaque test PHPUnit
reconstruit l'application, donc le dispatcher d'événements**. L'écouteur
du test précédent disparaît avec l'ancien conteneur pendant que le
drapeau reste vrai : `X-Query-Count` renvoyait 0 à partir du deuxième
test. On mémorise désormais l'instance observée, en référence faible.

**Le premier « N+1 » détecté n'en était pas un.** La CI a signalé
`/api/v1/eleves` passant de 7 à 10 requêtes. En réalité `EleveObserver`
purge `eleves_stats_{tenant}` à chaque création : la mesure sous charge
payait la reconstitution des trois agrégats de statistiques. On comparait
un cache chaud à un cache froid. Une seconde chauffe après la montée en
volume rétablit la symétrie.

Ce diagnostic n'a été possible que parce que le message d'échec donne le
**delta par table** (`eleves 1→4`, les autres stables). Le dump SQL brut,
lui, était trompeur : la clause `in (?, ?, …)` change de texte avec le
nombre de lignes, faisant passer les requêtes *eager* correctes pour des
requêtes nouvelles.

---

## 2. Canal de diagnostic CI — annotations découpées

Les logs GitHub Actions restent intéléchargeables depuis l'environnement
de travail (le CDN renvoie `EOF`), et PHP n'est pas installable
localement : les annotations sont le seul retour exploitable.

Or **GitHub tronque une annotation aux alentours de 255 caractères**. Le
premier échec de ce sprint est arrivé coupé en plein milieu d'un nom de
colonne. `CiDiagnosticExtension` découpe désormais chaque message en
quatre annotations numérotées au plus (`ECHEC 1/2`, `ECHEC 2/2`).

Corollaire adopté dans les assertions : **l'information discriminante en
premier**, le détail ensuite. Les messages de budget donnent d'abord les
compteurs, puis le delta par table, puis trois empreintes SQL courtes.

---

## 3. Frontend — deux défauts trouvés par les tests

Les tests ajoutés sur les pages critiques ont révélé deux régressions
silencieuses touchant **six pages**.

**`SearchBar` : la recherche ne fonctionnait sur aucune page de liste.**
Le composant attend `onSearch`, les pages passent `value` + `onChange`.
La frappe déclenchait `TypeError: onSearch is not a function` **dans le
timer d'anti-rebond** — une exception asynchrone, donc aucune trace à
l'écran — et la requête n'était jamais émise. Pages concernées : Élèves,
Enseignants, Groupes, Matières, Notes, Salles.

**`FilterBar` : la barre de filtres ne rendait rien.** Le composant
n'affichait un `<select>` que si `filter.type === 'select'` ; aucune page
ne renseignait ce champ, et plusieurs passaient leurs options en chaînes
brutes plutôt qu'en `{ value, label }`. Quatre pages affichaient donc une
barre de filtres vide. Au passage, la sélection écrasait les autres
filtres au lieu de les fusionner.

**Un test existant validait l'anomalie.** `GroupesPage > renders niveau
filter` cherchait le texte « Niveau » — qui existe aussi en **en-tête de
colonne**. Il passait donc sur un tableau alors que la barre de filtres
était absente. Réécrit sur le rôle `combobox`.

> Réflexe à conserver, déjà rencontré au Sprint 2 : un test vert n'est une
> preuve que si l'on sait *ce qu'il regarde*. Ici il regardait autre chose.

**Vulnérabilités.** `npm audit` a remonté 8 failles dont 7 hautes sur
`react-router-dom` (redirection ouverte, XSS, déni de service). Corrigées
dans la plage semver existante, sans changement de code.

**Couverture.** 79 → **122 tests**, 13 → 21 fichiers, 11 pages critiques
couvertes (Login, Mot de passe oublié, Dashboard, Élèves, Enseignants,
Groupes, Notes, Présences, Absences, Bulletins, Factures).

---

## 4. Analyse statique et audit de sécurité

| Livrable | État |
|---|---|
| `edugestdz/backend/phpstan.neon` | Niveau 6, prêt. |
| `scripts/analyse-statique.sh` | Installe Larastan à la volée puis analyse. |
| `scripts/audit-securite.sh` | `composer audit`, `npm audit`, Gitleaks — exécute tout avant de conclure. |
| `.gitleaks.toml` | Règles maison (mot de passe historique, clés HMAC, DSN Postgres) et exclusions explicites. |

**Larastan n'est pas déclaré dans `composer.json`.** L'y ajouter
invaliderait `composer.lock`, que l'environnement de travail ne peut pas
régénérer faute de PHP. Le paquet est donc installé à la volée par le
script et par la CI. Dès qu'un poste disposant de PHP reprend la main :

```bash
cd edugestdz/backend
composer require --dev larastan/larastan:^3.0
composer analyse -- --generate-baseline
```

**PHPStan reste non bloquant** tant que la baseline n'est pas générée.
Le niveau 6 sur une base Laravel existante remonte des centaines de
défauts hérités ; bloquer la branche principale là-dessus n'apprend rien
à personne et se contourne en désactivant la garde — le pire des deux
mondes. La marche à suivre est de figer l'existant puis d'exiger zéro
nouvelle erreur.

**Gitleaks analyse l'arbre de travail, pas l'historique** (`--no-git`).
L'historique contient les mots de passe littéraux retirés au Sprint 3 ;
les scanner en boucle signalerait éternellement des secrets déjà
révoqués. La réécriture d'historique est un chantier distinct
(`PLAN_REMEDIATION_2026.md`, § 5).

---

## 5. Unification de la CI — `docs/ci-qualite.patch`

**Le frontend n'était pas testé en intégration continue.** Les tests
Vitest existaient, mais `edugestdz/.github/workflows/frontend-ci.yml`
n'est lu par personne : GitHub ne considère que `.github/workflows/` à la
racine du dépôt. Les 79 tests annoncés verts l'étaient sur un poste, pas
en CI.

Le patch ajoute deux jobs à `.github/workflows/ci.yml` :

- **`frontend`** — `npm ci`, lint (non bloquant), `npm run test:coverage`
  (le cliquet n'est opposable que si la couverture est calculée), build de
  production ;
- **`qualite`** — `composer audit`, `npm audit` frontend (bloquant),
  `npm audit` mobile (rapport seul), Gitleaks, PHPStan (non bloquant).

Le mobile reste en rapport seul : le toolchain Expo traîne 38
vulnérabilités transitives dont la correction impose des ruptures de
version. Le chiffre est visible, le blocage attend la remontée d'Expo.

### Ordre d'application des quatre patches

Ils sont empilés et doivent être appliqués dans cet ordre. La séquence a
été vérifiée (`git apply --check` sur les trois, YAML parsé après coup) :

```bash
git apply edugestdz/docs/ci-secrets.patch
git apply edugestdz/docs/pre-deploy-secrets.patch
git apply edugestdz/docs/ci-qualite.patch
cp    edugestdz/docs/deploy.yml.desactive.patch .github/workflows/deploy.yml
rm    edugestdz/docs/*.patch
git add -A && git commit -m "ci: secrets hors du code, gardes sécurité et unification qualité" && git push
```

Rappel : ces fichiers restent non poussables depuis l'environnement de
travail. Le bac à sable achemine le trafic GitHub via une application
dépourvue de la permission `workflows` ; la restriction a été retestée le
2026-09-08 et tient toujours (`refusing to allow a GitHub App to create or
update workflow .github/workflows/ci.yml`).

---

## 6. Couverture — deux paliers, deux traitements

**Backend : palier porté de 30 % à 45 %, bloquant** (dans le patch CI).
La mesure et le verdict sont séparés : l'étape de verdict republie le
pourcentage mesuré en annotation et dans le résumé de job. Sans cela un
échec de couverture se résume à `exit 1`, sans savoir s'il manque un
point ou vingt — précisément le genre d'échec opaque que ce dépôt ne peut
pas se permettre.

> **À vérifier au premier passage.** Le palier de 45 % n'a pas pu être
> mesuré : il n'existe aucun moyen d'exécuter PHPUnit depuis
> l'environnement de travail, et le patch n'est pas poussable. Si la CI
> rougit, le pourcentage réel figure dans l'annotation et `--min` s'ajuste
> sur une ligne.

**Frontend : cliquet à hauteur du réel.** Les seuils déclarés étaient à
70 % — jamais atteints ni vérifiés, puisque `npm run test` ne calcule pas
la couverture. Ils sont ramenés au niveau mesuré, très légèrement en
dessous :

| Métrique | Mesuré | Palier |
|---|---|---|
| Lignes / instructions | 18.85 % | 18 % |
| Branches | 54.92 % | 52 % |
| Fonctions | 35.25 % | 34 % |

**Écart assumé au plan**, qui visait 40 % de lignes au Sprint 4. Un seuil
tenu vaut mieux qu'un seuil affiché : celui-ci interdit toute régression
dès maintenant, ce que 70 % ne faisait pas. Atteindre 40 % suppose de
couvrir une trentaine de pages supplémentaires — c'est un lot de travail
en soi, reporté au Sprint 5.

---

## 7. Ce qui reste ouvert

- **Couverture backend réelle** : inconnue, à relever au premier passage
  du patch CI.
- **Baseline PHPStan** : à générer sur un poste disposant de PHP, puis
  rendre l'étape bloquante.
- **Couverture frontend 40 %** : ~30 pages à couvrir.
- **Mobile** : aucun test en CI, 38 vulnérabilités transitives Expo,
  `mobile/src/api/axios.js` toujours pas aligné sur la rotation des
  jetons (hérité du Sprint 3).
- **P1-6, checks tenant redondants** : 16 occurrences repérées dans les
  contrôleurs. Non traité — la suppression n'est sûre que si le modèle
  visé utilise bien `BelongsToTenant`, ce qui se vérifie modèle par
  modèle. À faire avec un test de garde qui l'atteste, pas à la main.
- **CSRF** (point 3.1 du plan) : toujours non traité.
