# Versioning de l'API

> **Portée** — Cette note fixe la règle pour l'API HTTP d'EduGest DZ :
> quand créer une version, ce qui oblige à en créer une, et comment retirer
> l'ancienne sans casser les clients déployés. Elle décrit d'abord l'état
> réel du dépôt, puis la politique à appliquer.
>
> Dernière révision : septembre 2026 (Sprint 5, point 5.5).

---

## 1. État actuel, sans embellissement

| Élément | Réalité constatée |
|---------|-------------------|
| Schéma de versioning | Dans l'URI : `/api/v1/...` |
| Versions publiées | `v1` uniquement |
| Endpoints sous `v1` | ~439 déclarations de routes dans `routes/api/*.php` |
| Où le préfixe est défini | Une seule fois, `routes/api.php`, `Route::prefix('v1')` |
| Hors version | `/api/fichier/{cheminB64}` (fichier signé) et les leurres honeypot |
| Négociation par en-tête | **Aucune** — pas de `Accept: application/vnd...`, pas de `X-API-Version` |
| Mécanisme de dépréciation | **Aucun** — ni `Deprecation`, ni `Sunset`, ni journalisation d'usage |
| Version annoncée dans OpenAPI | `1.0.0` (`config/l5-swagger.php`) |

Clients à considérer à chaque changement :

| Client | Configuration de la base d'URL | Contrainte de mise à jour |
|--------|-------------------------------|---------------------------|
| Frontend React | `VITE_API_URL`, sinon `/api/v1` (`src/api/axiosInstance.js`, `src/api/client.js`) | Immédiate — rechargement du navigateur |
| Application mobile Expo | `EXPO_PUBLIC_API_URL`, sinon `http://localhost:8000/api/v1` | **Lente** — dépend des stores et du bon vouloir de l'utilisateur |
| Intégrations tierces | — | Inconnue ; à recenser avant toute suppression |

Le mobile est la contrainte dimensionnante. Un parent qui n'a pas ouvert
l'application depuis trois mois utilise encore la version d'il y a trois
mois. Toute fenêtre de retrait calculée sur le rythme du frontend est donc
fausse.

---

## 2. Ce qui oblige à changer de version

Le critère n'est pas « le changement est-il important ? » mais « un client
existant, non modifié, continue-t-il de fonctionner ? ».

### Rupture — impose `v2`

- Supprimer ou renommer un endpoint, un champ de réponse, un paramètre.
- Changer le type ou le format d'un champ (`"12"` → `12`, date locale → ISO 8601).
- Rendre obligatoire un paramètre jusque-là facultatif.
- Restreindre une valeur acceptée (nouvelle validation plus stricte).
- Changer le code HTTP d'un cas nominal, ou la structure d'une erreur.
- Changer la sémantique à structure constante : même champ, autre signification.
  C'est le cas le plus dangereux, parce qu'aucun test de schéma ne le voit.
- Changer la pagination, le tri par défaut, ou l'unité d'une valeur.

### Compatible — reste en `v1`

- Ajouter un endpoint.
- Ajouter un champ **facultatif** en réponse (un client correct ignore ce
  qu'il ne connaît pas ; si un client casse, c'est le client qu'on corrige).
- Ajouter un paramètre facultatif dont l'absence conserve le comportement.
- Élargir une validation, ajouter une valeur d'énumération **en entrée**.
- Corriger un défaut qui rapprochait la réponse de ce que la documentation
  promettait déjà.

### Le cas litigieux : la correction de sécurité

Une faille d'isolation corrigée peut casser un client qui exploitait —
volontairement ou non — les données qu'il n'aurait pas dû voir. **Cela ne
justifie pas d'attendre une `v2`.** Le correctif part en `v1`, immédiatement,
et le changement est consigné au `CHANGELOG.md`. Le dépôt en contient déjà
des exemples : des tests validaient des comportements fautifs et ont dû être
réécrits une fois la faille fermée.

---

## 3. Ajouter une énumération en sortie : pourquoi c'est une rupture

Ajouter une valeur à un champ `statut` renvoyé par l'API n'ajoute rien à la
structure, mais casse tout client qui fait un `switch` exhaustif — le mobile
en fait. Traiter ce cas comme compatible est le piège classique.

Règle : toute nouvelle valeur d'énumération **en sortie** est annoncée au
`CHANGELOG.md` et vérifiée côté clients avant déploiement. Les clients
doivent, de leur côté, prévoir une branche par défaut.

---

## 4. Créer une `v2` le jour venu

Le préfixe étant déclaré une seule fois, la mécanique est simple ; c'est la
gestion des deux versions en parallèle qui coûte cher. On ne duplique donc
pas tout.

1. Créer `routes/api/v2/` et un groupe `Route::prefix('v2')` dans
   `routes/api.php`.
2. **Ne dupliquer que les endpoints qui changent.** Les autres restent
   servis par les mêmes contrôleurs, référencés depuis les deux groupes.
   Dupliquer 439 routes pour en modifier trois, c'est garantir que les deux
   copies divergeront — le honeypot de ce dépôt en a fourni la démonstration :
   deux listes en dur, six entrées d'écart, personne ne l'a vu.
3. Versionner au niveau des **ressources de réponse**, pas des contrôleurs :
   `App\Http\Resources\V2\EleveResource` à côté de `V1\EleveResource`. La
   logique métier reste unique.
4. Mettre à jour `config/l5-swagger.php` pour publier les deux
   spécifications.
5. Étendre `RoutesIntegriteTest` : chaque endpoint de `v1` doit soit exister
   en `v2`, soit figurer dans une liste de suppressions assumées.

---

## 5. Retirer une version

Une version dépréciée n'est pas une version morte. La séquence :

### 5.1 Annonce (J)

`CHANGELOG.md`, et notification directe aux intégrations recensées.

### 5.2 En-têtes de dépréciation (J → J+N)

Chaque réponse de la version dépréciée porte :

```http
Deprecation: @1767225600
Sunset: Wed, 31 Dec 2026 23:59:59 GMT
Link: <https://docs.edugestdz.dz/api/v2>; rel="successor-version"
```

- `Deprecation` — [RFC 9745](https://www.rfc-editor.org/rfc/rfc9745.html) :
  champ structuré de type Date, donc préfixé `@`, jamais une date HTTP.
- `Sunset` — [RFC 8594](https://www.rfc-editor.org/rfc/rfc8594.html) : date
  HTTP au format IMF-fixdate.
- `Sunset` ne doit **jamais** être antérieur à `Deprecation`.

À implémenter comme middleware appliqué au groupe de la version dépréciée,
avec les dates en configuration — pas en dur dans le code. Voir
`config/security.php`, section honeypot, pour le motif à suivre.

### 5.3 Mesure de l'usage résiduel (obligatoire)

Retirer une version sans savoir qui l'utilise encore, c'est décider à
l'aveugle. Avant toute suppression, il faut le décompte des appels par
version et par client sur les 30 derniers jours. **Cette instrumentation
n'existe pas aujourd'hui** ; elle relève du point observabilité du Sprint 6
et conditionne le retrait.

### 5.4 Fenêtre minimale

**Six mois** entre l'annonce et le retrait, et jamais moins de **trois mois
après** que la version mobile ciblant la nouvelle API dépasse 95 % du parc
installé. Le second critère prime : c'est une mesure, pas un calendrier.

### 5.5 Retrait

La version supprimée répond `410 Gone` — pas `404`. Un `404` laisse croire à
une erreur de chemin ; un `410` dit que la ressource a existé et n'existe
plus. Le corps indique la version qui remplace.

---

## 6. Ce qui n'est volontairement pas fait

- **Pas de négociation par en-tête** (`Accept: application/vnd.edugest.v2+json`).
  Élégant, mais illisible dans les journaux, impossible à tester depuis un
  navigateur, et source d'erreurs de cache côté CDN. Le versioning par URI
  reste explicite et déboguable.
- **Pas de versioning par endpoint.** Une seule version pour toute l'API.
  Des versions par ressource multiplient les combinaisons à tester sans
  bénéfice pour un produit à trois clients connus.
- **Pas de `v1.1`.** Le numéro mineur n'a pas de sens dans l'URI : soit le
  changement est compatible et ne change rien, soit il ne l'est pas et
  impose `v2`.

---

## 7. Points ouverts

| Point | Statut |
|-------|--------|
| Middleware `Deprecation`/`Sunset` | Non implémenté — à écrire au moment de la `v2` |
| Métriques d'usage par version | Non implémentées — dépend de l'observabilité (Sprint 6) |
| Recensement des intégrations tierces | Non fait — bloquant avant tout retrait |
| Version OpenAPI | Figée à `1.0.0`, non rattachée au `CHANGELOG.md` |
| `mobile/src/api/axios.js` | Non aligné sur la rotation des refresh tokens (dette héritée du Sprint 3) |
