# 🤖 MISSION DEEPSEEK — Swagger / OpenAPI Documentation
## EduGest DZ · Branche : develop · 1er Juillet 2026
## Tests actuels : 313 ✅ · Objectif : 313 ✅ (0 régression)

---

## CONTEXTE

Documenter l'API complète d'EduGest DZ avec OpenAPI 3.1 via le package
**`darkaonline/l5-swagger`** (le standard Laravel).

Résultat attendu : une UI Swagger accessible sur `/api/documentation`
couvrant tous les endpoints existants (auth, élèves, finances, transport,
cantine, stock, budget, entretien, personnel, absences, billets, paiements CIB).

### Périmètre
- Tous les contrôleurs dans `app/Http/Controllers/Api/V1/`
- Toutes les routes dans `routes/api.php`
- Annotations PHP (PHPDoc) + fichiers de schémas YAML en fallback
- Sécurité JWT Bearer documentée
- Multi-tenant : header `X-Tenant-ID` documenté sur chaque groupe

---

## ÉTAPE 0 — Synchroniser develop

```bash
git checkout develop
git pull origin main
```

---

## ÉTAPE 1 — Installer l5-swagger

**Modifier :** `edugestdz/backend/composer.json`

Ajouter dans `require` :

```json
"darkaonline/l5-swagger": "^8.6"
```

```bash
cd edugestdz/backend
composer require "darkaonline/l5-swagger:^8.6"
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
```

---

## ÉTAPE 2 — Configuration l5-swagger

**Modifier :** `edugestdz/backend/config/l5-swagger.php`

Remplacer le bloc `'defaults'` → `'info'` :

```php
'info' => [
    'title'          => 'EduGest DZ API',
    'description'    => 'API complète de la plateforme SaaS de gestion scolaire EduGest DZ — écoles privées & centres de cours particuliers en Algérie.',
    'termsOfService' => null,
    'contact'        => [
        'email' => 'support@edugest.dz',
    ],
    'license'        => [
        'name' => 'Proprietary',
    ],
    'version'        => '1.0.0',
],
```

Remplacer la clé `'securityDefinitions'` :

```php
'securityDefinitions' => [
    'securitySchemes' => [
        'bearerAuth' => [
            'type'         => 'http',
            'scheme'       => 'bearer',
            'bearerFormat' => 'JWT',
        ],
    ],
    'security' => [
        [
            'bearerAuth' => [],
        ],
    ],
],
```

Remplacer la clé `'paths'` → `'annotations'` pour pointer vers les contrôleurs :

```php
'paths' => [
    'annotations' => [
        base_path('app/Http/Controllers/Api/V1'),
        base_path('app/Virtual'),
    ],
    'docs'                  => storage_path('api-docs'),
    'docs_json'             => 'api-docs.json',
    'docs_yaml'             => 'api-docs.yaml',
    'format_to_use_for_docs'=> env('L5_FORMAT_TO_USE_FOR_DOCS', 'json'),
    'views'                 => base_path('resources/views/vendor/l5-swagger'),
    'base'                  => env('L5_SWAGGER_BASE_PATH', null),
    'swagger_ui_assets_path'=> env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/swagger-api/swagger-ui/dist/'),
    'excludes'              => [],
],
```

---

## ÉTAPE 3 — Fichier d'entrée OpenAPI (annotation principale)

**Créer :** `edugestdz/backend/app/Virtual/OpenApiDefinition.php`

```php
<?php

namespace App\Virtual;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="EduGest DZ API",
 *     description="API complète — plateforme SaaS de gestion scolaire pour l'Algérie.
Authentification via JWT Bearer (header Authorization: Bearer <token>).
Multi-tenant : chaque requête doit inclure le header X-Tenant-ID.",
 *     @OA\Contact(email="support@edugest.dz"),
 *     @OA\License(name="Proprietary")
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Serveur courant"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 *
 * @OA\Parameter(
 *     parameter="TenantId",
 *     name="X-Tenant-ID",
 *     in="header",
 *     required=true,
 *     description="Identifiant unique du tenant (école/centre)",
 *     @OA\Schema(type="string", example="550e8400-e29b-41d4-a716-446655440000")
 * )
 *
 * @OA\Tag(name="Auth",          description="Authentification JWT + 2FA")
 * @OA\Tag(name="Eleves",        description="Gestion des élèves")
 * @OA\Tag(name="Enseignants",   description="Gestion du corps enseignant")
 * @OA\Tag(name="Groupes",       description="Groupes / classes")
 * @OA\Tag(name="Planning",      description="Séances & planning")
 * @OA\Tag(name="Presences",     description="Présences en séance")
 * @OA\Tag(name="Evaluations",   description="Évaluations & notes")
 * @OA\Tag(name="Bulletins",     description="Bulletins trimestriels PDF")
 * @OA\Tag(name="Absences",      description="Absences journalières + SMS")
 * @OA\Tag(name="Billets",       description="Billets entrée / retard / sortie / convocation")
 * @OA\Tag(name="Finances",      description="Factures & paiements")
 * @OA\Tag(name="PaiementCIB",   description="Paiement CIB / Dahabia via Satim")
 * @OA\Tag(name="Transport",     description="Circuits, arrêts & pointage bus")
 * @OA\Tag(name="Cantine",       description="Menus, inscriptions & pointage repas")
 * @OA\Tag(name="Stock",         description="Inventaire, mouvements & bons de commande")
 * @OA\Tag(name="Budget",        description="Budget, dépenses & prévisionnel")
 * @OA\Tag(name="Personnel",     description="Personnel non-enseignant & paie")
 * @OA\Tag(name="Entretien",     description="Locaux, interventions & préventif")
 * @OA\Tag(name="Pointage",      description="Pointage enseignants & badges RFID")
 * @OA\Tag(name="Notifications", description="Notifications push & WhatsApp")
 * @OA\Tag(name="SuperAdmin",    description="Administration globale (super-admin uniquement)")
 */
class OpenApiDefinition {}
```

---

## ÉTAPE 4 — Schémas réutilisables (Virtual Models)

**Créer :** `edugestdz/backend/app/Virtual/Schemas.php`

```php
<?php

namespace App\Virtual;

/**
 * ── Réponse générique ──────────────────────────────────────────────────────
 *
 * @OA\Schema(
 *     schema="SuccessResponse",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string",  example="Opération réussie"),
 *     @OA\Property(property="data",    type="object")
 * )
 *
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string",  example="Ressource non trouvée"),
 *     @OA\Property(property="errors",  type="object",  nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     @OA\Property(property="current_page",  type="integer", example=1),
 *     @OA\Property(property="last_page",     type="integer", example=5),
 *     @OA\Property(property="per_page",      type="integer", example=20),
 *     @OA\Property(property="total",         type="integer", example=97)
 * )
 *
 * ── Élève ─────────────────────────────────────────────────────────────────
 *
 * @OA\Schema(
 *     schema="Eleve",
 *     required={"id","nom","prenom","niveau_scolaire","statut"},
 *     @OA\Property(property="id",              type="string",  format="uuid"),
 *     @OA\Property(property="nom",             type="string",  example="Benali"),
 *     @OA\Property(property="prenom",          type="string",  example="Amira"),
 *     @OA\Property(property="date_naissance",  type="string",  format="date", example="2010-03-15"),
 *     @OA\Property(property="sexe",            type="string",  enum={"M","F"}),
 *     @OA\Property(property="niveau_scolaire", type="string",  example="3AS"),
 *     @OA\Property(property="statut",          type="string",  enum={"actif","inactif","suspendu"}),
 *     @OA\Property(property="photo_url",       type="string",  nullable=true),
 *     @OA\Property(property="tenant_id",       type="string",  format="uuid"),
 *     @OA\Property(property="created_at",      type="string",  format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="EleveInput",
 *     required={"nom","prenom","niveau_scolaire","date_naissance","sexe"},
 *     @OA\Property(property="nom",             type="string",  example="Benali"),
 *     @OA\Property(property="prenom",          type="string",  example="Amira"),
 *     @OA\Property(property="date_naissance",  type="string",  format="date", example="2010-03-15"),
 *     @OA\Property(property="sexe",            type="string",  enum={"M","F"}),
 *     @OA\Property(property="niveau_scolaire", type="string",  example="3AS"),
 *     @OA\Property(property="wilaya_id",       type="integer", example=31),
 *     @OA\Property(property="commune_id",      type="integer", example=310),
 *     @OA\Property(property="adresse",         type="string",  nullable=true)
 * )
 *
 * ── Facture ───────────────────────────────────────────────────────────────
 *
 * @OA\Schema(
 *     schema="Facture",
 *     @OA\Property(property="id",            type="string",  format="uuid"),
 *     @OA\Property(property="numero",        type="string",  example="FAC-2026-0042"),
 *     @OA\Property(property="eleve_id",      type="string",  format="uuid"),
 *     @OA\Property(property="total_ht",      type="number",  format="float", example=4500.00),
 *     @OA\Property(property="tva",           type="number",  format="float", example=0.00),
 *     @OA\Property(property="total_ttc",     type="number",  format="float", example=4500.00),
 *     @OA\Property(property="statut",        type="string",  enum={"émise","payée","en_retard","partiellement_payée","annulée"}),
 *     @OA\Property(property="date_emission", type="string",  format="date"),
 *     @OA\Property(property="date_echeance", type="string",  format="date"),
 *     @OA\Property(property="mois",          type="integer", example=7),
 *     @OA\Property(property="annee",         type="integer", example=2026)
 * )
 *
 * ── Paiement ──────────────────────────────────────────────────────────────
 *
 * @OA\Schema(
 *     schema="Paiement",
 *     @OA\Property(property="id",             type="string", format="uuid"),
 *     @OA\Property(property="facture_id",     type="string", format="uuid"),
 *     @OA\Property(property="montant",        type="number", format="float", example=4500.00),
 *     @OA\Property(property="mode_paiement",  type="string", enum={"espèces","chèque","virement","cib","dahabia"}),
 *     @OA\Property(property="statut",         type="string", enum={"en_attente","confirmé","échoué","remboursé"}),
 *     @OA\Property(property="date_paiement",  type="string", format="date-time"),
 *     @OA\Property(property="reference_satim",type="string", nullable=true)
 * )
 *
 * ── Circuit Transport ─────────────────────────────────────────────────────
 *
 * @OA\Schema(
 *     schema="CircuitTransport",
 *     @OA\Property(property="id",               type="string",  format="uuid"),
 *     @OA\Property(property="nom",              type="string",  example="Circuit Nord"),
 *     @OA\Property(property="capacite",         type="integer", example=25),
 *     @OA\Property(property="nb_eleves_actifs", type="integer", example=18),
 *     @OA\Property(property="taux_remplissage", type="number",  format="float", example=72.0),
 *     @OA\Property(property="actif",            type="boolean", example=true)
 * )
 *
 * ── Personnel ────────────────────────────────────────────────────────────
 *
 * @OA\Schema(
 *     schema="Personnel",
 *     @OA\Property(property="id",       type="string", format="uuid"),
 *     @OA\Property(property="nom",      type="string", example="Cherif"),
 *     @OA\Property(property="prenom",   type="string", example="Karim"),
 *     @OA\Property(property="poste",    type="string", example="Agent de sécurité"),
 *     @OA\Property(property="statut",   type="string", enum={"actif","inactif","congé"}),
 *     @OA\Property(property="salaire_base", type="number", format="float", example=28000.00)
 * )
 *
 * ── Article Stock ─────────────────────────────────────────────────────────
 *
 * @OA\Schema(
 *     schema="ArticleStock",
 *     @OA\Property(property="id",              type="string",  format="uuid"),
 *     @OA\Property(property="nom",             type="string",  example="Cahier 96 pages"),
 *     @OA\Property(property="reference",       type="string",  example="CAH-96"),
 *     @OA\Property(property="categorie",       type="string",  example="fournitures"),
 *     @OA\Property(property="quantite_stock",  type="integer", example=150),
 *     @OA\Property(property="seuil_alerte",    type="integer", example=20),
 *     @OA\Property(property="prix_unitaire",   type="number",  format="float", example=35.00),
 *     @OA\Property(property="actif",           type="boolean", example=true)
 * )
 */
class Schemas {}
```

---

## ÉTAPE 5 — Annoter AuthController

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/AuthController.php`

Ajouter ces annotations **avant** chaque méthode concernée (sans modifier la logique) :

```php
/**
 * @OA\Post(
 *     path="/api/v1/auth/login",
 *     summary="Connexion utilisateur",
 *     tags={"Auth"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"email","password"},
 *             @OA\Property(property="email",    type="string", format="email", example="admin@ecole-oran.dz"),
 *             @OA\Property(property="password", type="string", format="password", example="secret")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Connexion réussie",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="token",      type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
 *                 @OA\Property(property="token_type", type="string", example="bearer"),
 *                 @OA\Property(property="expires_in", type="integer", example=3600),
 *                 @OA\Property(property="user",       type="object")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Identifiants invalides", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 */
public function login(Request $request): JsonResponse
```

```php
/**
 * @OA\Post(
 *     path="/api/v1/auth/logout",
 *     summary="Déconnexion (invalide le token JWT)",
 *     tags={"Auth"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Déconnecté", @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
 *     @OA\Response(response=401, description="Non authentifié",  @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 */
public function logout(): JsonResponse
```

```php
/**
 * @OA\Post(
 *     path="/api/v1/auth/refresh",
 *     summary="Rafraîchir le token JWT",
 *     tags={"Auth"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Token rafraîchi", @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
 *     @OA\Response(response=401, description="Token invalide",  @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 */
public function refresh(): JsonResponse
```

```php
/**
 * @OA\Get(
 *     path="/api/v1/auth/me",
 *     summary="Profil de l'utilisateur connecté",
 *     tags={"Auth"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Profil utilisateur", @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
 *     @OA\Response(response=401, description="Non authentifié",    @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 */
public function me(): JsonResponse
```

---

## ÉTAPE 6 — Annoter EleveController

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/EleveController.php`

Ajouter avant chaque méthode :

```php
/**
 * @OA\Get(
 *     path="/api/v1/eleves",
 *     summary="Liste paginée des élèves",
 *     tags={"Eleves"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\Parameter(name="per_page",       in="query", required=false, @OA\Schema(type="integer", default=20)),
 *     @OA\Parameter(name="statut",         in="query", required=false, @OA\Schema(type="string",  enum={"actif","inactif","suspendu"})),
 *     @OA\Parameter(name="niveau_scolaire",in="query", required=false, @OA\Schema(type="string")),
 *     @OA\Parameter(name="search",         in="query", required=false, @OA\Schema(type="string")),
 *     @OA\Response(
 *         response=200,
 *         description="Liste des élèves",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="data",  type="array", @OA\Items(ref="#/components/schemas/Eleve")),
 *                 @OA\Property(property="meta",  ref="#/components/schemas/PaginationMeta"),
 *                 @OA\Property(property="stats", type="object")
 *             )
 *         )
 *     )
 * )
 */
public function index(Request $request): JsonResponse
```

```php
/**
 * @OA\Post(
 *     path="/api/v1/eleves",
 *     summary="Créer un élève",
 *     tags={"Eleves"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/EleveInput")),
 *     @OA\Response(response=201, description="Élève créé",      @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
 *     @OA\Response(response=422, description="Données invalides",@OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 */
public function store(Request $request): JsonResponse
```

```php
/**
 * @OA\Get(
 *     path="/api/v1/eleves/{id}",
 *     summary="Détail d'un élève",
 *     tags={"Eleves"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(response=200, description="Détail élève",   @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
 *     @OA\Response(response=404, description="Non trouvé",     @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 */
public function show(string $id): JsonResponse
```

```php
/**
 * @OA\Put(
 *     path="/api/v1/eleves/{id}",
 *     summary="Mettre à jour un élève",
 *     tags={"Eleves"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/EleveInput")),
 *     @OA\Response(response=200, description="Élève mis à jour", @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
 *     @OA\Response(response=404, description="Non trouvé",       @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 */
public function update(Request $request, string $id): JsonResponse
```

```php
/**
 * @OA\Delete(
 *     path="/api/v1/eleves/{id}",
 *     summary="Supprimer un élève (soft delete)",
 *     tags={"Eleves"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(response=200, description="Supprimé",    @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
 *     @OA\Response(response=404, description="Non trouvé",  @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 */
public function destroy(string $id): JsonResponse
```

---

## ÉTAPE 7 — Annoter FinanceController (Factures & Paiements)

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/FinanceController.php`

```php
/**
 * @OA\Get(
 *     path="/api/v1/finance/tableau-bord",
 *     summary="Dashboard financier",
 *     tags={"Finances"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\Response(
 *         response=200,
 *         description="KPIs financiers",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="ca_mois",      type="number",  format="float"),
 *                 @OA\Property(property="ca_annee",     type="number",  format="float"),
 *                 @OA\Property(property="impayes",      type="number",  format="float"),
 *                 @OA\Property(property="nb_impayes",   type="integer"),
 *                 @OA\Property(property="ca_par_mois",  type="array",   @OA\Items(type="object")),
 *                 @OA\Property(property="modes_payment",type="object")
 *             )
 *         )
 *     )
 * )
 */
public function getTableauBord(): JsonResponse
```

```php
/**
 * @OA\Get(
 *     path="/api/v1/finance/factures",
 *     summary="Liste des factures",
 *     tags={"Finances"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\Parameter(name="statut",   in="query", @OA\Schema(type="string", enum={"émise","payée","en_retard","partiellement_payée","annulée"})),
 *     @OA\Parameter(name="mois",     in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="annee",    in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
 *     @OA\Response(response=200, description="Factures paginées", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
 * )
 */
public function indexFactures(Request $request): JsonResponse
```

```php
/**
 * @OA\Post(
 *     path="/api/v1/finance/paiements",
 *     summary="Enregistrer un paiement (cash / chèque / virement)",
 *     tags={"Finances"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"facture_id","montant","mode_paiement"},
 *             @OA\Property(property="facture_id",    type="string", format="uuid"),
 *             @OA\Property(property="montant",       type="number", format="float", example=4500.00),
 *             @OA\Property(property="mode_paiement", type="string", enum={"espèces","chèque","virement"}),
 *             @OA\Property(property="reference",     type="string", nullable=true)
 *         )
 *     ),
 *     @OA\Response(response=201, description="Paiement enregistré", @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
 *     @OA\Response(response=422, description="Données invalides",   @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 */
public function storePaiement(Request $request): JsonResponse
```

---

## ÉTAPE 8 — Annoter SatimController (Paiement CIB / Dahabia)

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/SatimController.php`

```php
/**
 * @OA\Post(
 *     path="/api/v1/paiements/cib/initier",
 *     summary="Initier un paiement CIB / Dahabia via Satim",
 *     tags={"PaiementCIB"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"facture_id"},
 *             @OA\Property(property="facture_id",   type="string", format="uuid"),
 *             @OA\Property(property="return_url",   type="string", format="uri",
 *                          example="https://app.edugest.dz/paiement/retour"),
 *             @OA\Property(property="fail_url",     type="string", format="uri",
 *                          example="https://app.edugest.dz/paiement/echec")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="URL de redirection Satim",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="redirect_url", type="string", format="uri"),
 *                 @OA\Property(property="order_id",     type="string"),
 *                 @OA\Property(property="expire_at",    type="string", format="date-time")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=422, description="Facture introuvable ou déjà payée", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 */
public function initierPaiement(Request $request): JsonResponse
```

```php
/**
 * @OA\Post(
 *     path="/api/v1/paiements/cib/confirmer",
 *     summary="Confirmer un paiement Satim (callback)",
 *     tags={"PaiementCIB"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="orderId",       type="string"),
 *             @OA\Property(property="sessionId",     type="string"),
 *             @OA\Property(property="orderStatus",   type="integer", example=2)
 *         )
 *     ),
 *     @OA\Response(response=200, description="Paiement confirmé",  @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
 *     @OA\Response(response=400, description="Paiement échoué",    @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 */
public function confirmerPaiement(Request $request): JsonResponse
```

```php
/**
 * @OA\Post(
 *     path="/api/v1/paiements/cib/{paiementId}/rembourser",
 *     summary="Rembourser un paiement CIB",
 *     tags={"PaiementCIB"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\Parameter(name="paiementId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"motif"},
 *             @OA\Property(property="motif", type="string", example="Désinscription élève")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Remboursement initié", @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
 *     @OA\Response(response=422, description="Paiement non remboursable", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 */
public function rembourser(Request $request, string $paiementId): JsonResponse
```

---

## ÉTAPE 9 — Annoter TransportController

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/TransportController.php`

```php
/**
 * @OA\Get(
 *     path="/api/v1/transport/circuits",
 *     summary="Liste des circuits de transport",
 *     tags={"Transport"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\Parameter(name="actif", in="query", required=false, @OA\Schema(type="boolean")),
 *     @OA\Response(
 *         response=200,
 *         description="Circuits avec stats",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="circuits", type="array", @OA\Items(ref="#/components/schemas/CircuitTransport")),
 *                 @OA\Property(property="stats",    type="object")
 *             )
 *         )
 *     )
 * )
 */
public function indexCircuits(Request $request): JsonResponse
```

```php
/**
 * @OA\Post(
 *     path="/api/v1/transport/pointage",
 *     summary="Pointer un élève sur le bus",
 *     tags={"Transport"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"circuit_id","eleve_id","trajet"},
 *             @OA\Property(property="circuit_id", type="string", format="uuid"),
 *             @OA\Property(property="eleve_id",   type="string", format="uuid"),
 *             @OA\Property(property="trajet",     type="string", enum={"aller","retour"}),
 *             @OA\Property(property="present",    type="boolean", default=true),
 *             @OA\Property(property="heure",      type="string",  example="07:45")
 *         )
 *     ),
 *     @OA\Response(response=201, description="Pointage enregistré", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
 * )
 */
public function pointerEleve(Request $request): JsonResponse
```

---

## ÉTAPE 10 — Annoter AbsenceController & BilletController

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/AbsenceController.php`

```php
/**
 * @OA\Get(
 *     path="/api/v1/absences",
 *     summary="Liste des absences journalières",
 *     tags={"Absences"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\Parameter(name="date",     in="query", required=false, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="statut",   in="query", required=false, @OA\Schema(type="string", enum={"non_justifiée","justifiée","en_attente"})),
 *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=20)),
 *     @OA\Response(response=200, description="Absences paginées", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
 * )
 */
public function index(Request $request): JsonResponse
```

```php
/**
 * @OA\Post(
 *     path="/api/v1/absences",
 *     summary="Déclarer une absence journalière",
 *     tags={"Absences"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"eleve_id","date_absence"},
 *             @OA\Property(property="eleve_id",     type="string", format="uuid"),
 *             @OA\Property(property="date_absence", type="string", format="date"),
 *             @OA\Property(property="motif",        type="string", nullable=true),
 *             @OA\Property(property="sms_envoye",   type="boolean", default=false)
 *         )
 *     ),
 *     @OA\Response(response=201, description="Absence déclarée + SMS parent auto", @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
 *     @OA\Response(response=422, description="Données invalides", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 */
public function store(Request $request): JsonResponse
```

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/BilletController.php`

```php
/**
 * @OA\Post(
 *     path="/api/v1/billets",
 *     summary="Émettre un billet (entrée tardive / sortie anticipée / convocation)",
 *     tags={"Billets"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"eleve_id","type"},
 *             @OA\Property(property="eleve_id",   type="string", format="uuid"),
 *             @OA\Property(property="type",       type="string", enum={"entrée","retard","sortie_anticipée","convocation"}),
 *             @OA\Property(property="motif",      type="string", nullable=true),
 *             @OA\Property(property="heure",      type="string", example="08:47"),
 *             @OA\Property(property="autorise_par",type="string", nullable=true)
 *         )
 *     ),
 *     @OA\Response(response=201, description="Billet émis", @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
 *     @OA\Response(response=422, description="Type invalide", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 */
public function store(Request $request): JsonResponse
```

---

## ÉTAPE 11 — Annoter StockInventaireController

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/StockInventaireController.php`

```php
/**
 * @OA\Get(
 *     path="/api/v1/stock/articles",
 *     summary="Liste des articles en stock",
 *     tags={"Stock"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\Parameter(name="categorie",     in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="alerte_stock",  in="query", @OA\Schema(type="boolean", description="true = articles sous le seuil")),
 *     @OA\Parameter(name="per_page",      in="query", @OA\Schema(type="integer", default=20)),
 *     @OA\Response(response=200, description="Articles paginés", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
 * )
 */
public function indexArticles(Request $request): JsonResponse
```

```php
/**
 * @OA\Post(
 *     path="/api/v1/stock/mouvements",
 *     summary="Enregistrer un mouvement de stock (entrée / sortie / transfert)",
 *     tags={"Stock"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"article_id","type","quantite"},
 *             @OA\Property(property="article_id", type="string",  format="uuid"),
 *             @OA\Property(property="type",       type="string",  enum={"entrée","sortie","transfert","perte","retour"}),
 *             @OA\Property(property="quantite",   type="integer", example=10),
 *             @OA\Property(property="motif",      type="string",  nullable=true),
 *             @OA\Property(property="prix_unitaire",type="number",format="float", nullable=true)
 *         )
 *     ),
 *     @OA\Response(response=201, description="Mouvement enregistré", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
 * )
 */
public function storeMouvement(Request $request): JsonResponse
```

---

## ÉTAPE 12 — Annoter BudgetController

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/BudgetController.php`

```php
/**
 * @OA\Get(
 *     path="/api/v1/budget/dashboard",
 *     summary="Dashboard budget (recettes, dépenses, résultat net)",
 *     tags={"Budget"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\Parameter(name="mois",  in="query", @OA\Schema(type="integer", example=7)),
 *     @OA\Parameter(name="annee", in="query", @OA\Schema(type="integer", example=2026)),
 *     @OA\Response(
 *         response=200,
 *         description="Données budget du mois",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="recettes",    type="number", format="float"),
 *                 @OA\Property(property="depenses",    type="number", format="float"),
 *                 @OA\Property(property="resultat_net",type="number", format="float"),
 *                 @OA\Property(property="impayes",     type="number", format="float"),
 *                 @OA\Property(property="evolution",   type="array",  @OA\Items(type="object"))
 *             )
 *         )
 *     )
 * )
 */
public function dashboard(Request $request): JsonResponse
```

```php
/**
 * @OA\Get(
 *     path="/api/v1/budget/previsionnel",
 *     summary="Budget prévisionnel vs réalisé par catégorie",
 *     tags={"Budget"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\Parameter(name="annee", in="query", required=false, @OA\Schema(type="integer", example=2026)),
 *     @OA\Parameter(name="mois",  in="query", required=false, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Prévisionnel vs réalisé", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
 * )
 */
public function previsionnel(Request $request): JsonResponse
```

---

## ÉTAPE 13 — Annoter PersonnelController

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/PersonnelController.php`

```php
/**
 * @OA\Get(
 *     path="/api/v1/personnel",
 *     summary="Liste du personnel non-enseignant",
 *     tags={"Personnel"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\Parameter(name="statut",   in="query", @OA\Schema(type="string", enum={"actif","inactif","congé"})),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
 *     @OA\Response(response=200, description="Personnel paginé", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
 * )
 */
public function index(Request $request): JsonResponse
```

```php
/**
 * @OA\Post(
 *     path="/api/v1/personnel/{personnelId}/paie",
 *     summary="Générer la fiche de paie mensuelle (calcul IRG + CNAS auto)",
 *     tags={"Personnel"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\Parameter(name="personnelId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"mois","annee"},
 *             @OA\Property(property="mois",            type="integer", example=7),
 *             @OA\Property(property="annee",           type="integer", example=2026),
 *             @OA\Property(property="primes",          type="number",  format="float", default=0),
 *             @OA\Property(property="deductions",      type="number",  format="float", default=0),
 *             @OA\Property(property="heures_supp",     type="number",  format="float", default=0)
 *         )
 *     ),
 *     @OA\Response(response=201, description="Fiche de paie générée", @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
 *     @OA\Response(response=422, description="Données invalides",     @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 */
public function genererPaie(Request $request, string $personnelId): JsonResponse
```

---

## ÉTAPE 14 — Annoter EntretienController

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/EntretienController.php`

```php
/**
 * @OA\Get(
 *     path="/api/v1/entretien/interventions",
 *     summary="Liste des interventions d'entretien",
 *     tags={"Entretien"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\Parameter(name="statut",   in="query", @OA\Schema(type="string", enum={"en_attente","en_cours","terminée","annulée"})),
 *     @OA\Parameter(name="priorite", in="query", @OA\Schema(type="string", enum={"basse","normale","haute","urgente"})),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
 *     @OA\Response(response=200, description="Interventions paginées", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
 * )
 */
public function indexInterventions(Request $request): JsonResponse
```

```php
/**
 * @OA\Post(
 *     path="/api/v1/entretien/interventions",
 *     summary="Créer une intervention d'entretien",
 *     tags={"Entretien"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"local_id","description","priorite"},
 *             @OA\Property(property="local_id",    type="string", format="uuid"),
 *             @OA\Property(property="description", type="string"),
 *             @OA\Property(property="priorite",    type="string", enum={"basse","normale","haute","urgente"}),
 *             @OA\Property(property="prestataire_id",type="string",format="uuid",nullable=true),
 *             @OA\Property(property="date_prevue", type="string", format="date", nullable=true)
 *         )
 *     ),
 *     @OA\Response(response=201, description="Intervention créée", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
 * )
 */
public function storeIntervention(Request $request): JsonResponse
```

---

## ÉTAPE 15 — Variables d'environnement Swagger

**Modifier :** `edugestdz/backend/.env` (ajouter à la fin)

```dotenv
L5_SWAGGER_GENERATE_ALWAYS=false
L5_SWAGGER_BASE_PATH=/api/v1
L5_SWAGGER_CONST_HOST=http://localhost
```

**Modifier :** `edugestdz/backend/.env.production.example` (ajouter)

```dotenv
L5_SWAGGER_GENERATE_ALWAYS=false
L5_SWAGGER_BASE_PATH=/api/v1
L5_SWAGGER_CONST_HOST=https://app.edugest.dz
```

---

## ÉTAPE 16 — Route Swagger (vérification)

**Vérifier :** `edugestdz/backend/routes/web.php`

Le package l5-swagger enregistre automatiquement la route `/api/documentation`.
Si elle n'apparaît pas après install, ajouter manuellement dans `routes/web.php` :

```php
// Swagger UI — développement uniquement
if (app()->environment(['local', 'staging'])) {
    Route::get('/api/documentation', function () {
        return view('l5-swagger::index');
    });
}
```

---

## ÉTAPE 17 — Générer la documentation

```bash
cd edugestdz/backend
php artisan l5-swagger:generate
```

**Attendu :** `storage/api-docs/api-docs.json` créé (≥ 50KB).

---

## ÉTAPE 18 — Tests unitaires Swagger

**Créer :** `edugestdz/backend/tests/Feature/SwaggerTest.php`

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class SwaggerTest extends TestCase
{
    /**
     * Le fichier api-docs.json est bien généré et valide.
     */
    public function test_swagger_json_exists_and_is_valid(): void
    {
        $docsPath = storage_path('api-docs/api-docs.json');

        // Générer si absent (CI)
        if (! file_exists($docsPath)) {
            $this->artisan('l5-swagger:generate');
        }

        $this->assertFileExists($docsPath, 'api-docs.json doit être généré');

        $json = json_decode(file_get_contents($docsPath), true);
        $this->assertNotNull($json, 'api-docs.json doit être du JSON valide');
        $this->assertArrayHasKey('openapi', $json);
        $this->assertArrayHasKey('info', $json);
        $this->assertArrayHasKey('paths', $json);
    }

    /**
     * La documentation couvre les endpoints critiques.
     */
    public function test_swagger_covers_critical_endpoints(): void
    {
        $docsPath = storage_path('api-docs/api-docs.json');

        if (! file_exists($docsPath)) {
            $this->artisan('l5-swagger:generate');
        }

        $json  = json_decode(file_get_contents($docsPath), true);
        $paths = array_keys($json['paths'] ?? []);

        $critical = [
            '/api/v1/auth/login',
            '/api/v1/eleves',
            '/api/v1/finance/tableau-bord',
            '/api/v1/transport/circuits',
            '/api/v1/budget/dashboard',
        ];

        foreach ($critical as $endpoint) {
            $this->assertContains(
                $endpoint,
                $paths,
                "L'endpoint {$endpoint} doit être documenté dans Swagger"
            );
        }
    }

    /**
     * La sécurité JWT est définie.
     */
    public function test_swagger_defines_jwt_security(): void
    {
        $docsPath = storage_path('api-docs/api-docs.json');

        if (! file_exists($docsPath)) {
            $this->artisan('l5-swagger:generate');
        }

        $json = json_decode(file_get_contents($docsPath), true);

        $schemes = $json['components']['securitySchemes'] ?? [];
        $this->assertArrayHasKey('bearerAuth', $schemes, 'bearerAuth doit être défini');
        $this->assertSame('http',   $schemes['bearerAuth']['type']);
        $this->assertSame('bearer', $schemes['bearerAuth']['scheme']);
    }
}
```

---

## ORDRE D'EXÉCUTION DEEPSEEK

```bash
# 0. Synchroniser
git checkout develop
git pull origin main

# 1. Installer le package
cd edugestdz/backend
composer require "darkaonline/l5-swagger:^8.6"
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"

# 2. Créer les fichiers Virtual
create: edugestdz/backend/app/Virtual/OpenApiDefinition.php
create: edugestdz/backend/app/Virtual/Schemas.php

# 3. Modifier la config
modify: edugestdz/backend/config/l5-swagger.php
  → info, securityDefinitions, paths.annotations

# 4. Annoter les contrôleurs (ÉTAPES 5 à 14)
modify: AuthController.php           → login, logout, refresh, me
modify: EleveController.php          → index, store, show, update, destroy
modify: FinanceController.php        → getTableauBord, indexFactures, storePaiement
modify: SatimController.php          → initierPaiement, confirmerPaiement, rembourser
modify: TransportController.php      → indexCircuits, pointerEleve
modify: AbsenceController.php        → index, store
modify: BilletController.php         → store
modify: StockInventaireController.php→ indexArticles, storeMouvement
modify: BudgetController.php         → dashboard, previsionnel
modify: PersonnelController.php      → index, genererPaie
modify: EntretienController.php      → indexInterventions, storeIntervention

# 5. Variables d'environnement
modify: .env                         → L5_SWAGGER_* variables
modify: .env.production.example      → idem (host = app.edugest.dz)

# 6. Générer la documentation
php artisan l5-swagger:generate
# → storage/api-docs/api-docs.json doit être créé

# 7. Créer le test Swagger
create: edugestdz/backend/tests/Feature/SwaggerTest.php

# 8. Lancer les tests — AUCUNE régression tolérée
php artisan test --parallel
# → Attendu : exactement 316 tests verts (313 existants + 3 nouveaux)

# 9. Commit & push
git add .
git commit -m "feat: Swagger/OpenAPI documentation — tous les endpoints V1 annotés + 3 tests"
git push origin develop

# 10. Ouvrir la PR develop → main sur GitHub
```

---

## VÉRIFICATION MANUELLE

Après merge, ouvrir dans le navigateur :

```
http://localhost/api/documentation
```

**Attendu :**
- Interface Swagger UI avec tous les tags visibles (Auth, Eleves, Finances, Transport…)
- Bouton "Authorize" → saisir `Bearer <token>` → tous les endpoints testables en live
- Header `X-Tenant-ID` visible sur chaque endpoint

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_SWAGGER_OPENAPI.md — 18 étapes dans l'ordre.

RÈGLE : php artisan test --parallel → 316 tests verts après cette mission (313 + 3 nouveaux).
Ne pas modifier la logique des contrôleurs — uniquement ajouter des annotations PHPDoc.
Ne pas modifier les signatures des méthodes.

Après le commit & push, ouvrir une PR develop → main.
```
