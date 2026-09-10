# Documentation projet — index

Ce dossier rassemble ce qui traînait à la racine du dépôt : 114 fichiers
(96 Markdown, 19 HTML, 2 textes, 1 image, 1 dump SQL) qui masquaient les
quelques fichiers réellement utiles au quotidien. La racine est passée de
119 fichiers à 5.

Rien n'a été supprimé. L'historique d'un projet a une valeur : il explique
pourquoi une décision a été prise, et un audit de 2026 se relit quand la
même question revient. Mais cet historique n'a pas à être la première chose
qu'un nouveau venu voit en ouvrant le dépôt.

> **Documentation de référence** (à jour, maintenue) : [`edugestdz/docs/`](../edugestdz/docs/).
> Ce dossier-ci contient l'archive, les maquettes et les études. Ne pas
> confondre les deux : les archives décrivent des états passés du projet et
> **ne sont pas mises à jour**.

---

## Organisation

| Dossier | Contenu | Volume |
|---------|---------|--------|
| [`archive/missions/`](archive/missions/) | Journaux de développement : comptes rendus de mission, phases, flux fonctionnels, prompts d'agents | 85 fichiers |
| [`archive/audits/`](archive/audits/) | Audits, rapports d'état, analyse concurrentielle | 7 fichiers |
| [`design/`](design/) | Maquettes HTML et schémas d'architecture | 16 fichiers |
| [`business/`](business/) | Étude de marché algérienne, cahier des charges d'origine | 3 fichiers |
| [`guides/`](guides/) | Documents opérationnels toujours actifs | 2 fichiers |

---

## Audits et rapports

Les plus utiles à relire, du plus récent au plus ancien :

- [`AUDIT_FINAL_ARCHITECTURE_JUILLET_2026.html`](archive/audits/AUDIT_FINAL_ARCHITECTURE_JUILLET_2026.html) — audit d'architecture
- [`AUDIT_COMPLET_V2_JUILLET_2026.html`](archive/audits/AUDIT_COMPLET_V2_JUILLET_2026.html) — audit complet, seconde passe
- [`AUDIT_COMPLET_EDUGEST_DZ_JUILLET_2026.md`](archive/audits/AUDIT_COMPLET_EDUGEST_DZ_JUILLET_2026.md) — audit complet, première passe
- [`RAPPORT_AUDIT_COMPLET.md`](archive/audits/RAPPORT_AUDIT_COMPLET.md)
- [`POINTS_MANQUANTS_100_POURCENT.md`](archive/audits/POINTS_MANQUANTS_100_POURCENT.md) — écarts au périmètre cible
- [`ANALYSE_COMPETITIVE_MONDIALE.md`](archive/audits/ANALYSE_COMPETITIVE_MONDIALE.md) — positionnement concurrentiel
- [`CIRCULATION_INFO_MONDIALE_EDUGEST.html`](archive/audits/CIRCULATION_INFO_MONDIALE_EDUGEST.html) — circulation de l'information

Le plan de travail issu de ces audits est [`PLAN_REMEDIATION_2026.md`](../PLAN_REMEDIATION_2026.md),
à la racine : c'est un document vivant, pas une archive.

## Guides opérationnels

- [`GUIDE_UTILISATEUR_EDUGEST_DZ.md`](guides/GUIDE_UTILISATEUR_EDUGEST_DZ.md) — manuel utilisateur général
- [`INCIDENT_RESPONSE_PLAN.md`](guides/INCIDENT_RESPONSE_PLAN.md) — procédure en cas d'incident de sécurité

## Étude et cadrage

- [`ETUDE DE MARCHE EN ALGERIE.txt`](business/) — étude de marché
- [`etude-marche-algerie.html`](business/etude-marche-algerie.html) — même étude, mise en forme
- [`CAHIER DE CHARGE TECHNIQUE LOGICIEL GESTION.txt`](business/) — cahier des charges d'origine

## Maquettes et architecture

Cinq écrans maquettés (`edugest-design-01` à `-05`), une version consolidée
(`edugest-design-COMPLET-FINAL.html`), six itérations du schéma d'architecture
(`edugest-architecture*.html`, la plus récente étant `-finale-v4`), plus
`curriculum-visuel.html`, `modules-manquants.html` et
`pointage-absences-spec.html`.

Ces maquettes sont antérieures à l'implémentation : en cas de divergence avec
le frontend, **c'est le code qui fait foi**.

## Journaux de mission

85 comptes rendus, majoritairement préfixés `MISSION_`, plus `PHASE1` à
`PHASE4`, trois documents `FLUX_` et deux `SPECKIT_`. Ce sont des traces de
sessions de développement, conservées pour retrouver l'intention derrière un
morceau de code. Pour y chercher :

```bash
grep -ril "sujet recherché" docs/archive/missions/
```

---

## Ce qui reste à la racine, et pourquoi

| Fichier | Raison |
|---------|--------|
| `README.md` | Point d'entrée du dépôt |
| `CHANGELOG.md` | Historique des versions, convention usuelle |
| `CONTRIBUTING.md` | Attendu par GitHub à la racine |
| `PLAN_REMEDIATION_2026.md` | Plan de travail en cours (6 sprints) |
| `REPRISE_SESSION.md` | État de passation entre sessions de travail |

Les deux derniers partiront en archive une fois la remédiation terminée.

**Note :** le dump `seed_curriculum_algerie.sql` a rejoint
[`edugestdz/backend/database/sql/`](../edugestdz/backend/database/sql/) —
c'est de la donnée applicative, pas de la documentation.

**Point ouvert :** le README affiche un badge « Licence Propriétaire » qui
pointe vers un fichier `LICENSE` **absent du dépôt**, alors que
`edugestdz/backend/composer.json` déclare `"license": "MIT"`. Les deux
affirmations sont contradictoires et la question est juridique, pas
technique : elle doit être tranchée par le propriétaire du projet, puis le
fichier `LICENSE` ajouté à la racine.
