<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Wilaya, Commune, CalendrierScolaire};
use Illuminate\Http\{Request, JsonResponse};

class ParametreController extends Controller
{
    public function index(): JsonResponse
    {
        $tenant = app('tenant');

        return response()->json([
            'success' => true,
            'data'    => [
                'tenant' => [
                    'nom'             => $tenant->nom,
                    'email'           => $tenant->email,
                    'telephone'       => $tenant->telephone,
                    'logo_url'        => $tenant->logo_url,
                    'devise'          => 'DA',
                    'langue_defaut'   => 'fr',
                    'fuseau_horaire'  => 'Africa/Algiers',
                    'format_date'     => 'd/m/Y',
                ],
                'notifications' => [
                    'rappels_paiement' => true,
                    'absence_eleve'    => true,
                    'alerte_planning'  => true,
                ],
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'                => 'sometimes|string|max:200',
            'email'              => 'sometimes|email',
            'telephone'          => 'sometimes|string|max:20',
            'langue_defaut'      => 'sometimes|in:fr,ar',
            'frais_inscription'  => 'sometimes|numeric|min:0',
            'tva'                => 'sometimes|numeric|min:0|max:100',
        ]);

        return response()->json(['success' => true, 'message' => 'Paramètres mis à jour', 'data' => $validated]);
    }

    public function wilayas(): JsonResponse
    {
        $wilayas = Wilaya::orderBy('nom_fr')->get();
        return response()->json(['success' => true, 'data' => $wilayas]);
    }

    public function communes(string $wilayaId): JsonResponse
    {
        $communes = Commune::where('wilaya_id', $wilayaId)->orderBy('nom_fr')->get();
        return response()->json(['success' => true, 'data' => $communes]);
    }

    public function calendrier(): JsonResponse
    {
        $jours = CalendrierScolaire::where('est_ferie', true)
            ->orWhere('type_jour', 'vacance')
            ->orderBy('date')
            ->get();

        return response()->json(['success' => true, 'data' => $jours]);
    }
}
