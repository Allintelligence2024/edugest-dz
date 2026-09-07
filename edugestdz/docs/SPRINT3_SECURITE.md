# Sprint 3 — Durcissement sécurité

> Suite des Sprints 1 (QR signés) et 2 (RBAC intra-tenant).
> Périmètre : P0-5, P0-6, P1-1..P1-4, P2-1, P2-7, P2-8 du `PLAN_REMEDIATION_2026.md`.

---

## Les quatre failles réellement trouvées

Le plan annonçait deux P0. L'audit du code en a révélé quatre.

### 1. La chaîne d'audit était signée avec `APP_KEY` (P0-6)

`AuditChainService` calculait son HMAC avec `config('app.key')`. Conséquence :
toute rotation d'`APP_KEY` — opération de routine, recommandée après le départ
d'un administrateur — **invalidait rétroactivement l'intégralité de l'historique
d'audit**. Un registre d'audit qui devient invalide tout seul ne prouve plus rien.

**Correctif** : clé dédiée `AUDIT_CHAIN_KEY`, et surtout une `key_version`
stockée **sur chaque bloc**. La rotation devient possible : les blocs anciens
restent vérifiables avec leur clé d'origine, les nouveaux utilisent la clé
courante.

### 2. La vérification d'intégrité ne vérifiait jamais la signature

Plus grave. `verifierIntegriteComplete()` recalculait les hachages et le
chaînage… mais **ne touchait jamais au champ `signature`**. Or les hachages sont
calculables par quiconque : c'est la signature HMAC, qui exige la clé secrète,
qui rend la chaîne infalsifiable.

Un attaquant disposant d'un accès en écriture à la base (injection SQL, dump
restauré, DBA malveillant) pouvait donc réécrire un événement d'audit **et**
recalculer des hachages parfaitement cohérents. `verifierIntegriteComplete()`
aurait répondu « chaîne valide ». La garantie d'inaltérabilité était décorative.

Le test `test_une_reecriture_coherente_est_detectee_par_la_signature` reproduit
exactement ce scénario.

### 3. Le rafraîchissement de session ne fonctionnait pas

Le frontend lisait `refresh_token` dans la réponse de login et le renvoyait à
`/auth/refresh`. Le backend **n'a jamais émis un seul `refresh_token`**. Deux
conséquences enchaînées :

- le rafraîchissement silencieux échouait toujours ⇒ déconnexion brutale à
  chaque expiration du JWT ;
- `/auth/refresh` était placée **derrière `auth:api`**, c'est-à-dire qu'elle
  exigeait un JWT valide pour renouveler un JWT expiré. Inutilisable au moment
  précis où elle sert.

### 4. Les jetons vivaient dans `localStorage` (P0-5)

26 occurrences sur 21 fichiers. `localStorage` est lisible par tout JavaScript
s'exécutant sur la page : une XSS, une dépendance npm compromise ou une
extension de navigateur suffisait à exfiltrer une session complète — et elle
persistait après fermeture de l'onglet.

---

## Ce qui a été mis en place

### Chaîne d'audit versionnée

| Élément | Détail |
|---|---|
| Clé | `AUDIT_CHAIN_KEY`, distincte d'`APP_KEY` |
| Rotation | `security.audit.keys[1..3]` + `key_version` par bloc |
| Compatibilité | repli `AUDIT_CHAIN_KEY ?: APP_KEY` en version 1 pour les blocs existants |
| Vérification | hachage **et** signature HMAC, en `hash_equals` |
| Bloc genesis | `bloc_numero = 0`, signature non-HMAC, traité en cas particulier |

**Encodage canonique.** `encoderPayload()` applique un `ksort` récursif avant
hachage. Sans cela, PostgreSQL réordonnant librement les clés d'une colonne
`JSONB`, une relecture produirait un hachage différent de l'écriture — des faux
positifs d'altération en masse. L'ordre des **listes** est en revanche préservé :
il est signifiant.

```bash
php artisan audit:verify          # exit 1 + Log::critical si la chaîne est rompue
php artisan audit:verify --json   # pour la supervision
```

### Refresh tokens : cookie httpOnly + rotation

| Propriété | Valeur |
|---|---|
| Cookie | `edugest_refresh`, `httpOnly`, `Secure` (hors dev), `SameSite` depuis `config/session` |
| Portée | `path=/api/v1/auth` — jamais envoyé aux autres routes |
| Stockage serveur | **SHA-256 uniquement**, jamais le jeton en clair |
| TTL | 14 jours (`AUTH_REFRESH_TTL_DAYS`) |
| Rotation | à **chaque** usage |

**Détection de vol.** Chaque jeton porte un `family_id`. Rejouer un jeton déjà
consommé ne peut signifier qu'une chose : deux détenteurs pour un même jeton.
Toute la lignée est alors révoquée — y compris la session de la victime — et un
`Log::critical` est émis. Se reconnecter est un désagrément mineur comparé à un
attaquant qui conserve un accès indéfiniment renouvelé.

`/api/v1/auth/refresh` est passée **en route publique** sous `throttle:auth`.
Elle ne peut pas exiger le JWT qu'elle est censée remplacer.

**Clients mobiles.** Expo SecureStore ne gère pas les cookies : le jeton en
clair n'est renvoyé dans le corps de la réponse que si aucun cookie n'est
présent dans la requête.

### Frontend : plus aucun jeton persistant

- `src/api/tokenStore.js` (nouveau) — jeton d'accès dans une **variable de
  module**, effacée au rechargement.
- `purgerAncienStockage()` s'exécute au démarrage : les utilisateurs déjà
  connectés ne conservent pas un jeton en clair d'une version précédente.
- Au chargement de l'application, `AuthContext` appelle `/auth/refresh` : la
  session survit au rechargement de page **sans qu'aucun secret ne soit lisible
  par le JavaScript**.
- **Rafraîchissement sérialisé** (`axiosInstance.js` et `client.js`) : un seul
  appel réseau même si dix requêtes tombent en 401 simultanément. Sans cette
  précaution, les appels concurrents auraient été interprétés côté serveur comme
  une réutilisation de jeton volé — et auraient déconnecté l'utilisateur.
- `credentials: 'include'` / `withCredentials: true` partout, et
  `cors.supports_credentials = true` côté Laravel (avec liste d'origines
  explicite : les navigateurs refusent le joker `*` en mode credentials).

21 fichiers migrés, `localStorage` pour les jetons : **0 occurrence restante**.

### Tenant fail-closed

`ResolveTenant` renvoie désormais **403 `TENANT_FORBIDDEN`** au lieu de 404 : un
404 confirmait ou infirmait l'existence d'un établissement à un tiers.

> **Écart au plan (3.3).** Le plan demandait que le scope lève une exception.
> Vérification faite, `BelongsToTenant` était **déjà** fail-closed
> (`whereRaw('1 = 0')`). Lever une exception aurait cassé les jobs de queue et
> commandes artisan légitimes qui interrogent des modèles hors requête HTTP. On a
> conservé le filtre vide — le comportement sûr — et ajouté un `Log::warning`
> pour qu'une requête sans tenant cesse de passer inaperçue.

### RLS auditable

```bash
php artisan rls:status            # tableau lisible
php artisan rls:status --strict   # exit 1 si une table manque — pour la CI
```

Une table avec RLS activé mais **sans aucune policy** est comptée comme non
protégée : c'est un faux sentiment de sécurité, pas une protection.

### Secrets

| Endroit | Avant | Après |
|---|---|---|
| `docker-compose.yml` | mots de passe par défaut en clair | `${DB_PASSWORD:?...}` — le démarrage échoue si absent |
| pgAdmin / redis-commander | démarrés par défaut | `profiles: ["debug"]`, jamais up spontanément |
| Redis | exposé sur `0.0.0.0` | bindé sur `127.0.0.1` |
| `phpunit.xml` | secret réutilisable | valeurs préfixées `phpunit-only-`, explicitement inutilisables |
| `TestUserSeeder` | `Hash::make('password')` | mot de passe aléatoire affiché une fois, **refus de tourner en production** |

`scripts/generer-secrets.sh` génère les neuf secrets d'un coup et sauvegarde le
`.env` précédent de façon horodatée.

---

## Tests

| Fichier | Couvre |
|---|---|
| `tests/Feature/Security/AuditChainRotationTest.php` | signature, rotation `APP_KEY` sans casse, rotation de clé d'audit, altération de payload, réécriture cohérente, encodage canonique |
| `tests/Feature/Security/RefreshTokenTest.php` | cookie httpOnly, stockage haché, rotation, détection de réutilisation, expiration, compte suspendu, logout, purge |
| `tests/Feature/Security/DurcissementSprint3Test.php` | tenant fail-closed, RLS des 43 tables, séparation des clés, cron fail-closed, CORS |

⚠️ **PHP est indisponible dans l'environnement de travail** (dépôts APT
injoignables) : ces tests sont écrits mais **n'ont pas été exécutés**. À lancer
avant tout déploiement :

```bash
cd backend
php artisan migrate
php artisan test --testsuite=Feature --filter='AuditChain|RefreshToken|Sprint3'
```

Le frontend, lui, a été vérifié : `vite build` passe, et ESLint ne montre
aucune régression (18 erreurs préexistantes contre 20 en baseline).

---

## Action manuelle requise

### 1. Correctif CI non poussable

Le jeton GitHub de cette session **n'a pas le scope `workflows`** : impossible de
committer une modification de `.github/workflows/**`. Le fichier contient encore
le mot de passe littéral `EduGest@2026!` (4 occurrences).

```bash
cd /chemin/vers/edugest-dz
git apply edugestdz/docs/ci-secrets.patch
git add .github/workflows/ci.yml && git commit -m "ci: retirer les mots de passe en dur"
```

Le patch remplace les littéraux par `${{ secrets.CI_DB_PASSWORD }}` (avec repli
sur une valeur éphémère de CI, la base étant jetable) et génère
`QR_SIGNING_KEY` / `AUDIT_CHAIN_KEY` / `CRON_SECRET` via `openssl rand`.

### 2. Génération des secrets et migrations

```bash
./scripts/generer-secrets.sh
cd backend && php artisan migrate
```

Deux migrations sont ajoutées : `key_version` sur `audit_chain`, et la table
`refresh_tokens`.

### 3. Planifier la purge

Ajouter au `Kernel` (ou au cron Vercel) :

```php
$schedule->call(fn () => app(RefreshTokenService::class)->purger())->daily();
$schedule->command('audit:verify')->daily();
```

---

## Reste ouvert

- **CSRF** (point 3.1 du plan) : non traité. Le cookie de refresh est
  `SameSite=Lax/Strict` et scopé `/api/v1/auth`, ce qui couvre l'essentiel du
  risque ; un double-submit token reste souhaitable pour les navigateurs anciens.
- Application mobile : `mobile/src/api/axios.js` utilise déjà SecureStore et
  reste fonctionnel grâce au repli « jeton dans le corps », mais n'a pas été
  aligné sur la rotation.
