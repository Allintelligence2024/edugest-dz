# Matrice RBAC — EduGest DZ

> Sprint 2 · 2026-09-07
> Réponse au trou d'autorisation intra-tenant : avant ce sprint, 72 des 75
> contrôleurs n'étaient protégés que par `auth:api` + `resolve.tenant`.

---

## 1. Le problème résolu

L'isolation multi-tenant (RLS PostgreSQL + `BelongsToTenant` + `ResolveTenant`)
répondait bien à :

> « L'école A peut-elle voir les données de l'école B ? » → **Non.**

Mais personne ne répondait à :

> « Dans l'école A, un parent peut-il lire les salaires du personnel ? » → **Oui.**

Concrètement, avant le Sprint 2, un compte `parent` authentifié pouvait appeler :

| Endpoint | Donnée exposée |
|---|---|
| `GET /api/v1/paies` | salaires nets du personnel |
| `GET /api/v1/finance/bilan-annuel` | comptes de l'établissement |
| `GET /api/v1/personnel` | dossiers RH |
| `GET /api/v1/audit-logs` | journal de sécurité |
| `GET /api/v1/rgpd/export-tenant` | export intégral des données |
| `PUT /api/v1/notes/{id}` | modification des notes |

Et `GET /api/v1/absences-enseignants` était accessible **sans aucun jeton**.

---

## 2. Les 7 rôles

| Rôle | Portée |
|---|---|
| `super_admin` | Plateforme, hors tenant. Contourne tous les contrôles de rôle. |
| `admin` | Directeur d'établissement. Accès complet à son tenant. |
| `gestionnaire` | Adjoint de direction. Comme admin, sans certains actes RH. |
| `secretariat` | Scolarité : élèves, parents, inscriptions, planning. Pas de finance. |
| `comptable` | Finance, factures, paiements, paie. Pas de pédagogie. |
| `enseignant` | Notes, présences, évaluations, ses séances. Lecture élèves. |
| `parent` | Ses enfants uniquement : bulletins, tarifs, paiement en ligne. |

> ⚠️ `secretariat` était référencé par `ElevePolicy` et `FacturePolicy` mais
> **n'existait pas** dans `RolePermissionSeeder` : toute vérification le
> concernant échouait silencieusement. Le rôle a été créé (id 7).

---

## 3. Matrice par domaine

Légende : ✅ accès · 📖 lecture seule · ❌ refusé (403)

| Domaine | admin | gestionnaire | secretariat | comptable | enseignant | parent |
|---|:--:|:--:|:--:|:--:|:--:|:--:|
| **Élèves** (liste/fiche) | ✅ | ✅ | ✅ | 📖 | 📖 | ❌ |
| Élèves (créer/modifier/supprimer) | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Élève → paiements | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| Import / export élèves | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Parents** | ✅ | ✅ | ✅ | 📖 | 📖 | ❌ |
| **Enseignants** | ✅ | ✅ | 📖 | 📖 | 📖 | ❌ |
| **Contrats** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Personnel / RH** | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Paies** | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| **Factures** | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Paiements / caisse** | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Finance / bilans** | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Budget** | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Tarifs** | ✅ | ✅ | 📖 | ✅ | 📖 | 📖 |
| Paiement en ligne (initier/statut) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Paiement en ligne (dashboard/rembourser) | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Matières / salles** | ✅ | ✅ | ✅ | 📖 | 📖 | 📖 |
| **Groupes** | ✅ | ✅ | ✅ | ❌ | 📖 | ❌ |
| **Cours / séances** | ✅ | ✅ | ✅ | ❌ | 📖 | ❌ |
| Séances (démarrer/terminer) | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ |
| **Présences** (saisie) | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ |
| **Évaluations / notes** | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ |
| **Bulletins** (lecture) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Bulletins (générer/envoyer) | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Planning** (consulter) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Planning (générer/conflits) | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Paramètres établissement** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Audit logs** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **RGPD** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Transport / cantine / stock** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Entretien / pointage / surveillance** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Billets / examens** | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ |
| **Signalements graves** | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ |
| **Absences enseignants** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Super-admin** | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

---

## 4. Mise en œuvre

### Middlewares (alias dans `bootstrap/app.php`)

```php
'role'       => \App\Http\Middleware\RoleCheck::class,
'permission' => \App\Http\Middleware\PermissionCheck::class,
```

Usage :

```php
Route::prefix('paies')->middleware('role:admin,comptable')->group(...);
Route::post('factures')->middleware('permission:factures.créer');
```

### Défense en profondeur

Trois couches indépendantes :

1. **Route** — `role:` / `permission:` : barrière grossière, appliquée avant
   même d'entrer dans le contrôleur.
2. **Policy** — logique fine par ressource (« ce parent est-il bien le parent
   de cet élève ? »).
3. **Scope tenant** — RLS + global scope : l'école B reste invisible.

### API du modèle `User`

```php
$user->nomRole();                    // 'comptable' | null
$user->hasRole('admin', 'comptable') // OR logique
$user->isSuperAdmin();
$user->permissions();                // ['eleves.lister', ...] — caché 5 min
$user->hasPermission('paies.valider');
```

Les permissions viennent de `role_permissions` (86 permissions seedées par
`RolePermissionSeeder`) — table qui existait déjà mais n'était **jamais lue**.

---

## 5. Garde-fous automatiques

`tests/Feature/Security/RbacCoverageTest.php` inspecte la table de routage et
échoue si :

- une route d'un préfixe sensible perd son contrôle de rôle ;
- une route devient accessible sans authentification hors liste blanche ;
- une route authentifiée ne résout pas de tenant.

`tests/Feature/Security/RolePermissionsTest.php` (24 tests) vérifie le
comportement réel : un `parent` reçoit bien 403 sur `/paies`, `/finance`,
`/personnel`, `/audit-logs`, et un `enseignant` garde bien l'accès aux
évaluations et présences.

**Ajouter une route sensible sans `role:` fait échouer la CI.**

---

## 6. Périmètre d'accès intra-tenant (Sprint 2 — suite)

Le RBAC par route répond à « ce **rôle** peut-il appeler cet endpoint ? ».
`PerimetreAccesService` répond à la question suivante :

> « **Cet utilisateur** peut-il voir **cette ligne** ? »

### Règles

| Rôle | Périmètre élèves |
|---|---|
| `super_admin`, `admin`, `gestionnaire`, `secretariat`, `comptable` | tout le tenant |
| `parent` | uniquement ses enfants (`parents.user_id` → `eleve_parent`) |
| `enseignant` | uniquement les élèves inscrits dans ses groupes (titulaire **ou** via un cours) |
| tout autre / sans rôle | **aucun élève** |

### Points d'application

- `BaseApiController::$colonnePerimetreEleve` — définir `'id'` ou `'eleve_id'`
  suffit à restreindre automatiquement `index()` pour tout contrôleur héritant
  de la classe de base.
- `BaseApiController::verifierPerimetreEleve($id)` — garde à placer après un
  `findOrFail` ; retourne une réponse 403 ou `null`.
- `EleveController` : liste + `show` + `notes` + `presences` + `paiements` +
  `bulletins` + `statistiques` (6 sous-ressources).
- `BulletinController` : `index`, `show`, `pdf`.

### Détails d'implémentation

- **Fail-closed** : un périmètre vide filtre sur un UUID impossible ; jamais de
  dégénérescence en « tous les résultats ».
- **Statistiques masquées** : `GET /eleves` ne renvoie `meta.stats` qu'aux
  rôles à vision globale — sinon les effectifs totaux fuiteraient.
- **Cache 5 min** par utilisateur, invalidé par `PerimetreCacheObserver` sur
  `ParentEleve`, `Enseignant`, `Inscription` et `Groupe` : un retrait de
  filiation prend effet immédiatement.
- Le check tenant redondant de `EleveController::show` (signalé à l'audit) est
  remplacé par le contrôle de périmètre, qui lui apporte une vraie valeur.

Couverture : `tests/Feature/Security/PerimetreAccesTest.php` (17 tests).

---

## 7. Limites connues restantes

- **3 Policies seulement** (`Eleve`, `Facture`, `FluxInfo`) pour 106 modèles.
  Les deux couches route + périmètre couvrent l'essentiel du risque, mais la
  granularité par instance mérite d'être étendue.
- **Contrôleurs hors `BaseApiController`** : ceux qui n'en héritent pas
  (Transport, Cantine, Stock…) ne bénéficient pas du filtrage automatique.
  Ils sont pour l'instant fermés aux parents/enseignants par le RBAC de route,
  ce qui suffit — mais toute ouverture future devra ajouter le périmètre.
- `GET /enseignants/{id}/planning` reste ouvert à tous les rôles authentifiés :
  le filtrage par identité doit être fait dans le contrôleur.
- **Tests non exécutés dans l'environnement de développement** (PHP absent du
  sandbox) : la CI est le juge de référence.
