# edugestdz/ — shim de compatibilité CI (Sprint 6, 11 sept. 2026)

Ce répertoire est un **shim de symlinks** : les workflows GitHub Actions de
`main` référencent encore les chemins `edugestdz/backend`, `edugestdz/frontend`,
`edugestdz/mobile`, `edugestdz/scripts` — or la restructuration des sprints 4-6
a déplacé tout ce contenu à la racine et supprimé le dossier historique.

Le correctif propre existe : `docs/fusion-workflows.patch` (chemins des
workflows alignés sur la racine + palier couverture réellement bloquant).
Il n'a pas pu être poussé depuis l'agent (le jeton GitHub App n'a pas la
permission `workflows`). Ce shim permet donc aux workflows **existants**
de retrouver le code et d'exécuter les vrais tests, en attendant que le
patch soit appliqué sur `main` depuis un poste autorisé.

| Lien | Cible |
|------|-------|
| `backend` → | `../backend` |
| `frontend` → | `../frontend` |
| `mobile` → | `../mobile` |
| `scripts` → | `../scripts` |
| `deploy.sh` → | `../deploy.sh` |

**À supprimer** dès que `fusion-workflows.patch` est appliqué sur `main`
(les deux sont interchangeables ; le patch est la cible propre, ce shim
n'est qu'un pont).
