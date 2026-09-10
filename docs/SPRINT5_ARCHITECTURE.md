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
| **5.3** Découpe des contrôleurs > 350 lignes | Budget fait (442 → 273, 3 services). Restent 8 contrôleurs, voir `scripts/controleurs-baseline.txt`. |
| **5.6** i18next + icônes lucide | Non entamé. |
| Coverage backend 45 → 60 % | **Mesuré : 60,71 %** (run `main`, annotation clover). Seuil rendu réellement bloquant à 45 ; relèvement à 60 à décider sur mesure post-5.3. |
| Tests mobile (auth, présence, paiement) | Report du Sprint 4, toujours ouvert. |
