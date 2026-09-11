# edugestdz/ — shim de compatibilité CI (Sprint 6, 11 sept. 2026)

Ce répertoire est un **shim de compatibilité** : les workflows GitHub Actions
de `main` référencent encore les chemins `edugestdz/backend`,
`edugestdz/frontend`, `edugestdz/mobile`, `edugestdz/scripts` — or la
restructuration des sprints 4-6 a déplacé tout ce contenu à la racine et
supprimé le dossier historique.

Le correctif propre existe : `docs/fusion-workflows.patch` (chemins des
workflows alignés sur la racine + palier couverture réellement bloquant).
Il n'a pas pu être poussé depuis l'agent (le jeton GitHub App n'a pas la
permission `workflows`). Ce shim permet donc aux workflows **existants**
de retrouver le code et d'exécuter les vrais tests, en attendant que le
patch soit appliqué sur `main` depuis un poste autorisé.

## Structure (v2 — inversion)

| Chemin | Nature | Rôle |
|--------|--------|------|
| `edugestdz/backend/` | **répertoire réel** (le code y vit) | profondeur 3 exigée par l'étape PHPStan du job qualité : `working-directory: edugestdz/backend` + `../../scripts/analyse-statique.sh` |
| `backend` (racine) → `edugestdz/backend` | symlink | tous les autres usages du dépôt (docs, compose, scripts) continuent de fonctionner |
| `edugestdz/frontend` → `../frontend` | symlink | job frontend + audits npm |
| `edugestdz/mobile` → `../mobile` | symlink | audit npm mobile |
| `edugestdz/scripts` → `../scripts` | symlink | pre-deploy (`../scripts/smoke-test.sh`) |
| `edugestdz/deploy.sh` → `../deploy.sh` | symlink | workflow deploy |

**Pourquoi l'inversion (v2)** : GitHub Actions **canonicalise** le
`working-directory` (chdir physique). Avec `edugestdz/backend` en simple
symlink vers `../backend`, le répertoire de travail devenait
`<repo>/backend` et `../../scripts/…` sortait du dépôt → exit 127 en 0 s.
Le code doit donc physiquement vivre à la profondeur `edugestdz/backend`,
tandis que la racine garde un `backend` symbolique pour ne rien casser
d'ailleurs. `vendor/` s'installe dans le vrai dossier (non versionné).

**À supprimer** dès que `fusion-workflows.patch` est appliqué sur `main` :
détruire le lien `backend` puis `git mv edugestdz/backend backend`
(renommages purs, aucun changement de contenu). Les deux sont
interchangeables ; le patch est la cible propre, ce shim n'est qu'un pont.
