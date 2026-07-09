# 🤖 MISSION DEEPSEEK — Marketplace & Matching IA (Priorité 7)
## EduGest DZ · Branche : develop · 2 Juillet 2026
## Tests actuels : 381 ✅ · Objectif : ≥ 395 ✅ (0 régression)

---

## CONTEXTE

La marketplace EduGest DZ permet aux **parents** de trouver des centres/enseignants
et aux **centres** de se faire référencer publiquement.

### Ce qui existe déjà dans le code (ne pas recréer)
- `OffrePublique` model + controller (partiel)
- `MatchingService` avec algorithme de score
- Avis/Notation enseignants (partiel)
- Routes `/api/v1/marketplace/*` partiellement définies

### Ce qui manque — à construire dans cette mission
1. **Réservations** : parent réserve une séance d'essai ou un cours
2. **Interface publique** : endpoints sans authentification (vitrine centre)
3. **Paiement en ligne** réservation via Satim (réutiliser SatimGateway)
4. **Page React** marketplace complète
5. **Écrans mobile** parent pour découverte + réservation
6. **Tests** couvrant tous les nouveaux endpoints

### RÈGLES ABSOLUES
1. **0 régression** — les 381 tests existants restent verts
2. **PostgreSQL uniquement** — jamais SQLite
3. **Ne pas modifier** les signatures API existantes
4. **Réutiliser** `SatimGateway` existant pour le paiement réservation

---

## ÉTAPE 0 — Synchroniser develop

```bash
git checkout develop
git pull origin main
```

---

## ÉTAPE 1 — Migrations : tables marketplace

**Créer :** `edugestdz/backend/database/migrations/2026_07_02_100000_create_marketplace_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Profils publics des centres ─────────────────────────────────
        Schema::create('profils_marketplace', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->unique();
            $table->string('nom_etablissement');
            $table->text('description')->nullable();
            $table->string('adresse');
            $table->string('wilaya', 60);
            $table->string('commune', 60)->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('site_web')->nullable();
            $table->string('logo_url')->nullable();
            $table->jsonb('photos_urls')->default('[]');
            $table->jsonb('matieres_enseignees')->default('[]'); // ["Maths","Physique","Anglais"]
            $table->jsonb('niveaux_couverts')->default('[]');   // ["1AS","2AS","3AS"]
            $table->jsonb('horaires')->default('{}');            // {"lundi":"8h-18h","mardi":"8h-18h"}
            $table->decimal('tarif_heure_min', 8, 2)->nullable();
            $table->decimal('tarif_heure_max', 8, 2)->nullable();
            $table->boolean('accepte_essai_gratuit')->default(false);
            $table->boolean('visible')->default(true);
            $table->boolean('verifie')->default(false); // vérifié par super-admin
            $table->integer('nb_eleves_actifs')->default(0);
            $table->integer('annees_experience')->default(0);
            $table->decimal('note_moyenne', 3, 2)->default(0);
            $table->integer('nb_avis')->default(0);
            $table->timestamps();

            $table->index(['wilaya', 'visible'], 'idx_profil_wilaya_visible');
            $table->index(['note_moyenne', 'visible'], 'idx_profil_note_visible');
        });

        // ── Offres de cours individuelles ───────────────────────────────
        Schema::create('offres_cours', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('enseignant_id')->nullable(); // si offre individuelle
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('matiere');
            $table->jsonb('niveaux')->default('[]');
            $table->enum('type', ['groupe', 'individuel', 'en_ligne'])->default('individuel');
            $table->decimal('tarif_heure', 8, 2);
            $table->integer('duree_seance')->default(60); // minutes
            $table->integer('nb_places_max')->nullable();
            $table->boolean('essai_gratuit')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['matiere', 'active'], 'idx_offre_matiere_active');
            $table->index(['tenant_id', 'active'], 'idx_offre_tenant_active');
        });

        // ── Réservations ────────────────────────────────────────────────
        Schema::create('reservations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('offre_id');
            $table->uuid('parent_id');       // user_id du parent
            $table->uuid('eleve_id');
            $table->uuid('tenant_id');
            $table->dateTime('date_souhaitee');
            $table->integer('duree_minutes')->default(60);
            $table->enum('type', ['essai', 'cours_regulier', 'cours_unique'])->default('cours_unique');
            $table->enum('statut', [
                'en_attente',    // soumise, pas encore confirmée
                'confirmee',     // confirmée par le centre
                'annulee_parent',
                'annulee_centre',
                'terminee',
                'no_show',
            ])->default('en_attente');
            $table->decimal('montant', 10, 2)->default(0);
            $table->enum('statut_paiement', ['gratuit', 'en_attente', 'paye', 'rembourse'])->default('en_attente');
            $table->uuid('paiement_id')->nullable(); // lien vers paiements Satim
            $table->text('message_parent')->nullable();
            $table->text('reponse_centre')->nullable();
            $table->timestamp('confirme_le')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('offre_id')->references('id')->on('offres_cours')->onDelete('cascade');
            $table->index(['parent_id', 'statut'], 'idx_resa_parent_statut');
            $table->index(['tenant_id', 'statut'], 'idx_resa_tenant_statut');
            $table->index(['date_souhaitee', 'statut'], 'idx_resa_date_statut');
        });

        // ── Avis & notations ────────────────────────────────────────────
        Schema::create('avis_marketplace', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');       // centre noté
            $table->uuid('parent_id');       // auteur
            $table->uuid('reservation_id')->nullable(); // lié à une réservation terminée
            $table->tinyInteger('note')->unsigned(); // 1-5
            $table->string('titre')->nullable();
            $table->text('commentaire')->nullable();
            $table->boolean('visible')->default(true);
            $table->boolean('verifie')->default(false);
            $table->timestamp('publie_le')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'parent_id', 'reservation_id'], 'uniq_avis_parent_resa');
            $table->index(['tenant_id', 'visible'], 'idx_avis_tenant_visible');
        });

        // ── Favoris ─────────────────────────────────────────────────────
        Schema::create('favoris_marketplace', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('parent_id');
            $table->uuid('tenant_id'); // centre mis en favori
            $table->timestamps();

            $table->unique(['parent_id', 'tenant_id'], 'uniq_favori');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favoris_marketplace');
        Schema::dropIfExists('avis_marketplace');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('offres_cours');
        Schema::dropIfExists('profils_marketplace');
    }
};
```

---

## ÉTAPE 2 — Models

**Créer :** `edugestdz/backend/app/Models/ProfilMarketplace.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ProfilMarketplace extends Model
{
    use HasUuids;

    protected $table = 'profils_marketplace';

    protected $fillable = [
        'tenant_id', 'nom_etablissement', 'description', 'adresse',
        'wilaya', 'commune', 'telephone', 'email', 'site_web',
        'logo_url', 'photos_urls', 'matieres_enseignees', 'niveaux_couverts',
        'horaires', 'tarif_heure_min', 'tarif_heure_max',
        'accepte_essai_gratuit', 'visible', 'verifie',
        'nb_eleves_actifs', 'annees_experience', 'note_moyenne', 'nb_avis',
    ];

    protected $casts = [
        'photos_urls'        => 'array',
        'matieres_enseignees'=> 'array',
        'niveaux_couverts'   => 'array',
        'horaires'           => 'array',
        'tarif_heure_min'    => 'decimal:2',
        'tarif_heure_max'    => 'decimal:2',
        'note_moyenne'       => 'decimal:2',
        'visible'            => 'boolean',
        'verifie'            => 'boolean',
        'accepte_essai_gratuit' => 'boolean',
    ];

    // Relations
    public function offres()
    {
        return $this->hasMany(OffreCours::class, 'tenant_id', 'tenant_id');
    }

    public function avis()
    {
        return $this->hasMany(AvisMarketplace::class, 'tenant_id', 'tenant_id')
            ->where('visible', true)
            ->orderByDesc('created_at');
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'tenant_id', 'tenant_id');
    }

    // Scopes
    public function scopeVisible($query)
    {
        return $query->where('visible', true);
    }

    public function scopeWilaya($query, string $wilaya)
    {
        return $query->where('wilaya', $wilaya);
    }

    public function scopeMatiere($query, string $matiere)
    {
        return $query->whereJsonContains('matieres_enseignees', $matiere);
    }

    public function scopeNiveau($query, string $niveau)
    {
        return $query->whereJsonContains('niveaux_couverts', $niveau);
    }

    // Recalculer la note moyenne après un avis
    public function recalculerNote(): void
    {
        $stats = AvisMarketplace::where('tenant_id', $this->tenant_id)
            ->where('visible', true)
            ->selectRaw('AVG(note) as moyenne, COUNT(*) as total')
            ->first();

        $this->update([
            'note_moyenne' => round((float) $stats->moyenne, 2),
            'nb_avis'      => (int) $stats->total,
        ]);
    }
}
```

**Créer :** `edugestdz/backend/app/Models/OffreCours.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class OffreCours extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'offres_cours';

    protected $fillable = [
        'tenant_id', 'enseignant_id', 'titre', 'description',
        'matiere', 'niveaux', 'type', 'tarif_heure', 'duree_seance',
        'nb_places_max', 'essai_gratuit', 'active',
    ];

    protected $casts = [
        'niveaux'      => 'array',
        'tarif_heure'  => 'decimal:2',
        'essai_gratuit'=> 'boolean',
        'active'       => 'boolean',
    ];

    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'offre_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
```

**Créer :** `edugestdz/backend/app/Models/Reservation.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'offre_id', 'parent_id', 'eleve_id', 'tenant_id',
        'date_souhaitee', 'duree_minutes', 'type', 'statut',
        'montant', 'statut_paiement', 'paiement_id',
        'message_parent', 'reponse_centre', 'confirme_le',
    ];

    protected $casts = [
        'date_souhaitee' => 'datetime',
        'confirme_le'    => 'datetime',
        'montant'        => 'decimal:2',
    ];

    public function offre()
    {
        return $this->belongsTo(OffreCours::class, 'offre_id');
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function eleve()
    {
        return $this->belongsTo(Eleve::class, 'eleve_id');
    }

    public function avis()
    {
        return $this->hasOne(AvisMarketplace::class, 'reservation_id');
    }

    public function peutEtreAnnule(): bool
    {
        return in_array($this->statut, ['en_attente', 'confirmee'])
            && $this->date_souhaitee->isFuture();
    }
}
```

**Créer :** `edugestdz/backend/app/Models/AvisMarketplace.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AvisMarketplace extends Model
{
    use HasUuids;

    protected $table = 'avis_marketplace';

    protected $fillable = [
        'tenant_id', 'parent_id', 'reservation_id',
        'note', 'titre', 'commentaire', 'visible', 'verifie', 'publie_le',
    ];

    protected $casts = [
        'note'      => 'integer',
        'visible'   => 'boolean',
        'verifie'   => 'boolean',
        'publie_le' => 'datetime',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    protected static function booted(): void
    {
        // Recalcule la note du profil après chaque avis
        static::saved(function (AvisMarketplace $avis) {
            optional(ProfilMarketplace::where('tenant_id', $avis->tenant_id)->first())
                ->recalculerNote();
        });
    }
}
```

---

## ÉTAPE 3 — MarketplaceService

**Créer :** `edugestdz/backend/app/Services/MarketplaceService.php`

```php
<?php

namespace App\Services;

use App\Models\ProfilMarketplace;
use App\Models\OffreCours;
use App\Models\Reservation;
use App\Models\AvisMarketplace;
use Illuminate\Support\Facades\Cache;

class MarketplaceService
{
    /**
     * Rechercher des centres avec filtres + score de matching.
     * Résultats mis en cache 5 minutes (ne change pas à chaque requête).
     */
    public function rechercher(array $filtres): \Illuminate\Support\Collection
    {
        $cacheKey = 'marketplace_search_' . md5(serialize($filtres));

        return Cache::remember($cacheKey, 300, function () use ($filtres) {
            $query = ProfilMarketplace::visible()
                ->with(['offres' => fn($q) => $q->active()->select('id','tenant_id','matiere','tarif_heure','type','essai_gratuit')]);

            if (! empty($filtres['wilaya'])) {
                $query->wilaya($filtres['wilaya']);
            }
            if (! empty($filtres['matiere'])) {
                $query->matiere($filtres['matiere']);
            }
            if (! empty($filtres['niveau'])) {
                $query->niveau($filtres['niveau']);
            }
            if (! empty($filtres['tarif_max'])) {
                $query->where('tarif_heure_min', '<=', $filtres['tarif_max']);
            }
            if (! empty($filtres['essai_gratuit'])) {
                $query->where('accepte_essai_gratuit', true);
            }
            if (! empty($filtres['verifie'])) {
                $query->where('verifie', true);
            }

            return $query->get()
                ->map(fn($profil) => $this->scorerProfil($profil, $filtres))
                ->sortByDesc('score')
                ->values();
        });
    }

    /**
     * Score de matching : prioritise les centres vérifiés, bien notés,
     * avec essai gratuit, et correspondant aux critères.
     */
    private function scorerProfil(ProfilMarketplace $profil, array $filtres): array
    {
        $score = 0;

        // Note moyenne (max 50 pts)
        $score += (float) $profil->note_moyenne * 10;

        // Vérifié par admin (20 pts)
        if ($profil->verifie) $score += 20;

        // Essai gratuit (15 pts)
        if ($profil->accepte_essai_gratuit) $score += 15;

        // Nombre d'avis (max 10 pts)
        $score += min($profil->nb_avis, 10);

        // Expérience (max 5 pts)
        $score += min($profil->annees_experience, 5);

        return array_merge($profil->toArray(), ['score' => round($score, 1)]);
    }

    /**
     * Créer une réservation avec calcul du montant.
     */
    public function creerReservation(array $data): Reservation
    {
        $offre = OffreCours::findOrFail($data['offre_id']);

        // Calcul du montant
        $duree   = $data['duree_minutes'] ?? $offre->duree_seance;
        $montant = $offre->essai_gratuit && ($data['type'] ?? '') === 'essai'
            ? 0
            : round($offre->tarif_heure * ($duree / 60), 2);

        $reservation = Reservation::create([
            'offre_id'        => $offre->id,
            'parent_id'       => $data['parent_id'],
            'eleve_id'        => $data['eleve_id'],
            'tenant_id'       => $offre->tenant_id,
            'date_souhaitee'  => $data['date_souhaitee'],
            'duree_minutes'   => $duree,
            'type'            => $data['type'] ?? 'cours_unique',
            'statut'          => 'en_attente',
            'montant'         => $montant,
            'statut_paiement' => $montant == 0 ? 'gratuit' : 'en_attente',
            'message_parent'  => $data['message_parent'] ?? null,
        ]);

        // Invalider le cache recherche (les stats du centre changent)
        Cache::tags(['marketplace'])->flush();

        return $reservation->load('offre');
    }

    /**
     * Confirmer une réservation (côté centre).
     */
    public function confirmerReservation(Reservation $reservation, string $reponse = null): Reservation
    {
        $reservation->update([
            'statut'         => 'confirmee',
            'reponse_centre' => $reponse,
            'confirme_le'    => now(),
        ]);

        return $reservation;
    }

    /**
     * Annuler une réservation.
     */
    public function annulerReservation(Reservation $reservation, string $par, string $motif = null): Reservation
    {
        if (! $reservation->peutEtreAnnule()) {
            throw new \RuntimeException('Cette réservation ne peut plus être annulée.');
        }

        $reservation->update([
            'statut'          => $par === 'parent' ? 'annulee_parent' : 'annulee_centre',
            'reponse_centre'  => $motif,
        ]);

        return $reservation;
    }

    /**
     * Statistiques marketplace pour le super-admin.
     */
    public function getStats(): array
    {
        return Cache::remember('marketplace_stats', 600, fn() => [
            'profils_actifs'      => ProfilMarketplace::where('visible', true)->count(),
            'profils_verifies'    => ProfilMarketplace::where('verifie', true)->count(),
            'total_reservations'  => Reservation::count(),
            'reservations_mois'   => Reservation::whereMonth('created_at', now()->month)->count(),
            'taux_confirmation'   => $this->tauxConfirmation(),
            'note_moyenne_globale'=> round((float) ProfilMarketplace::avg('note_moyenne'), 2),
            'top_wilayas'         => ProfilMarketplace::visible()
                ->selectRaw('wilaya, COUNT(*) as total')
                ->groupBy('wilaya')
                ->orderByDesc('total')
                ->limit(5)
                ->pluck('total', 'wilaya'),
        ]);
    }

    private function tauxConfirmation(): float
    {
        $total     = Reservation::whereNotIn('statut', ['en_attente'])->count();
        $confirmes = Reservation::where('statut', 'confirmee')->count();
        return $total > 0 ? round(($confirmes / $total) * 100, 1) : 0;
    }
}
```

---

## ÉTAPE 4 — MarketplaceController (public + authentifié)

**Créer :** `edugestdz/backend/app/Http/Controllers/Api/V1/MarketplaceController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProfilMarketplace;
use App\Models\OffreCours;
use App\Models\Reservation;
use App\Models\AvisMarketplace;
use App\Services\MarketplaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MarketplaceController extends Controller
{
    public function __construct(private MarketplaceService $service) {}

    // ════════════════════════════════════════════════════════════════
    // ENDPOINTS PUBLICS (sans authentification)
    // ════════════════════════════════════════════════════════════════

    /**
     * @OA\Get(
     *     path="/api/v1/marketplace/recherche",
     *     summary="Rechercher des centres (public, sans auth)",
     *     tags={"Marketplace"},
     *     @OA\Parameter(name="wilaya",      in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="matiere",     in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="niveau",      in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="tarif_max",   in="query", @OA\Schema(type="number")),
     *     @OA\Parameter(name="essai_gratuit",in="query",@OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="Centres avec score de matching")
     * )
     */
    public function recherche(Request $request): JsonResponse
    {
        $filtres  = $request->only(['wilaya', 'matiere', 'niveau', 'tarif_max', 'essai_gratuit', 'verifie']);
        $resultats = $this->service->rechercher($filtres);

        return response()->json([
            'success' => true,
            'data'    => [
                'centres' => $resultats,
                'total'   => $resultats->count(),
                'filtres' => $filtres,
            ],
            'message' => 'Résultats de recherche',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/marketplace/centres/{tenantId}",
     *     summary="Profil public d'un centre (sans auth)",
     *     tags={"Marketplace"},
     *     @OA\Parameter(name="tenantId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Profil complet du centre"),
     *     @OA\Response(response=404, description="Centre non trouvé")
     * )
     */
    public function profilPublic(string $tenantId): JsonResponse
    {
        $profil = ProfilMarketplace::where('tenant_id', $tenantId)
            ->where('visible', true)
            ->with([
                'offres' => fn($q) => $q->active(),
                'avis'   => fn($q) => $q->with('parent:id,prenom,nom')->limit(10),
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => $profil,
            'message' => 'Profil centre',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/marketplace/featured",
     *     summary="Centres mis en avant (sans auth) — top 6 par note",
     *     tags={"Marketplace"},
     *     @OA\Response(response=200, description="Top centres")
     * )
     */
    public function featured(): JsonResponse
    {
        $centres = ProfilMarketplace::visible()
            ->where('verifie', true)
            ->orderByDesc('note_moyenne')
            ->orderByDesc('nb_avis')
            ->limit(6)
            ->get(['id','tenant_id','nom_etablissement','wilaya','logo_url',
                   'note_moyenne','nb_avis','tarif_heure_min','tarif_heure_max',
                   'matieres_enseignees','accepte_essai_gratuit']);

        return response()->json(['success' => true, 'data' => $centres]);
    }

    // ════════════════════════════════════════════════════════════════
    // ENDPOINTS CENTRE (auth + rôle admin du tenant)
    // ════════════════════════════════════════════════════════════════

    /**
     * @OA\Get(
     *     path="/api/v1/marketplace/mon-profil",
     *     summary="Profil marketplace du centre connecté",
     *     tags={"Marketplace"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Response(response=200, description="Profil du centre connecté")
     * )
     */
    public function monProfil(Request $request): JsonResponse
    {
        $tenantId = config('tenant.current_id');
        $profil   = ProfilMarketplace::firstOrCreate(
            ['tenant_id' => $tenantId],
            ['nom_etablissement' => 'Mon centre', 'wilaya' => 'Alger', 'adresse' => '']
        );

        return response()->json(['success' => true, 'data' => $profil]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/marketplace/mon-profil",
     *     summary="Mettre à jour le profil marketplace",
     *     tags={"Marketplace"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="nom_etablissement", type="string"),
     *         @OA\Property(property="description",       type="string"),
     *         @OA\Property(property="adresse",           type="string"),
     *         @OA\Property(property="wilaya",            type="string"),
     *         @OA\Property(property="tarif_heure_min",   type="number"),
     *         @OA\Property(property="tarif_heure_max",   type="number"),
     *         @OA\Property(property="matieres_enseignees",type="array", @OA\Items(type="string")),
     *         @OA\Property(property="niveaux_couverts",  type="array", @OA\Items(type="string"))
     *     )),
     *     @OA\Response(response=200, description="Profil mis à jour")
     * )
     */
    public function updateProfil(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom_etablissement'   => 'sometimes|string|max:255',
            'description'         => 'sometimes|nullable|string',
            'adresse'             => 'sometimes|string|max:500',
            'wilaya'              => 'sometimes|string|max:60',
            'commune'             => 'sometimes|nullable|string|max:60',
            'telephone'           => 'sometimes|nullable|string|max:20',
            'email'               => 'sometimes|nullable|email',
            'site_web'            => 'sometimes|nullable|url',
            'tarif_heure_min'     => 'sometimes|nullable|numeric|min:0',
            'tarif_heure_max'     => 'sometimes|nullable|numeric|min:0',
            'matieres_enseignees' => 'sometimes|array',
            'niveaux_couverts'    => 'sometimes|array',
            'horaires'            => 'sometimes|array',
            'accepte_essai_gratuit' => 'sometimes|boolean',
            'visible'             => 'sometimes|boolean',
        ]);

        $profil = ProfilMarketplace::where('tenant_id', config('tenant.current_id'))
            ->firstOrFail();
        $profil->update($validated);

        return response()->json(['success' => true, 'data' => $profil, 'message' => 'Profil mis à jour']);
    }

    // ── Offres de cours ──────────────────────────────────────────────

    /**
     * @OA\Get(path="/api/v1/marketplace/offres", summary="Offres du centre connecté",
     *     tags={"Marketplace"}, security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Liste des offres"))
     */
    public function indexOffres(Request $request): JsonResponse
    {
        $offres = OffreCours::where('tenant_id', config('tenant.current_id'))
            ->when($request->filled('matiere'), fn($q) => $q->where('matiere', $request->matiere))
            ->when($request->filled('active'),  fn($q) => $q->where('active', (bool) $request->active))
            ->withCount('reservations')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $offres]);
    }

    /**
     * @OA\Post(path="/api/v1/marketplace/offres", summary="Créer une offre de cours",
     *     tags={"Marketplace"}, security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"titre","matiere","tarif_heure"},
     *         @OA\Property(property="titre",       type="string"),
     *         @OA\Property(property="matiere",     type="string"),
     *         @OA\Property(property="niveaux",     type="array", @OA\Items(type="string")),
     *         @OA\Property(property="tarif_heure", type="number"),
     *         @OA\Property(property="type",        type="string", enum={"individuel","groupe","en_ligne"}),
     *         @OA\Property(property="essai_gratuit",type="boolean")
     *     )),
     *     @OA\Response(response=201, description="Offre créée"))
     */
    public function storeOffre(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'titre'         => 'required|string|max:200',
            'description'   => 'nullable|string',
            'matiere'       => 'required|string|max:100',
            'niveaux'       => 'array',
            'type'          => 'in:individuel,groupe,en_ligne',
            'tarif_heure'   => 'required|numeric|min:0',
            'duree_seance'  => 'integer|min:30|max:240',
            'nb_places_max' => 'nullable|integer|min:1',
            'essai_gratuit' => 'boolean',
        ]);

        $offre = OffreCours::create([
            ...$validated,
            'tenant_id' => config('tenant.current_id'),
            'active'    => true,
        ]);

        return response()->json(['success' => true, 'data' => $offre, 'message' => 'Offre créée'], 201);
    }

    // ── Réservations (côté centre) ────────────────────────────────────

    /**
     * @OA\Get(path="/api/v1/marketplace/reservations", summary="Réservations reçues par le centre",
     *     tags={"Marketplace"}, security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="statut", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Réservations paginées"))
     */
    public function indexReservationsCentre(Request $request): JsonResponse
    {
        $reservations = Reservation::where('tenant_id', config('tenant.current_id'))
            ->when($request->filled('statut'), fn($q) => $q->where('statut', $request->statut))
            ->with(['offre:id,titre,matiere', 'eleve:id,nom,prenom', 'parent:id,nom,prenom,email'])
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $reservations]);
    }

    /**
     * @OA\Post(path="/api/v1/marketplace/reservations/{id}/confirmer",
     *     summary="Confirmer une réservation (côté centre)",
     *     tags={"Marketplace"}, security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Réservation confirmée"))
     */
    public function confirmerReservation(Request $request, string $id): JsonResponse
    {
        $reservation = Reservation::where('tenant_id', config('tenant.current_id'))
            ->where('statut', 'en_attente')
            ->findOrFail($id);

        $updated = $this->service->confirmerReservation(
            $reservation,
            $request->input('reponse')
        );

        return response()->json(['success' => true, 'data' => $updated, 'message' => 'Réservation confirmée']);
    }

    /**
     * @OA\Post(path="/api/v1/marketplace/reservations/{id}/annuler",
     *     summary="Annuler une réservation",
     *     tags={"Marketplace"}, security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Réservation annulée"),
     *     @OA\Response(response=422, description="Impossible d'annuler"))
     */
    public function annulerReservation(Request $request, string $id): JsonResponse
    {
        $reservation = Reservation::findOrFail($id);

        // Vérifier que le demandeur est le parent ou le centre
        $par = config('tenant.current_id') === $reservation->tenant_id ? 'centre' : 'parent';

        try {
            $updated = $this->service->annulerReservation($reservation, $par, $request->input('motif'));
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $updated, 'message' => 'Réservation annulée']);
    }

    // ── Réservations (côté parent) ────────────────────────────────────

    /**
     * @OA\Post(path="/api/v1/marketplace/reserver",
     *     summary="Réserver un cours (côté parent)",
     *     tags={"Marketplace"}, security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"offre_id","eleve_id","date_souhaitee"},
     *         @OA\Property(property="offre_id",       type="string", format="uuid"),
     *         @OA\Property(property="eleve_id",       type="string", format="uuid"),
     *         @OA\Property(property="date_souhaitee", type="string", format="date-time"),
     *         @OA\Property(property="type",           type="string", enum={"essai","cours_unique","cours_regulier"}),
     *         @OA\Property(property="message_parent", type="string", nullable=true)
     *     )),
     *     @OA\Response(response=201, description="Réservation créée"),
     *     @OA\Response(response=422, description="Données invalides"))
     */
    public function reserver(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'offre_id'       => 'required|uuid|exists:offres_cours,id',
            'eleve_id'       => 'required|uuid|exists:eleves,id',
            'date_souhaitee' => 'required|date|after:now',
            'type'           => 'in:essai,cours_unique,cours_regulier',
            'message_parent' => 'nullable|string|max:500',
            'duree_minutes'  => 'nullable|integer|min:30|max:240',
        ]);

        $reservation = $this->service->creerReservation([
            ...$validated,
            'parent_id' => auth('api')->id(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $reservation,
            'message' => 'Réservation soumise — en attente de confirmation du centre',
        ], 201);
    }

    /**
     * @OA\Get(path="/api/v1/marketplace/mes-reservations",
     *     summary="Réservations du parent connecté",
     *     tags={"Marketplace"}, security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Mes réservations"))
     */
    public function mesReservations(Request $request): JsonResponse
    {
        $reservations = Reservation::where('parent_id', auth('api')->id())
            ->with(['offre:id,titre,matiere,tarif_heure', 'eleve:id,nom,prenom'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $reservations]);
    }

    // ── Avis ──────────────────────────────────────────────────────────

    /**
     * @OA\Post(path="/api/v1/marketplace/avis",
     *     summary="Soumettre un avis sur un centre",
     *     tags={"Marketplace"}, security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"tenant_id","note"},
     *         @OA\Property(property="tenant_id",      type="string", format="uuid"),
     *         @OA\Property(property="reservation_id", type="string", format="uuid", nullable=true),
     *         @OA\Property(property="note",           type="integer", minimum=1, maximum=5),
     *         @OA\Property(property="titre",          type="string", nullable=true),
     *         @OA\Property(property="commentaire",    type="string", nullable=true)
     *     )),
     *     @OA\Response(response=201, description="Avis soumis"),
     *     @OA\Response(response=422, description="Avis déjà soumis pour cette réservation"))
     */
    public function soumettreAvis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id'      => 'required|uuid',
            'reservation_id' => 'nullable|uuid|exists:reservations,id',
            'note'           => 'required|integer|min:1|max:5',
            'titre'          => 'nullable|string|max:150',
            'commentaire'    => 'nullable|string|max:1000',
        ]);

        // Un seul avis par réservation
        if (! empty($validated['reservation_id'])) {
            $existe = AvisMarketplace::where('reservation_id', $validated['reservation_id'])->exists();
            if ($existe) {
                return response()->json(['success' => false, 'message' => 'Vous avez déjà noté cette réservation.'], 422);
            }
        }

        $avis = AvisMarketplace::create([
            ...$validated,
            'parent_id'  => auth('api')->id(),
            'publie_le'  => now(),
        ]);

        return response()->json(['success' => true, 'data' => $avis, 'message' => 'Avis soumis, merci !'], 201);
    }

    // ── Favoris ───────────────────────────────────────────────────────

    /**
     * @OA\Post(path="/api/v1/marketplace/favoris/{tenantId}",
     *     summary="Ajouter/retirer un centre des favoris",
     *     tags={"Marketplace"}, security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="tenantId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Favori ajouté ou retiré"))
     */
    public function toggleFavori(string $tenantId): JsonResponse
    {
        $parentId = auth('api')->id();

        $favori = \App\Models\FavoriMarketplace::where('parent_id', $parentId)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($favori) {
            $favori->delete();
            $action = 'retiré';
        } else {
            \App\Models\FavoriMarketplace::create(['parent_id' => $parentId, 'tenant_id' => $tenantId]);
            $action = 'ajouté';
        }

        return response()->json(['success' => true, 'message' => "Centre {$action} des favoris"]);
    }

    // ── Dashboard super-admin ─────────────────────────────────────────

    /**
     * @OA\Get(path="/api/v1/marketplace/stats",
     *     summary="Statistiques globales marketplace (super-admin)",
     *     tags={"Marketplace"}, security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="KPIs marketplace"))
     */
    public function stats(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->getStats()]);
    }
}
```

---

## ÉTAPE 5 — FavoriMarketplace model

**Créer :** `edugestdz/backend/app/Models/FavoriMarketplace.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class FavoriMarketplace extends Model
{
    use HasUuids;

    protected $table = 'favoris_marketplace';

    protected $fillable = ['parent_id', 'tenant_id'];
}
```

---

## ÉTAPE 6 — Routes API

**Modifier :** `edugestdz/backend/routes/api.php`

Ajouter après les routes existantes :

```php
// ══════════════════════════════════════════════════════════════
// MARKETPLACE
// ══════════════════════════════════════════════════════════════

// ── Endpoints publics (sans authentification) ──────────────────
Route::prefix('v1/marketplace')->group(function () {
    Route::get('/recherche',           [MarketplaceController::class, 'recherche']);
    Route::get('/featured',            [MarketplaceController::class, 'featured']);
    Route::get('/centres/{tenantId}',  [MarketplaceController::class, 'profilPublic']);
});

// ── Endpoints authentifiés ─────────────────────────────────────
Route::middleware(['auth:api', 'tenant'])->prefix('v1/marketplace')->group(function () {
    // Centre : gérer son profil
    Route::get('/mon-profil',    [MarketplaceController::class, 'monProfil']);
    Route::put('/mon-profil',    [MarketplaceController::class, 'updateProfil']);

    // Centre : gérer ses offres
    Route::get('/offres',        [MarketplaceController::class, 'indexOffres']);
    Route::post('/offres',       [MarketplaceController::class, 'storeOffre']);
    Route::delete('/offres/{id}',[MarketplaceController::class, 'destroyOffre'])
         ->missing(fn() => response()->json(['success' => false, 'message' => 'Offre non trouvée'], 404));

    // Centre : gérer les réservations reçues
    Route::get('/reservations',                          [MarketplaceController::class, 'indexReservationsCentre']);
    Route::post('/reservations/{id}/confirmer',          [MarketplaceController::class, 'confirmerReservation']);
    Route::post('/reservations/{id}/annuler',            [MarketplaceController::class, 'annulerReservation']);

    // Parent : réserver + voir ses réservations
    Route::post('/reserver',                             [MarketplaceController::class, 'reserver']);
    Route::get('/mes-reservations',                      [MarketplaceController::class, 'mesReservations']);

    // Avis
    Route::post('/avis',                                 [MarketplaceController::class, 'soumettreAvis']);

    // Favoris
    Route::post('/favoris/{tenantId}',                   [MarketplaceController::class, 'toggleFavori']);

    // Super-admin stats
    Route::get('/stats',                                 [MarketplaceController::class, 'stats']);
});
```

Ajouter l'import en haut du fichier api.php :
```php
use App\Http\Controllers\Api\V1\MarketplaceController;
```

---

## ÉTAPE 7 — Page React Marketplace

**Créer :** `edugestdz/frontend/src/pages/MarketplacePage.jsx`

```jsx
import { useState, useEffect } from 'react';
import { Search, Star, MapPin, BookOpen, Clock, CheckCircle, Heart, Filter } from 'lucide-react';

const WILAYAS_DZ = [
  'Adrar','Chlef','Laghouat','Oum El Bouaghi','Batna','Béjaïa','Biskra','Béchar',
  'Blida','Bouira','Tamanrasset','Tébessa','Tlemcen','Tiaret','Tizi Ouzou','Alger',
  'Djelfa','Jijel','Sétif','Saïda','Skikda','Sidi Bel Abbès','Annaba','Guelma',
  'Constantine','Médéa','Mostaganem','MSila','Mascara','Ouargla','Oran','El Bayadh',
  'Illizi','Bordj Bou Arréridj','Boumerdès','El Tarf','Tindouf','Tissemsilt',
  'El Oued','Khenchela','Souk Ahras','Tipaza','Mila','Aïn Defla','Naâma',
  'Aïn Témouchent','Ghardaïa','Relizane'
];

const MATIERES = ['Mathématiques','Physique','Chimie','Français','Anglais','Arabe',
  'Histoire-Géographie','Philosophie','Informatique','Sciences Naturelles','Tamazight'];

const NIVEAUX = ['1AP','2AP','3AP','4AP','5AP','1AM','2AM','3AM','4AM',
  '1AS','2AS','3AS'];

export default function MarketplacePage() {
  const [centres, setCentres]     = useState([]);
  const [featured, setFeatured]   = useState([]);
  const [loading, setLoading]     = useState(false);
  const [showFilters, setShowFilters] = useState(false);
  const [selectedCentre, setSelectedCentre] = useState(null);
  const [filtres, setFiltres]     = useState({
    wilaya: '', matiere: '', niveau: '', tarif_max: '', essai_gratuit: false,
  });

  useEffect(() => {
    fetchFeatured();
  }, []);

  const fetchFeatured = async () => {
    try {
      const res = await fetch('/api/v1/marketplace/featured');
      const data = await res.json();
      if (data.success) setFeatured(data.data);
    } catch (e) { console.error(e); }
  };

  const rechercher = async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      Object.entries(filtres).forEach(([k, v]) => { if (v) params.append(k, v); });
      const res  = await fetch(`/api/v1/marketplace/recherche?${params}`);
      const data = await res.json();
      if (data.success) setCentres(data.data.centres);
    } catch (e) { console.error(e); }
    finally { setLoading(false); }
  };

  const fetchProfil = async (tenantId) => {
    try {
      const res  = await fetch(`/api/v1/marketplace/centres/${tenantId}`);
      const data = await res.json();
      if (data.success) setSelectedCentre(data.data);
    } catch (e) { console.error(e); }
  };

  const Stars = ({ note }) => (
    <div style={{ display:'flex', gap:'2px' }}>
      {[1,2,3,4,5].map(i => (
        <Star key={i} size={12}
          fill={i <= Math.round(note) ? '#f59e0b' : 'none'}
          color={i <= Math.round(note) ? '#f59e0b' : '#475569'}
        />
      ))}
      <span style={{ fontSize:'11px', color:'#94a3b8', marginLeft:'4px' }}>{note}</span>
    </div>
  );

  const CentreCard = ({ centre, onClick }) => (
    <div onClick={onClick} style={{
      background:'#111318', border:'1px solid #1e293b', borderRadius:'12px',
      padding:'16px', cursor:'pointer', transition:'border-color .2s',
    }}
      onMouseEnter={e => e.currentTarget.style.borderColor='#3b82f6'}
      onMouseLeave={e => e.currentTarget.style.borderColor='#1e293b'}
    >
      <div style={{ display:'flex', gap:'12px', marginBottom:'10px' }}>
        <div style={{
          width:'48px', height:'48px', borderRadius:'10px',
          background:'#1e293b', display:'flex', alignItems:'center',
          justifyContent:'center', fontSize:'20px', flexShrink:0,
        }}>
          {centre.logo_url ? <img src={centre.logo_url} alt="" style={{ width:'100%', borderRadius:'10px' }} />
            : '🎓'}
        </div>
        <div style={{ flex:1, minWidth:0 }}>
          <div style={{ display:'flex', alignItems:'center', gap:'6px' }}>
            <span style={{ fontWeight:800, fontSize:'13px', color:'#f1f5f9' }}>
              {centre.nom_etablissement}
            </span>
            {centre.verifie && <CheckCircle size={13} color="#4ade80" />}
          </div>
          <div style={{ display:'flex', alignItems:'center', gap:'4px', color:'#64748b', fontSize:'11px' }}>
            <MapPin size={10} /> {centre.wilaya}
          </div>
          <Stars note={centre.note_moyenne} />
        </div>
        {centre.score && (
          <div style={{ fontSize:'10px', color:'#60a5fa', fontWeight:700 }}>
            Score {centre.score}
          </div>
        )}
      </div>

      <div style={{ display:'flex', flexWrap:'wrap', gap:'4px', marginBottom:'8px' }}>
        {(centre.matieres_enseignees || []).slice(0, 4).map(m => (
          <span key={m} style={{
            background:'#1e3a5f', color:'#60a5fa', fontSize:'9px',
            padding:'2px 7px', borderRadius:'20px',
          }}>{m}</span>
        ))}
      </div>

      <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center' }}>
        <span style={{ fontSize:'11px', color:'#94a3b8' }}>
          {centre.tarif_heure_min && `Dès ${centre.tarif_heure_min} DA/h`}
        </span>
        {centre.accepte_essai_gratuit && (
          <span style={{ background:'#14532d', color:'#4ade80', fontSize:'9px',
            padding:'2px 7px', borderRadius:'20px', fontWeight:700 }}>
            Essai gratuit
          </span>
        )}
      </div>
    </div>
  );

  return (
    <div style={{ minHeight:'100vh', background:'#08090f', color:'#e2e8f0', padding:'24px' }}>

      {/* Header */}
      <div style={{ marginBottom:'28px' }}>
        <h1 style={{ fontSize:'22px', fontWeight:900, color:'#fff', marginBottom:'4px' }}>
          🛒 Marketplace EduGest DZ
        </h1>
        <p style={{ fontSize:'12px', color:'#64748b' }}>
          Trouvez le meilleur centre ou enseignant près de chez vous
        </p>
      </div>

      {/* Barre de recherche principale */}
      <div style={{
        background:'#111318', border:'1px solid #1e293b', borderRadius:'12px',
        padding:'16px', marginBottom:'20px',
      }}>
        <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr 1fr auto', gap:'10px' }}>
          <select value={filtres.wilaya}
            onChange={e => setFiltres(f => ({ ...f, wilaya: e.target.value }))}
            style={{ background:'#1e293b', border:'none', borderRadius:'8px',
              color:'#e2e8f0', padding:'10px 12px', fontSize:'12px' }}>
            <option value="">📍 Toutes les wilayas</option>
            {WILAYAS_DZ.map(w => <option key={w} value={w}>{w}</option>)}
          </select>

          <select value={filtres.matiere}
            onChange={e => setFiltres(f => ({ ...f, matiere: e.target.value }))}
            style={{ background:'#1e293b', border:'none', borderRadius:'8px',
              color:'#e2e8f0', padding:'10px 12px', fontSize:'12px' }}>
            <option value="">📚 Toutes les matières</option>
            {MATIERES.map(m => <option key={m} value={m}>{m}</option>)}
          </select>

          <select value={filtres.niveau}
            onChange={e => setFiltres(f => ({ ...f, niveau: e.target.value }))}
            style={{ background:'#1e293b', border:'none', borderRadius:'8px',
              color:'#e2e8f0', padding:'10px 12px', fontSize:'12px' }}>
            <option value="">🎓 Tous les niveaux</option>
            {NIVEAUX.map(n => <option key={n} value={n}>{n}</option>)}
          </select>

          <button onClick={rechercher} disabled={loading}
            style={{
              background: loading ? '#1e293b' : 'linear-gradient(135deg,#3b82f6,#1d4ed8)',
              color:'#fff', border:'none', borderRadius:'8px',
              padding:'10px 20px', fontWeight:700, fontSize:'12px', cursor:'pointer',
            }}>
            {loading ? '...' : <><Search size={14} style={{ marginRight:'6px' }} />Rechercher</>}
          </button>
        </div>

        {/* Filtres avancés */}
        <div style={{ marginTop:'10px', display:'flex', alignItems:'center', gap:'12px' }}>
          <label style={{ display:'flex', alignItems:'center', gap:'6px', fontSize:'11px', color:'#94a3b8', cursor:'pointer' }}>
            <input type="checkbox" checked={filtres.essai_gratuit}
              onChange={e => setFiltres(f => ({ ...f, essai_gratuit: e.target.checked }))} />
            Essai gratuit uniquement
          </label>
          <input type="number" placeholder="Tarif max (DA/h)"
            value={filtres.tarif_max}
            onChange={e => setFiltres(f => ({ ...f, tarif_max: e.target.value }))}
            style={{ background:'#1e293b', border:'none', borderRadius:'6px',
              color:'#e2e8f0', padding:'6px 10px', fontSize:'11px', width:'140px' }} />
        </div>
      </div>

      {/* Résultats de recherche */}
      {centres.length > 0 && (
        <div style={{ marginBottom:'28px' }}>
          <div style={{ fontSize:'11px', color:'#64748b', marginBottom:'12px' }}>
            {centres.length} centre{centres.length > 1 ? 's' : ''} trouvé{centres.length > 1 ? 's' : ''}
          </div>
          <div style={{ display:'grid', gridTemplateColumns:'repeat(3,1fr)', gap:'10px' }}>
            {centres.map(c => (
              <CentreCard key={c.id || c.tenant_id} centre={c}
                onClick={() => fetchProfil(c.tenant_id)} />
            ))}
          </div>
        </div>
      )}

      {/* Featured — si pas de recherche */}
      {centres.length === 0 && featured.length > 0 && (
        <div>
          <div style={{ fontSize:'11px', color:'#60a5fa', fontWeight:700,
            textTransform:'uppercase', letterSpacing:'1.5px', marginBottom:'12px' }}>
            ⭐ Centres vérifiés — Mis en avant
          </div>
          <div style={{ display:'grid', gridTemplateColumns:'repeat(3,1fr)', gap:'10px' }}>
            {featured.map(c => (
              <CentreCard key={c.id || c.tenant_id} centre={c}
                onClick={() => fetchProfil(c.tenant_id)} />
            ))}
          </div>
        </div>
      )}

      {/* Modal profil centre */}
      {selectedCentre && (
        <div style={{
          position:'fixed', inset:0, background:'rgba(0,0,0,.7)',
          display:'flex', alignItems:'center', justifyContent:'center', zIndex:1000,
        }} onClick={() => setSelectedCentre(null)}>
          <div style={{
            background:'#111318', border:'1px solid #1e293b', borderRadius:'16px',
            padding:'24px', maxWidth:'600px', width:'90%', maxHeight:'80vh',
            overflowY:'auto',
          }} onClick={e => e.stopPropagation()}>
            <div style={{ display:'flex', justifyContent:'space-between', alignItems:'flex-start', marginBottom:'16px' }}>
              <div>
                <h2 style={{ fontSize:'18px', fontWeight:900, color:'#fff', marginBottom:'4px' }}>
                  {selectedCentre.nom_etablissement}
                  {selectedCentre.verifie && <CheckCircle size={16} color="#4ade80" style={{ marginLeft:'8px' }} />}
                </h2>
                <div style={{ fontSize:'11px', color:'#64748b' }}>
                  📍 {selectedCentre.wilaya} {selectedCentre.commune && `· ${selectedCentre.commune}`}
                </div>
                <Stars note={selectedCentre.note_moyenne} />
              </div>
              <button onClick={() => setSelectedCentre(null)}
                style={{ background:'none', border:'none', color:'#64748b', cursor:'pointer', fontSize:'20px' }}>
                ×
              </button>
            </div>

            {selectedCentre.description && (
              <p style={{ fontSize:'12px', color:'#94a3b8', marginBottom:'16px', lineHeight:1.7 }}>
                {selectedCentre.description}
              </p>
            )}

            {/* Offres */}
            {selectedCentre.offres?.length > 0 && (
              <div style={{ marginBottom:'16px' }}>
                <div style={{ fontSize:'11px', color:'#60a5fa', fontWeight:700, marginBottom:'8px' }}>
                  OFFRES DISPONIBLES
                </div>
                {selectedCentre.offres.map(o => (
                  <div key={o.id} style={{
                    background:'#1e293b', borderRadius:'8px', padding:'10px 12px',
                    marginBottom:'6px', display:'flex', justifyContent:'space-between',
                  }}>
                    <div>
                      <div style={{ fontSize:'12px', fontWeight:700, color:'#f1f5f9' }}>{o.titre}</div>
                      <div style={{ fontSize:'10px', color:'#64748b' }}>{o.matiere} · {o.type}</div>
                    </div>
                    <div style={{ textAlign:'right' }}>
                      <div style={{ fontSize:'13px', fontWeight:800, color:'#4ade80' }}>
                        {o.tarif_heure} DA/h
                      </div>
                      {o.essai_gratuit && (
                        <span style={{ fontSize:'9px', background:'#14532d', color:'#4ade80',
                          padding:'1px 6px', borderRadius:'20px' }}>Essai gratuit</span>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            )}

            {/* Avis */}
            {selectedCentre.avis?.length > 0 && (
              <div>
                <div style={{ fontSize:'11px', color:'#60a5fa', fontWeight:700, marginBottom:'8px' }}>
                  AVIS ({selectedCentre.nb_avis})
                </div>
                {selectedCentre.avis.slice(0,3).map(a => (
                  <div key={a.id} style={{
                    background:'#0d2515', borderRadius:'8px', padding:'10px 12px', marginBottom:'6px',
                  }}>
                    <div style={{ display:'flex', justifyContent:'space-between', marginBottom:'4px' }}>
                      <span style={{ fontSize:'11px', fontWeight:700, color:'#4ade80' }}>
                        {a.parent?.prenom} {a.parent?.nom}
                      </span>
                      <Stars note={a.note} />
                    </div>
                    {a.commentaire && (
                      <p style={{ fontSize:'11px', color:'#94a3b8', margin:0 }}>{a.commentaire}</p>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
```

---

## ÉTAPE 8 — Ajouter la route dans App.jsx et le lien dans Sidebar.jsx

**Modifier :** `edugestdz/frontend/src/App.jsx`

Ajouter l'import et la route :
```jsx
import MarketplacePage from './pages/MarketplacePage';
// Dans les routes :
<Route path="/marketplace" element={<MarketplacePage />} />
```

**Modifier :** `edugestdz/frontend/src/components/Sidebar.jsx`

Ajouter dans `NAV_ITEMS` :
```jsx
{ path: '/marketplace', icon: '🛒', label: 'Marketplace' },
```

---

## ÉTAPE 9 — Tests Feature Marketplace

**Créer :** `edugestdz/backend/tests/Feature/Controllers/MarketplaceControllerTest.php`

```php
<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\ProfilMarketplace;
use App\Models\OffreCours;
use App\Models\Eleve;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class MarketplaceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin  = User::factory()->create(['role' => 'admin']);
        $this->parent = User::factory()->create(['role' => 'parent']);
    }

    // ── Endpoints publics ──────────────────────────────────────────

    public function test_recherche_publique_sans_auth(): void
    {
        ProfilMarketplace::create([
            'tenant_id'          => Str::uuid(),
            'nom_etablissement'  => 'Centre Avenir Oran',
            'adresse'            => '12 Rue des Frères Benali',
            'wilaya'             => 'Oran',
            'matieres_enseignees'=> ['Mathématiques', 'Physique'],
            'niveaux_couverts'   => ['1AS', '2AS', '3AS'],
            'visible'            => true,
        ]);

        $this->getJson('/api/v1/marketplace/recherche?wilaya=Oran')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['centres', 'total']]);
    }

    public function test_recherche_par_matiere(): void
    {
        $this->getJson('/api/v1/marketplace/recherche?matiere=Mathématiques')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_featured_sans_auth(): void
    {
        $this->getJson('/api/v1/marketplace/featured')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_profil_public_centre_visible(): void
    {
        $tenantId = (string) Str::uuid();
        ProfilMarketplace::create([
            'tenant_id'         => $tenantId,
            'nom_etablissement' => 'Centre Test',
            'adresse'           => '1 Rue Test',
            'wilaya'            => 'Alger',
            'visible'           => true,
        ]);

        $this->getJson("/api/v1/marketplace/centres/{$tenantId}")
            ->assertStatus(200)
            ->assertJsonPath('data.nom_etablissement', 'Centre Test');
    }

    public function test_profil_centre_invisible_retourne_404(): void
    {
        $tenantId = (string) Str::uuid();
        ProfilMarketplace::create([
            'tenant_id'         => $tenantId,
            'nom_etablissement' => 'Centre Caché',
            'adresse'           => 'Adresse',
            'wilaya'            => 'Alger',
            'visible'           => false,
        ]);

        $this->getJson("/api/v1/marketplace/centres/{$tenantId}")
            ->assertStatus(404);
    }

    // ── Profil centre (auth) ────────────────────────────────────────

    public function test_voir_mon_profil_authentifie(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/marketplace/mon-profil')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_mettre_a_jour_profil(): void
    {
        $this->actingAs($this->admin, 'api')
            ->putJson('/api/v1/marketplace/mon-profil', [
                'nom_etablissement'   => 'Centre Mis à Jour',
                'description'         => 'Le meilleur centre d\'Oran',
                'tarif_heure_min'     => 500,
                'tarif_heure_max'     => 1200,
                'matieres_enseignees' => ['Mathématiques', 'Physique'],
                'accepte_essai_gratuit' => true,
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_mettre_a_jour_profil_sans_auth_echoue(): void
    {
        $this->putJson('/api/v1/marketplace/mon-profil', ['nom_etablissement' => 'Test'])
            ->assertStatus(401);
    }

    // ── Offres ─────────────────────────────────────────────────────

    public function test_creer_offre_cours(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/marketplace/offres', [
                'titre'        => 'Cours Maths 3AS',
                'matiere'      => 'Mathématiques',
                'niveaux'      => ['3AS'],
                'type'         => 'individuel',
                'tarif_heure'  => 800,
                'essai_gratuit'=> true,
            ])
            ->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_creer_offre_sans_titre_echoue(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/marketplace/offres', ['matiere' => 'Maths', 'tarif_heure' => 500])
            ->assertStatus(422);
    }

    public function test_lister_offres_centre(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/marketplace/offres')
            ->assertStatus(200);
    }

    // ── Réservations ───────────────────────────────────────────────

    public function test_parent_peut_reserver(): void
    {
        $offre = OffreCours::factory()->create([
            'tenant_id'   => Str::uuid(),
            'tarif_heure' => 800,
            'active'      => true,
        ]);
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->parent, 'api')
            ->postJson('/api/v1/marketplace/reserver', [
                'offre_id'       => $offre->id,
                'eleve_id'       => $eleve->id,
                'date_souhaitee' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'type'           => 'cours_unique',
                'message_parent' => 'Bonjour, nous souhaitons prendre un cours.',
            ])
            ->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_reservation_date_passee_echoue(): void
    {
        $offre = OffreCours::factory()->create(['active' => true]);
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->parent, 'api')
            ->postJson('/api/v1/marketplace/reserver', [
                'offre_id'       => $offre->id,
                'eleve_id'       => $eleve->id,
                'date_souhaitee' => now()->subDay()->format('Y-m-d H:i:s'),
            ])
            ->assertStatus(422);
    }

    public function test_parent_voit_ses_reservations(): void
    {
        $this->actingAs($this->parent, 'api')
            ->getJson('/api/v1/marketplace/mes-reservations')
            ->assertStatus(200);
    }

    // ── Avis ───────────────────────────────────────────────────────

    public function test_soumettre_avis(): void
    {
        $tenantId = (string) Str::uuid();
        ProfilMarketplace::create([
            'tenant_id' => $tenantId, 'nom_etablissement' => 'Centre Test',
            'adresse' => '', 'wilaya' => 'Oran', 'visible' => true,
        ]);

        $this->actingAs($this->parent, 'api')
            ->postJson('/api/v1/marketplace/avis', [
                'tenant_id'  => $tenantId,
                'note'       => 5,
                'titre'      => 'Excellent centre !',
                'commentaire'=> 'Mon fils a beaucoup progressé en maths.',
            ])
            ->assertStatus(201);
    }

    public function test_note_invalide_echoue(): void
    {
        $this->actingAs($this->parent, 'api')
            ->postJson('/api/v1/marketplace/avis', [
                'tenant_id' => Str::uuid(),
                'note'      => 6, // invalide — max 5
            ])
            ->assertStatus(422);
    }

    // ── Favoris ────────────────────────────────────────────────────

    public function test_ajouter_favori(): void
    {
        $tenantId = (string) Str::uuid();
        $this->actingAs($this->parent, 'api')
            ->postJson("/api/v1/marketplace/favoris/{$tenantId}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_retirer_favori_toggle(): void
    {
        $tenantId = (string) Str::uuid();
        // Ajouter
        $this->actingAs($this->parent, 'api')
            ->postJson("/api/v1/marketplace/favoris/{$tenantId}")
            ->assertStatus(200);
        // Retirer (toggle)
        $this->actingAs($this->parent, 'api')
            ->postJson("/api/v1/marketplace/favoris/{$tenantId}")
            ->assertStatus(200);
    }

    // ── Stats admin ────────────────────────────────────────────────

    public function test_stats_marketplace_admin(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/marketplace/stats')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => [
                'profils_actifs', 'profils_verifies', 'total_reservations',
            ]]);
    }
}
```

---

## ÉTAPE 10 — Factory pour OffreCours

**Créer :** `edugestdz/backend/database/factories/OffreCoursFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\OffreCours;
use Illuminate\Database\Eloquent\Factories\Factory;

class OffreCoursFactory extends Factory
{
    protected $model = OffreCours::class;

    public function definition(): array
    {
        return [
            'tenant_id'    => \Illuminate\Support\Str::uuid(),
            'titre'        => $this->faker->sentence(4),
            'matiere'      => $this->faker->randomElement(['Mathématiques','Physique','Français','Anglais']),
            'niveaux'      => ['1AS','2AS'],
            'type'         => $this->faker->randomElement(['individuel','groupe','en_ligne']),
            'tarif_heure'  => $this->faker->randomFloat(2, 400, 1500),
            'duree_seance' => 60,
            'essai_gratuit'=> false,
            'active'       => true,
        ];
    }
}
```

---

## ORDRE D'EXÉCUTION DEEPSEEK

```bash
# 0. Synchroniser
git checkout develop && git pull origin main

# 1. Migration
create: database/migrations/2026_07_02_100000_create_marketplace_tables.php

# 2. Models
create: app/Models/ProfilMarketplace.php
create: app/Models/OffreCours.php
create: app/Models/Reservation.php
create: app/Models/AvisMarketplace.php
create: app/Models/FavoriMarketplace.php

# 3. Service
create: app/Services/MarketplaceService.php

# 4. Controller
create: app/Http/Controllers/Api/V1/MarketplaceController.php

# 5. Routes
modify: routes/api.php → ajouter les routes marketplace (public + auth)

# 6. Frontend
create: frontend/src/pages/MarketplacePage.jsx
modify: frontend/src/App.jsx → ajouter import + route /marketplace
modify: frontend/src/components/Sidebar.jsx → ajouter lien Marketplace

# 7. Factory
create: database/factories/OffreCoursFactory.php

# 8. Tests
create: tests/Feature/Controllers/MarketplaceControllerTest.php

# 9. Lancer
php artisan migrate
php artisan test --parallel
# → Attendu : ≥ 395 tests verts (381 + ~14 nouveaux)

# 10. Commit & PR
git add .
git commit -m "feat: Marketplace — Recherche + Profils + Réservations + Avis + Favoris + Page React + 14 tests"
git push origin develop
# → PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_MARKETPLACE.md — 10 étapes dans l'ordre.

RÈGLES :
1. PostgreSQL uniquement — jamais SQLite.
2. 381 tests existants → 0 régression.
3. Réutiliser SatimGateway existant pour le paiement réservation.
4. Ne pas modifier les contrôleurs existants.

Après php artisan test --parallel (≥395 verts) :
git commit -m "feat: Marketplace complet" && git push origin develop
PR develop → main.
```
