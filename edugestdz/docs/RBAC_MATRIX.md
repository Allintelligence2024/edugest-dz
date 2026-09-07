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

## 6. Limites connues (à traiter au Sprint 3)

- **Filtrage par filiation non vérifié** : un parent atteint `/bulletins` — le
  contrôleur doit garantir qu'il ne voit que SES enfants. Les Policies
  couvrent partiellement ; un audit endpoint par endpoint reste à faire.
- **`enseignant` voit tous les élèves du tenant**, pas seulement ses classes.
  Restriction par affectation à prévoir.
- **3 Policies seulement** (`Eleve`, `Facture`, `FluxInfo`) pour 106 modèles.
  Le middleware de route couvre le gros du risque ; les Policies restent à
  étendre pour la granularité par instance.
- `GET /api/v1/enseignants/{id}/planning` reste ouvert à tous les rôles
  authentifiés : le filtrage par identité doit être fait dans le contrôleur.
