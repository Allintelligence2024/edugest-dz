# Tests de charge k6 — guide complet (Sprint 6, lot G)

Le dossier `tests/k6/` contient les campagnes de charge de l'API EduGest
DZ. Objectif du lot G : **publier des résultats, pas résumer en « ça
tient »** — chaque exécution écrit un rapport JSON complet dans
`tests/k6/results/`.

## 1. Installation

k6 n'est pas une dépendance npm : [installation](https://k6.io/docs/get-started/installation/)
(version ≥ 0.45 recommandée — les scénarios utilisent `handleSummary`,
`exec.vu` et l'import jslib par HTTPS, tous stables depuis cette version).

```bash
# Debian/Ubuntu
sudo gpg -k && sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6.gpg \
  --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb [signed-by=/usr/share/keyrings/k6.gpg] https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt update && sudo apt install k6
```

## 2. Préparation — ce qu'il faut avant de lancer

| Élément | Détail |
|---|---|
| Environnement | **Jamais la production.** Stack de perf (docker-compose ou réplique), avec des données semées représentatives (le lot G vise 50 tenants × 5 000 élèves). |
| Compte de test A | Actif, **sans 2FA**, rôle admin (`K6_EMAILS`/`K6_PASSWORDS`). La 2FA ferait échouer le login (`two_factor_required`, aucun token). |
| Comptes additionnels | Optionnels, en liste (`K6_EMAILS=a@x.dz,b@x.dz`) — voir « Limites de débit » ci-dessous. |
| Isolation | Compte B d'un **autre** établissement + nom d'un élève de cet établissement (`K6_EMAIL_B`, `K6_PASSWORD_B`, `K6_ELEVE_B_NOM`). |
| Bulletins | `K6_GROUPE_ID` = uuid d'un groupe de test (sinon le scénario ne teste que la validation). |

## 3. Contraintes de débit du backend — à lire AVANT de monter la charge

Ces limites sont dans `backend/app/Providers/AppServiceProvider.php` ; elles
sont **volontaires** (protection), et un test de charge doit en tenir compte :

| Limiteur | Valeur | Impact sur les campagnes |
|---|---|---|
| `throttle:auth` (login) | **10 req / 15 min / IP** | Chaque VU ne se connecte qu'**une fois** (cache par VU). Avec 5 VU : 5 logins. Ne jamais boucler sur `/auth/login` — sauf le scénario `auth-throttle`, fait exprès. |
| `throttle:api` (général) | par rôle : super_admin 300/min, admin 200/min, enseignant 120/min, parent 60/min (**÷2 entre 22 h et 6 h**) | Un seul compte admin plafonne à ~200 req/min : le profil par défaut (5 VU × ~1 req/s avec réflexes) reste dessous. Pour charger plus : plusieurs comptes (`K6_EMAILS` en liste, répartition round-robin par VU) ou relever les limites **sur l'env de perf uniquement**. |
| `surveillance/webhook` | 60 req/min/IP | Le scénario webhook est calibré à ~45 req/min. |
| `throttle:exports` | 10 req/min | Aucun scénario ne touche aux exports. |

Conséquence pratique : les **429 sont comptés** (`http_429`) mais ne font
pas échouer une campagne — atteindre une limite de débit est un
comportement serveur normal. Un **5xx**, lui, échoue toujours.

## 4. Les scénarios

| Commande | Ce qu'il vérifie | Seuils par défaut |
|---|---|---|
| `./tests/k6/run.sh smoke` | La stack répond : ping, health complet (DB, Redis, kill-switch), login, profil, élèves, recherche, analytics. | checks > 99 % — tout doit passer. |
| `./tests/k6/run.sh load` | Parcours de lecture standard (me, élèves p.25, recherche, séances, factures) montée à 5 VU pendant 3 min, réflexes 1-3 s. | p95 < 800 ms, échecs < 1 %. |
| `./tests/k6/run.sh isolation` | **Isolation RLS sous charge** : depuis l'établissement A, rechercher un élève de B ne doit jamais rien renvoyer ; depuis B, le même élève est trouvé (témoin). Toute fuite = échec immédiat (`isolation_fuites == 0`). ⚠️ 2 logins par VU (A+B) : garder `K6_VUS ≤ 5` (budget `throttle:auth` de 10/15 min/IP), ou relever la limite sur l'env de perf. | p95 < 800 ms, **0 fuite**. |
| `./tests/k6/run.sh qr` | Scan QR de présence — **chemin d'erreur** (jetons aléatoires → 422 propre, jamais 5xx). | p95 < 800 ms. |
| `./tests/k6/run.sh bulletins` | `POST /bulletins/generer` — avec `K6_GROUPE_ID` : vraie génération (écriture lourde) ; sans : validation 422. | p95 < 3 s (écriture). |
| `./tests/k6/run.sh auth` | Le limiteur `throttle:auth` : 12 logins valides d'affilée → ~10 passent, la suite en 422 propre. | ≥ 1 429, 0 5xx. |
| `./tests/k6/run.sh webhook` | Webhook public Dahua (payload natif `Events[].Code`) à ~45 req/min, SerialNo inconnu → `processed:false`. | p95 < 500 ms. |
| `./tests/k6/run.sh tous` | Tout, dans l'ordre sûr (smoke → … → **auth en dernier** : il consomme le budget login de l'IP pour 15 min). ⚠️ ~38 logins au total depuis une seule IP : exige `throttle:auth` relevé sur l'env de perf — le lanceur le rappelle et demande `K6_AUTH_THROTTLE_RELAXED=1` pour confirmer. | — |

Variables de profil : `K6_VUS` (défaut 5), `K6_RAMP` (30s), `K6_HOLD` (3m),
`K6_P95_MS` (800), `K6_P95_MS_ECRITURE` (3000), `K6_BASE_URL`
(défaut `http://localhost:8000`).

## 5. QR nominal (procédure manuelle)

Le chemin nominal du QR exige un jeton réel, émis par une séance ouverte :
1. Enseignant : `POST /qr-code/session/demarrer` pour une séance du jour ;
2. Élève : son QR (jeton court, à extraire de `GET /eleves/{id}/qrcode`) ;
3. `POST /presences/scan {qr_token, seance_id}` — à envoyer dans la minute
   (jeton expiré → 422 `QR_INVALIDE`, c'est aussi ce que teste la campagne).

Ce chemin dépend de jetons à durée de vie courte : il se valide à la main
ou via un harness dédié avec données semées — pas dans les campagnes k6
répétables.

## 6. Interpréter et publier les résultats

- Chaque exécution écrit `tests/k6/results/<scénario>-<horodatage>.json`
  (métriques complètes + métadonnées : URL, VU, date). **On archive ce
  fichier avec la campagne** — c'est lui qui fait foi, pas le résumé
  terminal. Via `run.sh` le dossier `results/` est créé ; en invocation
  directe (`k6 run scenarios/…`), le créer d'abord — selon la version de
  k6, `handleSummary` ne crée pas les dossiers parents.
- La première campagne fixe la **baseline** : on note alors p95/p99 par
  endpoint et on recadre `K6_P95_MS`. Les seuils livrés (800 ms lecture,
  3 s écriture, 500 ms webhook) sont des hypothèses raisonnables pour un
  VPS, **pas des mesures**.
- Régression = même scénario, même profil, delta de p95 > 20 % → chercher
  la requête lente (le JSON contient `http_req_duration` par `name`).
- `edugest:simuler-charge` (Artisan) reste utile comme fumigène interne
  (HTTP depuis le serveur lui-même) ; k6 mesure depuis un **client
  externe**, réseau inclus — les deux sont complémentaires, aucun ne
  remplace l'autre.

## 7. Reste à faire (journalé)

- Exécution réelle de la campagne complète sur l'environnement de perf
  (50 tenants × 5 000 élèves) et **publication du rapport** — les
  scénarios sont prêts, l'exécution exige une stack et k6 hors sandbox ;
- intégration CI (job nightly optionnel, `k6 run --quiet` + artefact) ;
- dashboard Grafana/Prometheus (sortie `--out experimental-prometheus-rw`)
  — hors périmètre de ce lot.
