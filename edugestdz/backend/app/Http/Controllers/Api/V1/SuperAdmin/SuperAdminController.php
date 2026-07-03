<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Eleve;
use App\Models\Paiement;
use App\Models\ProfilMarketplace;
use App\Models\ReservationMarketplace;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SuperAdminController extends Controller
{
    public function indexTenants(): JsonResponse
    {
        $admins = User::whereHas('role', fn($q) => $q->where('nom', 'admin'))
            ->select('id', 'nom', 'prenom', 'email', 'tenant_id', 'created_at')
            ->orderByDesc('created_at')
            ->get();

        $tenants = $admins->map(fn($u) => [
            'id'         => $u->tenant_id ?? $u->id,
            'nom'        => $u->nom . ' ' . $u->prenom,
            'email'      => $u->email,
            'nb_eleves'  => $u->tenant?->eleves()->where('statut', 'actif')->count() ?? 0,
            'actif'      => true,
            'created_at' => $u->created_at,
        ]);

        return response()->json(['success' => true, 'data' => $tenants]);
    }

    public function statsGlobales(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'total_tenants'        => User::whereHas('role', fn($q) => $q->where('nom', 'admin'))->count(),
                'tenants_actifs'       => User::whereHas('role', fn($q) => $q->where('nom', 'admin'))->count(),
                'total_eleves'         => Eleve::where('statut', 'actif')->count(),
                'ca_global'            => (float) Paiement::where('statut', 'confirmé')->sum('montant'),
                'profils_marketplace'  => ProfilMarketplace::where('visible', true)->count(),
                'total_reservations'   => ReservationMarketplace::count(),
            ],
        ]);
    }

    public function suspendreTenant(string $id): JsonResponse
    {
        User::where('tenant_id', $id)->update(['actif' => false]);
        Log::warning("Super-admin: tenant {$id} suspendu par " . auth('api')->id());

        return response()->json(['success' => true, 'message' => 'Tenant suspendu']);
    }

    public function verifierMarketplace(string $tenantId): JsonResponse
    {
        ProfilMarketplace::where('tenant_id', $tenantId)
            ->update(['verifie' => true]);

        return response()->json(['success' => true, 'message' => 'Profil marketplace vérifié']);
    }
}
