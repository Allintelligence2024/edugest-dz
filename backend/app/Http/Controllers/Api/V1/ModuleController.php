<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TenantModule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ModuleController extends Controller
{
    public function index(): JsonResponse
    {
        $tenantId    = config('tenant.current_id');
        $modules     = TenantModule::getEtatComplet($tenantId);
        $parCategorie = collect($modules)->groupBy('categorie')->toArray();

        return response()->json([
            'success' => true,
            'data'    => [
                'modules'       => $modules,
                'par_categorie' => $parCategorie,
                'nb_actifs'     => collect($modules)->where('actif', true)->count(),
                'nb_total'      => count($modules),
            ],
            'message' => 'État des modules',
        ]);
    }

    public function activer(string $moduleKey): JsonResponse
    {
        if (!isset(TenantModule::MODULES[$moduleKey])) {
            return response()->json(['success' => false, 'message' => "Module inconnu : {$moduleKey}"], 422);
        }

        TenantModule::activer(
            config('tenant.current_id'),
            $moduleKey,
            auth('api')->id()
        );

        return response()->json([
            'success' => true,
            'data'    => array_merge(TenantModule::MODULES[$moduleKey], ['actif' => true]),
            'message' => "Module « {$moduleKey} » activé avec succès ✅",
        ]);
    }

    public function desactiver(Request $request, string $moduleKey): JsonResponse
    {
        if (!isset(TenantModule::MODULES[$moduleKey])) {
            return response()->json(['success' => false, 'message' => "Module inconnu : {$moduleKey}"], 422);
        }

        try {
            TenantModule::desactiver(
                config('tenant.current_id'),
                $moduleKey,
                auth('api')->id(),
                $request->input('raison')
            );

            return response()->json([
                'success' => true,
                'data'    => array_merge(TenantModule::MODULES[$moduleKey], ['actif' => false]),
                'message' => "Module « {$moduleKey} » désactivé. Vous pouvez le réactiver à tout moment.",
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'modules'   => 'required|array',
            'modules.*' => 'boolean',
        ]);

        $tenantId  = config('tenant.current_id');
        $userId    = auth('api')->id();
        $resultats = [];
        $erreurs   = [];

        foreach ($validated['modules'] as $key => $actif) {
            if (!isset(TenantModule::MODULES[$key])) {
                $erreurs[] = "Module inconnu : {$key}";
                continue;
            }

            try {
                if ($actif) {
                    TenantModule::activer($tenantId, $key, $userId);
                } else {
                    TenantModule::desactiver($tenantId, $key, $userId);
                }
                $resultats[$key] = $actif;
            } catch (\RuntimeException $e) {
                $erreurs[] = $e->getMessage();
            }
        }

        return response()->json([
            'success' => empty($erreurs),
            'data'    => ['mis_a_jour' => $resultats, 'erreurs' => $erreurs],
            'message' => count($resultats) . " module(s) mis à jour" . (count($erreurs) > 0 ? " — " . count($erreurs) . " erreur(s)" : " ✅"),
        ]);
    }

    public function actifs(): JsonResponse
    {
        $tenantId  = config('tenant.current_id');

        $configures = TenantModule::where('tenant_id', $tenantId)
            ->where('actif', false)
            ->pluck('module_key')
            ->toArray();

        $tousLesKeys = array_keys(TenantModule::MODULES);
        $actifsKeys  = array_filter($tousLesKeys, fn($k) => !in_array($k, $configures));

        return response()->json([
            'success' => true,
            'data'    => array_values($actifsKeys),
        ]);
    }
}
