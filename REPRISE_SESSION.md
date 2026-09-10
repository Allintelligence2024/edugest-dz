# Reprise de session — EduGest DZ

> Document de passation. Dernière mise à jour : 2026-09-08.

---

## Où en est le travail

Branche : **`arena/01a080e0-edugest-dz`**.
Pull request : **[#82](https://github.com/Allintelligence2024/edugest-dz/pull/82)** — Sprints 4 et 5, ouverte.

**La PR #81 (sprints 1 à 3) a été mergée** dans `main` le 2026-09-08, commit
`addd705`. La CI backend qui restait à relever au moment de la passation
précédente est **verte** sur `main`. Il n'y a plus rien à vérifier de ce côté.

Sprints 1, 2, 3 livrés. Sprint 4 terminé — voir
`edugestdz/docs/SPRINT4_QUALITE.md`. **Sprint 5 entamé** : P1-6 (reporté du
Sprint 4), 5.1 hygiène racine, 5.4 honeypot en configuration, 5.5 versioning
de l'API — voir `edugestdz/docs/SPRINT5_ARCHITECTURE.md`.

Deux enseignements de ce sprint méritent d'être lus avant de continuer :

1. **La prémisse d'un point d'audit peut être fausse.** P1-6 demandait de
   supprimer des « checks tenant redondants » ; ils n'étaient pas redondants,
   ils étaient la seule isolation de quatre modèles non scopés. Les supprimer
   aurait créé la fuite que le point prétendait prévenir.
2. **Un test peut mesurer l'intention au lieu du réel.** Un test vert
   affirmait « 22 routes leurres » en comptant un tableau PHP, pendant que le
   routeur n'en servait que 16.

### État de la CI

| Job | État |
|---|---|
| CI backend (`main`) | **vert** |
| CI backend (PR #82) | **vert** |
| Pre-Deploy Smoke Tests | **vert** |
| Vercel | **vert** |
| Frontend (Vitest, local) | **122/122 vert** — *pas encore exécuté en CI, voir plus bas* |
| CD — Deploy Production | **rouge** — attendu, voir plus bas |

**`CD — Deploy Production` échoue sur `main`** à l'étape « Deploy via SSH » :
le workflow déploie encore vers un serveur SSH abandonné au profit de Vercel.
C'est exactement ce que corrige `deploy.yml.desactive.patch`, non poussable
depuis le bac à sable. Ce rouge est connu et sans conséquence, mais il pollue
le tableau de bord ; l'application des patches le fait disparaître.

---

## Ce qu'il reste à faire

1. **Appliquer les 4 patches de workflow** (voir « Action manuelle »). C'est
   le point bloquant le plus rentable : il éteint le rouge du CD, active les
   gardes sécurité du Sprint 3 **et** met enfin le frontend sous CI.
2. Relever la **couverture backend réelle** au premier passage du nouveau
   palier (45 %), puis ajuster si nécessaire — le pourcentage mesuré est
   republié en annotation, précisément pour rester lisible.
3. Générer la **baseline PHPStan** sur un poste disposant de PHP, puis rendre
   l'étape bloquante.
4. Poursuivre le Sprint 5 : **5.2** fusion de `edugestdz/` à la racine (sur
   un commit dédié, aucune PR en vol), **5.3** découpe des contrôleurs de
   plus de 350 lignes, **5.6** i18next et icônes lucide.
5. Poursuivre : couverture frontend vers 40 %, tests mobile, CSRF.
6. **Trancher la question de la licence** — décision du propriétaire, pas de
   l'outillage : le README annonce « Propriétaire » et pointe vers un fichier
   `LICENSE` absent, tandis que `composer.json` déclare `MIT`.
7. Résorber la liste `DETTE` de `PorteeTenantModelesTest` : six modèles de
   surveillance et d'examens portent `tenant_id` sans scope.

---

## Contraintes de l'environnement — toujours valables

Retestées le 2026-09-08. Les trois tiennent.

### PHP est indisponible localement

`apt-get install php-cli` échoue, et les binaires statiques ne sont pas
récupérables (les *release assets* GitHub transitent par
`release-assets.githubusercontent.com`, bloqué au niveau TLS).

**Aucun test backend ne peut tourner localement. La CI est le seul moyen.**
Compter environ 4 minutes par aller-retour — et jusqu'à 20 si l'étape de
couverture Xdebug s'exécute.

**Palliatif utile** : la validité syntaxique PHP se vérifie sans PHP, avec le
parseur JavaScript `php-parser` (glayzzle). Cela n'attrape pas les erreurs de
type ou de logique, mais évite de brûler un cycle de CI sur une accolade
manquante :

```bash
mkdir -p /tmp/phpcheck && cd /tmp/phpcheck && npm i php-parser
node -e 'const e=require("php-parser"),fs=require("fs");
  const p=new e({parser:{suppressErrors:false}});
  for (const f of process.argv.slice(1)) {
    const a=p.parseCode(fs.readFileSync(f,"utf8"),f);
    console.log((a.errors||[]).length? "✖ "+f : "✔ "+f);
  }' fichier1.php fichier2.php
```

Même logique pour le YAML des workflows, avec `js-yaml`.

### Les logs GitHub Actions restent illisibles

`gh run view --log`, l'API `jobs/{id}/logs` et le zip complet renvoient tous
`EOF`. **Ne pas réessayer.**

Le contournement reste `tests/Support/CiDiagnosticExtension.php`, enregistré
dans `phpunit.xml` : chaque échec part dans le résumé de job **et** en
annotation `::error::`.

```bash
RID=$(gh run list --branch arena/01a080e0-edugest-dz --workflow "CI — EduGest DZ" --limit 1 --json databaseId -q '.[0].databaseId')
JID=$(gh api repos/Allintelligence2024/edugest-dz/actions/runs/$RID/jobs -q '.jobs[0].id')
gh api repos/Allintelligence2024/edugest-dz/check-runs/$JID/annotations \
  -q '.[] | select(.annotation_level=="failure") | "\(.title) :: \(.message)"'
```

> **Nouveau (Sprint 4) : GitHub tronque une annotation vers 255 caractères.**
> Un message détaillé arrivait coupé en plein mot — ce qui a coûté un cycle.
> L'extension découpe désormais chaque message en quatre annotations
> numérotées au plus (`ECHEC 1/2`, `ECHEC 2/2`).
>
> Corollaire pour les assertions : **mettre l'information discriminante en
> premier**. Un delta agrégé (« table `eleves` 1→4 ») vaut mieux qu'un dump
> SQL, qui non seulement se fait tronquer mais peut induire en erreur — la
> clause `in (?, ?, …)` change de texte avec le nombre de lignes et fait
> passer des requêtes *eager* correctes pour des requêtes nouvelles.

### Les workflows ne sont pas poussables

Retesté le 2026-09-08, la restriction tient :

```
! [remote rejected] refusing to allow a GitHub App to create or update
  workflow `.github/workflows/ci.yml` without `workflows` permission
```

Un jeton personnel fourni manuellement ne contourne pas la restriction.

---

## Action manuelle requise : 4 patches de workflow

À appliquer depuis un poste disposant d'un accès normal au dépôt. **L'ordre
compte** : les patches sont empilés sur `.github/workflows/ci.yml`. La
séquence a été vérifiée (`git apply --check` sur les trois, YAML parsé après
application).

```bash
git apply edugestdz/docs/ci-secrets.patch
git apply edugestdz/docs/pre-deploy-secrets.patch
git apply edugestdz/docs/ci-qualite.patch
cp    edugestdz/docs/deploy.yml.desactive.patch .github/workflows/deploy.yml
rm    edugestdz/docs/*.patch
git add -A && git commit -m "ci: secrets hors du code, gardes sécurité et unification qualité" && git push
```

| Patch | Effet |
|---|---|
| `ci-secrets.patch` | Retire les mots de passe littéraux, génère les clés via `openssl rand`, ajoute 4 gardes bloquantes (`rls:status --strict`, `audit:verify`, anti-`localStorage`, anti-secrets). |
| `pre-deploy-secrets.patch` | Même traitement sur le second workflow, qui portait les mêmes 5 secrets en dur. |
| `ci-qualite.patch` | **Sprint 4** — ajoute les jobs `frontend` et `qualite`, porte la couverture backend à 45 %. |
| `deploy.yml.desactive.patch` | Fichier de remplacement : `on: push` devient `on: workflow_dispatch` avec confirmation. Éteint le rouge du CD. |

Après déploiement : `./scripts/generer-secrets.sh` puis `php artisan migrate`
(deux migrations en attente : `key_version` sur `audit_chain`, table
`refresh_tokens`).

---

## Ce que la CI a révélé — leçons cumulées

### Sprints 2 et 3 (rappel)

Les tests de ces sprints avaient été écrits sans jamais tourner. Leur première
exécution a mis au jour huit défauts. Les trois enseignements qui resservent :

1. **`AuditChainObserver` invalidait toute la chaîne** en recalculant
   `data_hash` même sans caviardage. C'est la vérification de signature
   ajoutée au même sprint qui l'a détecté — elle n'a pas comblé un trou
   théorique, elle a trouvé un vrai défaut le jour de sa mise en service.
2. **Deux correctifs justes isolément se neutralisaient** : `/auth/refresh`
   rendue publique + scope tenant fail-closed ⇒ `User::find()` renvoyait
   `null`. Chercher les interactions, pas seulement les correctifs.
3. **Un test qui casse après un correctif de sécurité peut documenter la
   faille** : trois tests hérités affirmaient qu'un parent pouvait supprimer
   n'importe quel élève et qu'un enseignant lisait le budget.

Détail complet : `edugestdz/docs/SPRINT3_SECURITE.md`.

### Sprint 4

4. **Un test vert n'est une preuve que si l'on sait ce qu'il regarde.**
   `GroupesPage > renders niveau filter` cherchait le texte « Niveau », qui
   existe aussi en en-tête de colonne. Il passait sur le tableau pendant que
   la barre de filtres, elle, ne rendait rien du tout. Cibler les rôles
   (`getByRole('combobox', { name: … })`) plutôt que du texte ambigu.

5. **Une exception dans un `setTimeout` ne se voit pas.** `SearchBar` levait
   `TypeError: onSearch is not a function` dans son anti-rebond : la
   recherche était morte sur six pages, sans le moindre signe à l'écran.
   Les erreurs asynchrones non capturées méritent une vérification propre
   (Vitest les remonte en « Unhandled Errors » — les lire).

6. **Un contrat de composant implicite finit par être violé.** `FilterBar`
   exigeait un `type: 'select'` que personne ne passait : quatre pages
   affichaient une barre vide. Rendre le composant tolérant, ou typer le
   contrat — mais ne pas compter sur la discipline.

7. **Comparer deux mesures suppose deux états comparables.** Le premier
   « N+1 » détecté sur `/api/v1/eleves` n'en était pas un : `EleveObserver`
   purge le cache de statistiques à chaque création, si bien que la mesure
   sous charge payait la reconstitution du cache. Chauffer des deux côtés.

8. **Un seuil qu'on n'exécute pas ne protège rien.** La couverture frontend
   était déclarée à 70 % dans `vitest.config.js` alors que `npm run test` ne
   la calcule même pas, et le workflow qui aurait dû lancer ces tests
   (`edugestdz/.github/workflows/frontend-ci.yml`) n'est lu par personne :
   GitHub ne considère que `.github/` à la racine. Vérifier qu'une garde
   s'exécute avant de la croire.

---

## Repères techniques

### Arborescence

Dossier intermédiaire `edugestdz/` : `edugestdz/backend`, `edugestdz/frontend`,
`edugestdz/mobile`. **Les seuls workflows actifs sont ceux de `.github/` à la
racine** — ceux de `edugestdz/.github/` sont inertes (P1-8).

Depuis le Sprint 5, la racine ne contient plus que 5 fichiers ; les 114 autres
sont classés sous `docs/` (voir `docs/README.md`). La documentation **de
référence** reste sous `edugestdz/docs/` ; `docs/` racine contient l'archive,
les maquettes et les études. La fusion des deux est le point 5.2.

### Conventions établies

- `role:a,b,c` et `permission:module.action` fonctionnent en **OU** ;
  `super_admin` court-circuite tout.
- Trois couches : route → Policy/périmètre → scope tenant + RLS.
- **Fail-closed systématique** : un périmètre vide donne un `whereIn` sur UUID
  nul, jamais « tous ».
- `apiResource(...)->only()/->except()` pour rester compatible avec
  `route:cache`.
- Commentaires et messages de commit en français.

### Fichiers structurants

| Fichier | Rôle |
|---|---|
| `backend/app/Services/AuditChainService.php` | Signature HMAC, rotation de clé, encodage canonique. |
| `backend/app/Observers/AuditChainObserver.php` | Caviardage — ne recalcule le hachage que s'il a modifié le payload. |
| `backend/app/Services/RefreshTokenService.php` | Rotation, détection de réutilisation, cookie httpOnly. |
| `backend/app/Http/Middleware/QueryMonitor.php` | Compteur de requêtes, budgets issus de `config/performance.php`. |
| `backend/tests/Support/BudgetRequetes.php` | Assertions de budget et de non-croissance (détection N+1). |
| `backend/tests/Support/CiDiagnosticExtension.php` | Remontée des échecs en annotations découpées. |
| `backend/app/Traits/BelongsToTenant.php` | Scope global `tenant`, fail-closed. Le hook `creating` **lève** sans tenant résolu — attention aux seeders et commandes. |
| `backend/tests/Feature/Security/PorteeTenantModelesTest.php` | Inventaire de la portée tenant : `HORS_PERIMETRE` (décision) vs `DETTE` (à traiter). |
| `backend/config/security.php` | Source unique des leurres honeypot, clés d'audit, QR, TTL du refresh. |
| `frontend/src/api/tokenStore.js` | Jeton d'accès en mémoire seule. |
| `frontend/src/api/client.js`, `axiosInstance.js` | Rafraîchissement **sérialisé**. |

### Documentation

- `PLAN_REMEDIATION_2026.md` — plan en 6 sprints, état d'avancement.
- `edugestdz/docs/SPRINT3_SECURITE.md` — détail du Sprint 3.
- `edugestdz/docs/SPRINT4_QUALITE.md` — détail du Sprint 4, écarts assumés.
- `edugestdz/docs/SPRINT5_ARCHITECTURE.md` — détail du Sprint 5, écarts assumés.
- `edugestdz/docs/VERSIONING_API.md` — politique de versioning de l'API.
- `docs/README.md` — index des archives (missions, audits, maquettes, études).
- `edugestdz/docs/RBAC_MATRIX.md` — matrice rôles × contrôleurs.
- `edugestdz/DEPLOIEMENT_VERCEL.md` — architecture Vercel.

---

## Points restés ouverts

- **Couverture backend réelle** : inconnue. Le palier de 45 % n'a pas pu être
  mesuré (ni PHP local, ni patch poussable) ; le premier passage tranchera.
- **Baseline PHPStan** : à générer, l'analyse reste non bloquante d'ici là.
- **Couverture frontend** : cliquet posé à 18 % de lignes (mesure réelle
  18.85 %), cible 40 % au Sprint 5 — environ 30 pages à couvrir.
- **Mobile** : aucun test en CI, 38 vulnérabilités transitives Expo,
  `mobile/src/api/axios.js` non aligné sur la rotation des jetons.
- **P1-6** : **traité, et le constat de l'audit était inversé.** Sur 63
  filtres tenant recensés dans les contrôleurs, **un seul** est réellement
  redondant (`AbsenceJournaliere`) — conservé en défense en profondeur. Les
  autres sont porteurs : 21 modèles portent `tenant_id` sans aucun scope.
  Huit ont été corrigés ; `PorteeTenantModelesTest` verrouille l'invariant et
  garde la liste `DETTE` des six modèles restants (surveillance, examens).
  **Ne jamais retirer un filtre tenant sans vérifier à l'exécution que le
  modèle est scopé** : `User` *importait* le trait sans l'appliquer, ce qui
  suffisait à tromper la relecture comme l'analyse statique.
- **CSRF** (point 3.1) : non traité. Le cookie de refresh est `SameSite` et
  scopé `/api/v1/auth` ; un double-submit token reste souhaitable.
