# Reprise de session — EduGest DZ

> Document de passation. Dernière mise à jour : 2026-09-07, commit `a3e4479`.

---

## Où en est le travail

Branche : **`arena/01a07cee-edugest-dz`** — 18 commits d'avance sur `main`, tout est poussé.
Pull request : **[#81](https://github.com/Allintelligence2024/edugest-dz/pull/81)**, ouverte, **non mergée**.

Sprints 1, 2 et 3 du `PLAN_REMEDIATION_2026.md` sont livrés. Sprint 4 entamé côté frontend.

### État de la CI au dernier relevé

| Job | État |
|---|---|
| Frontend (vitest) | **79/79 vert** |
| Pre-Deploy Smoke Tests | **vert** |
| Vercel | **vert** |
| CI backend | **inconnu** — voir ci-dessous |

Le dernier commit (`a3e4479`) corrige les 3 derniers échecs backend connus, mais **son résultat n'a pas pu être relevé** : les identifiants GitHub du bac à sable ont expiré pendant l'attente (`HTTP 401: Bad credentials`). Le commit est bien poussé ; il faut simplement aller lire le résultat.

**Première action à faire : consulter la PR #81 et vérifier si le job backend est passé au vert.**

---

## Ce qu'il reste à faire

1. **Vérifier la CI backend** de `a3e4479`. Si vert → merger la PR #81.
2. Si rouge → lire les diagnostics (voir « Comment diagnostiquer » plus bas) et corriger.
3. **Appliquer les 3 patches de workflow** (voir « Action manuelle » plus bas) — non poussables depuis le bac à sable.
4. Reprendre le **Sprint 4** (`PLAN_REMEDIATION_2026.md`) : coverage backend 45 %, PHPStan/Larastan niveau 6, `composer audit`, Gitleaks, tests N+1 via `QueryMonitor`.

---

## Contraintes de l'environnement — à lire avant de commencer

Trois limitations ont fortement pesé sur la session. Les connaître évite de perdre des heures.

### PHP est indisponible localement, et le restera

`apt-get install php-cli` échoue (dépôts injoignables). Les binaires PHP statiques ne sont pas récupérables non plus : les *release assets* GitHub transitent par `release-assets.githubusercontent.com`, bloqué au niveau TLS. Seul `api.github.com` répond.

**Conséquence : aucun test backend ne peut être exécuté localement. La CI est le seul moyen de les faire tourner.** Chaque aller-retour coûte environ 4 minutes.

### Les logs GitHub Actions sont illisibles

`gh run view --log`, l'API `jobs/{id}/logs` et le zip complet renvoient tous `EOF` (le CDN de logs est bloqué comme celui des assets). **Ne pas perdre de temps à réessayer.**

Un contournement est en place : `tests/Support/CiDiagnosticExtension.php`, une extension PHPUnit enregistrée dans `phpunit.xml`, écrit chaque échec dans le résumé de job **et** en annotation `::error::`. Les annotations, elles, restent lisibles :

```bash
RID=$(gh run list --branch arena/01a07cee-edugest-dz --workflow "CI — EduGest DZ" --limit 1 --json databaseId -q '.[0].databaseId')
JID=$(gh api repos/Allintelligence2024/edugest-dz/actions/runs/$RID/jobs -q '.jobs[0].id')
gh api repos/Allintelligence2024/edugest-dz/check-runs/$JID/annotations \
  -q '.[] | select(.annotation_level=="failure") | "\(.title) :: \(.message)"'
```

**C'est le seul canal de diagnostic disponible. L'extension a débloqué la session ; ne pas la supprimer.** Quand un échec reste opaque, y ajouter des assertions verbeuses (afficher valeur attendue/obtenue, types, contenu de réponse) plutôt que de deviner — c'est ce qui a permis de trouver les dernières causes racines.

### Les workflows ne sont pas poussables

Toute modification de `.github/workflows/**` est rejetée : le bac à sable achemine le trafic GitHub via une application dépourvue de la permission `workflows`. Un jeton personnel fourni manuellement **ne contourne pas** la restriction — testé, y compris en neutralisant `GH_TOKEN`/`GITHUB_TOKEN` : même un `curl` non authentifié répond `Resource not accessible by integration`.

---

## Action manuelle requise : 3 patches de workflow

À appliquer depuis un poste disposant d'un accès normal au dépôt.

```bash
git apply edugestdz/docs/ci-secrets.patch
git apply edugestdz/docs/pre-deploy-secrets.patch
cp    edugestdz/docs/deploy.yml.desactive.patch .github/workflows/deploy.yml
rm    edugestdz/docs/*.patch
git add -A && git commit -m "ci: secrets hors du code + gardes sécurité" && git push
```

Les trois ont été appliqués et vérifiés localement avant extraction (`git apply --check` OK, YAML parsé, greps de garde exécutés à blanc sur l'arbre courant).

- **`ci-secrets.patch`** — retire les mots de passe littéraux, génère `QR_SIGNING_KEY` / `AUDIT_CHAIN_KEY` / `CRON_SECRET` via `openssl rand`, et ajoute 4 gardes bloquantes : `rls:status --strict`, `audit:verify`, anti-`localStorage`, anti-secrets.
- **`pre-deploy-secrets.patch`** — un **second workflow** portait les mêmes 5 secrets en dur. L'audit manuel l'avait manqué ; c'est le dry-run de la garde anti-secrets qui l'a révélé.
- **`deploy.yml.desactive.patch`** — fichier de remplacement complet : `on: push` devient `on: workflow_dispatch` avec confirmation.

Après déploiement, penser aussi à : `./scripts/generer-secrets.sh` puis `php artisan migrate` (deux migrations en attente : `key_version` sur `audit_chain`, table `refresh_tokens`).

---

## Ce que la CI a révélé — leçons à ne pas réapprendre

Les tests des Sprints 2 et 3 avaient été **écrits sans jamais tourner**. Leur première exécution a mis au jour huit défauts, dont plusieurs graves. Résumé, parce que le raisonnement compte autant que le correctif.

### 1. L'observer d'audit invalidait toute la chaîne (grave)

`AuditChainObserver::creating()` recalculait `data_hash` avec un `json_encode()` brut, écrasant le hachage canonique du service — **même quand aucun champ sensible n'était masqué**. La signature HMAC restait, elle, calculée sur le hachage d'origine. Depuis le Sprint 3, **tout bloc naissait avec une signature invalide**.

Le point important : la vérification de signature ajoutée au Sprint 3 n'a pas seulement comblé un trou théorique, elle a immédiatement détecté une incohérence réelle. C'est exactement son rôle.

### 2. Deux correctifs du même sprint se neutralisaient

`/auth/refresh` a été rendue publique (elle ne peut pas exiger le JWT qu'elle renouvelle). Mais `User` utilise `BelongsToTenant`, dont le scope fail-closed — renforcé au même sprint — filtre `1 = 0` quand aucun tenant n'est résolu. `User::find()` renvoyait donc `null` pour un utilisateur valide : **le refresh échouait systématiquement**.

Chaque correctif était juste isolément. Leur interaction ne l'était pas. Corrigé par `withoutGlobalScope('tenant')` puis rétablissement explicite du contexte.

### 3. Le RBAC du Sprint 2 cassait deux flux métier

Trop restrictif : un **enseignant** ne pouvait plus signaler sa propre absence, et un **élève** ne pouvait plus déposer un signalement grave (harcèlement, violence) — soit la finalité même du dispositif. Les verbes de lecture et de traitement restent réservés à l'encadrement ; seuls les dépôts ont été rouverts.

### 4. Trois tests hérités encodaient la vulnérabilité

`test_supprimer_eleve_existant` vérifiait qu'un **parent pouvait supprimer n'importe quel élève**. `test_budget_dashboard_accessible` qu'un **enseignant lisait le budget**. Ils échouaient en 403 — c'est-à-dire que le Sprint 2 fonctionnait. Réécrits pour affirmer la règle voulue.

Réflexe à garder : un test qui casse après un correctif de sécurité peut parfaitement être un test qui *documentait la faille*.

### 5. Faux positifs de mes propres détecteurs

`RbacCoverageTest` signalait 16 routes « non authentifiées » : les honeypots, qu'il cherchait via un middleware `Honeypot` inexistant (ils sont identifiés par leur nom de route `honeypot.*`). Et 7 routes super-admin, parce que `gatherMiddleware()` renvoie l'alias `super_admin`, pas la classe `SuperAdmin`.

Un seul vrai trou dans le lot : les routes `/tarifs` en lecture n'avaient effectivement aucun contrôle de rôle.

### 6. `withoutMiddleware()` purge les cookies de test

Ajouté dans un `setUp` pour contourner `throttle:auth`, il réinitialise l'état de la requête de test et **supprime les cookies posés par `withCookie()`**. Le serveur recevait `{"tous":[],"header":null}`. Diagnostiqué en enregistrant une route sonde qui rapportait ce que Laravel voyait réellement — après trois hypothèses fausses (chiffrement des cookies, nom du cookie, scope tenant).

Remplacé par `RateLimiter::clear('auth')` + `withUnencryptedCookie()`.

---

## Repères techniques

### Arborescence

Le dépôt a un dossier `edugestdz/` intermédiaire : `edugestdz/backend`, `edugestdz/frontend`, `edugestdz/mobile`. Les workflows sont à la racine, dans `.github/`.

### Conventions établies

- `role:a,b,c` et `permission:module.action` fonctionnent en **OU** ; `super_admin` court-circuite tout.
- Trois couches : route → Policy/périmètre → scope tenant + RLS.
- **Fail-closed systématique** : un périmètre vide donne un `whereIn` sur UUID nul, jamais « tous ».
- `apiResource(...)->only()/->except()` pour rester compatible avec `route:cache`.
- Commentaires et messages de commit en français, comme le reste du dépôt.

### Fichiers structurants du Sprint 3

| Fichier | Rôle |
|---|---|
| `backend/app/Services/AuditChainService.php` | signature HMAC, rotation de clé, encodage canonique |
| `backend/app/Observers/AuditChainObserver.php` | caviardage — **ne recalcule le hachage que s'il a modifié le payload** |
| `backend/app/Services/RefreshTokenService.php` | rotation, détection de réutilisation, cookie httpOnly |
| `frontend/src/api/tokenStore.js` | jeton d'accès en mémoire seule |
| `frontend/src/api/client.js`, `axiosInstance.js` | rafraîchissement **sérialisé** (crucial : des appels concurrents déclencheraient la détection de vol) |
| `backend/tests/Support/CiDiagnosticExtension.php` | remontée des échecs dans les annotations CI |

### Documentation

- `PLAN_REMEDIATION_2026.md` — plan en 6 sprints, état d'avancement.
- `edugestdz/docs/SPRINT3_SECURITE.md` — détail du Sprint 3, écarts au plan, points ouverts.
- `edugestdz/docs/RBAC_MATRIX.md` — matrice rôles × contrôleurs.
- `edugestdz/DEPLOIEMENT_VERCEL.md` — architecture Vercel (Railway abandonné).

---

## Points restés ouverts

- **CSRF** (point 3.1 du plan) : non traité. Le cookie de refresh est `SameSite` et scopé `/api/v1/auth`, ce qui couvre l'essentiel ; un double-submit token reste souhaitable.
- **Mobile** : `mobile/src/api/axios.js` fonctionne grâce au repli « jeton dans le corps » mais n'a pas été aligné sur la rotation.
- **Coverage backend** : seuil CI toujours à 30 %, le plan vise 45 % au Sprint 4.
