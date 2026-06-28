<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\{Tenant, User, SuperAdminAction};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function __construct()
    {
        $this->middleware('super_admin');
    }

    public function index(Request $request): JsonResponse
    {
        $tenants = Tenant::withCount('users', 'eleves')
            ->when($request->statut, fn($q) => $q->where('statut', $request->statut))
            ->when($request->plan, fn($q) => $q->where('plan_abonnement', $request->plan))
            ->when($request->search, fn($q) => $q->where('nom_etablissement', 'ILIKE', "%{$request->search}%"))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        $stats = [
            'total'  => Tenant::count(),
            'actifs' => Tenant::where('statut', 'actif')->count(),
            'expires' => Tenant::where('date_expiration', '<', now())->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => $tenants->items(),
            'meta'    => ['total' => $tenants->total(), 'stats' => $stats],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom_etablissement' => 'required|string|max:200',
            'slug'              => 'required|string|max:100|unique:tenants,slug',
            'type_etablissement'=> 'required|in:centre,ecole,institut',
            'wilaya_id'         => 'required|exists:wilayas,id',
            'plan_abonnement'   => 'required|in:gratuit,premium',
            'date_expiration'   => 'nullable|date',
            'email'             => 'required|email',
            'telephone'         => 'required|string|max:20',
            'admin_nom'         => 'required|string|max:100',
            'admin_prenom'      => 'required|string|max:100',
            'admin_email'       => 'required|email|unique:users,email',
            'admin_password'    => 'required|string|min:8',
        ]);

        $tenant = DB::transaction(function () use ($validated) {
            $tenant = Tenant::create([
                'nom_etablissement' => $validated['nom_etablissement'],
                'slug'              => $validated['slug'],
                'type_etablissement'=> $validated['type_etablissement'],
                'wilaya_id'         => $validated['wilaya_id'],
                'plan_abonnement'   => $validated['plan_abonnement'],
                'date_expiration'   => $validated['date_expiration'] ?? now()->addYear(),
                'statut'            => 'actif',
                'email'             => $validated['email'],
                'telephone'         => $validated['telephone'],
                'quotas'            => $this->getQuotasForPlan($validated['plan_abonnement']),
            ]);

            $admin = User::create([
                'tenant_id' => $tenant->id,
                'nom'       => $validated['admin_nom'],
                'prenom'    => $validated['admin_prenom'],
                'email'     => $validated['admin_email'],
                'password'  => Hash::make($validated['admin_password']),
                'role'      => 'admin',
                'statut'    => 'actif',
            ]);

            SuperAdminAction::create([
                'super_admin_id' => auth()->id(),
                'tenant_id'      => $tenant->id,
                'action'         => 'tenant.created',
                'details'        => ['plan' => $validated['plan_abonnement']],
            ]);

            return $tenant;
        });

        return response()->json([
            'success' => true,
            'data'    => $tenant->loadCount('users', 'eleves'),
            'message' => 'Établissement créé avec succès',
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::withCount('users', 'eleves', 'factures')
            ->findOrFail($id);

        $usage = [
            'users'  => $tenant->users()->count(),
            'eleves' => $tenant->eleves()->count(),
            'revenue' => $tenant->factures()->where('statut', 'payee')->sum('total_ttc'),
        ];

        return response()->json([
            'success' => true,
            'data'    => ['tenant' => $tenant, 'usage' => $usage],
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'nom_etablissement' => 'sometimes|string|max:200',
            'plan_abonnement'   => 'sometimes|in:gratuit,premium',
            'date_expiration'   => 'sometimes|date',
            'statut'            => 'sometimes|in:actif,suspendu,expire',
        ]);

        if (isset($validated['plan_abonnement'])) {
            $validated['quotas'] = $this->getQuotasForPlan($validated['plan_abonnement']);
        }

        $tenant->update($validated);

        SuperAdminAction::create([
            'super_admin_id' => auth()->id(),
            'tenant_id'      => $tenant->id,
            'action'         => 'tenant.updated',
            'details'        => $validated,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $tenant->fresh(),
            'message' => 'Établissement mis à jour',
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'total_tenants'   => Tenant::count(),
                'tenants_actifs'  => Tenant::where('statut', 'actif')->count(),
                'tenants_expires' => Tenant::where('date_expiration', '<', now())->count(),
                'tenants_suspendus' => Tenant::where('statut', 'suspendu')->count(),
                'revenus_estimes' => Tenant::where('plan_abonnement', 'premium')->count() * 5000,
            ],
        ]);
    }

    public function impersonate(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $admin = $tenant->users()->where('role', 'admin')->first();

        if (!$admin) {
            return response()->json(['success' => false, 'error' => ['code' => 'NO_ADMIN', 'message' => 'Aucun admin pour ce tenant']], 404);
        }

        $token = auth('api')->login($admin);

        SuperAdminAction::create([
            'super_admin_id' => auth()->id(),
            'tenant_id'      => $tenant->id,
            'action'         => 'tenant.impersonate',
            'details'        => ['impersonated_user_id' => $admin->id],
        ]);

        return response()->json([
            'success'      => true,
            'data'         => [
                'access_token' => $token,
                'tenant'       => $tenant,
                'user'         => $admin,
                'impersonating' => true,
            ],
            'message' => 'Connexion en tant que ' . $tenant->nom_etablissement,
        ]);
    }

    private function getQuotasForPlan(string $plan): array
    {
        return match ($plan) {
            'gratuit' => ['eleves_max' => 50, 'users_max' => 3, 'stockage_mb' => 100, 'campagnes_mensuelles' => 0],
            'premium' => ['eleves_max' => -1, 'users_max' => -1, 'stockage_mb' => 1024, 'campagnes_mensuelles' => 100],
            default   => ['eleves_max' => 50, 'users_max' => 3, 'stockage_mb' => 100, 'campagnes_mensuelles' => 0],
        };
    }
}
