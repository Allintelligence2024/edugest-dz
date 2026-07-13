<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Livre;
use App\Models\Emprunt;
use App\Services\ScanLivreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BibliothequeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Livre::query()
            ->where('statut', 'actif');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'ilike', "%{$search}%")
                  ->orWhere('auteur', 'ilike', "%{$search}%")
                  ->orWhere('isbn', 'like', "%{$search}%");
            });
        }

        if ($categorie = $request->input('categorie')) {
            $query->where('categorie', $categorie);
        }

        $livres = $query->orderBy('titre')->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $livres,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'titre'          => 'required|string|max:255',
            'auteur'         => 'nullable|string|max:255',
            'isbn'           => 'nullable|string|max:20',
            'editeur'        => 'nullable|string|max:255',
            'annee_edition'  => 'nullable|integer|min:1900|max:' . date('Y'),
            'categorie'      => 'nullable|string|max:100',
            'description'    => 'nullable|string',
            'nb_exemplaires' => 'nullable|integer|min:1',
            'emplacement'    => 'nullable|string|max:50',
        ]);

        $validated['nb_exemplaires'] = $validated['nb_exemplaires'] ?? 1;
        $validated['nb_disponibles'] = $validated['nb_exemplaires'];

        $livre = Livre::create($validated);

        return response()->json([
            'success' => true,
            'data'    => $livre,
        ], 201);
    }

    public function show(Livre $livre): JsonResponse
    {
        $livre->load(['emprunts' => fn($q) => $q->where('statut', 'en_cours')]);

        return response()->json([
            'success' => true,
            'data'    => $livre,
        ]);
    }

    public function emprunter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'livre_id'        => 'required|exists:livres_bibliotheque,id',
            'emprunteur_id'   => 'required|string',
            'type_emprunteur' => 'nullable|in:eleve,enseignant',
            'nom_emprunteur'  => 'required|string|max:255',
            'duree_jours'     => 'nullable|integer|min:1|max:30',
        ]);

        $livre = Livre::findOrFail($validated['livre_id']);

        if (!$livre->estDisponible()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce livre n\'est pas disponible pour emprunt.',
            ], 422);
        }

        $emprunt = DB::transaction(function () use ($validated, $livre) {
            $emprunt = Emprunt::create([
                'livre_id'           => $livre->id,
                'emprunteur_id'      => $validated['emprunteur_id'],
                'type_emprunteur'    => $validated['type_emprunteur'] ?? 'eleve',
                'nom_emprunteur'     => $validated['nom_emprunteur'],
                'date_emprunt'       => now()->toDateString(),
                'date_retour_prevue' => now()->addDays($validated['duree_jours'] ?? 14)->toDateString(),
                'statut'             => 'en_cours',
            ]);

            $livre->decrement('nb_disponibles');

            return $emprunt;
        });

        return response()->json([
            'success' => true,
            'data'    => $emprunt->load('livre'),
        ], 201);
    }

    public function retourner(Emprunt $emprunt): JsonResponse
    {
        if ($emprunt->statut !== 'en_cours') {
            return response()->json([
                'success' => false,
                'message' => 'Cet emprunt n\'est pas actif.',
            ], 422);
        }

        DB::transaction(function () use ($emprunt) {
            $emprunt->update([
                'date_retour_effective' => now()->toDateString(),
                'statut'                => 'retourne',
            ]);

            $emprunt->livre->increment('nb_disponibles');
        });

        return response()->json([
            'success' => true,
            'data'    => $emprunt->fresh('livre'),
        ]);
    }

    public function mesEmprunts(Request $request): JsonResponse
    {
        $emprunts = Emprunt::where('emprunteur_id', $request->user()->id)
            ->with('livre')
            ->orderByDesc('date_emprunt')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $emprunts,
        ]);
    }

    public function scanner(Request $request, ScanLivreService $scanService): JsonResponse
    {
        if (!$scanService->estConfigured()) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'VISION_API_NOT_CONFIGURED',
                    'message' => 'Le service de scan n\'est pas configuré. Contactez l\'administrateur.',
                ],
            ], 503);
        }

        $validated = $request->validate([
            'image'     => 'required_without:image_url|string|max:10485760',
            'image_url' => 'required_without:image|url',
        ]);

        $imageData = $validated['image'] ?? $validated['image_url'];

        $resultat = $scanService->analyserImage($imageData);

        if (!$resultat['success']) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'SCAN_FAILED',
                    'message' => $resultat['error'],
                ],
            ], 422);
        }

        $tenantId = config('tenant.current_id');

        $livreTrouve = null;

        if (!empty($resultat['isbn'])) {
            $livreTrouve = Livre::where('tenant_id', $tenantId)
                ->where('isbn', $resultat['isbn'])
                ->first();
        }

        if (!$livreTrouve && !empty($resultat['titre'])) {
            $livreTrouve = Livre::where('tenant_id', $tenantId)
                ->whereRaw('LOWER(titre) = ?', [strtolower($resultat['titre'])])
                ->first();
        }

        if ($livreTrouve) {
            return response()->json([
                'success' => true,
                'source'  => 'catalogue',
                'data'    => [
                    'livre'      => $livreTrouve,
                    'disponible' => $livreTrouve->estDisponible(),
                    'nb_dispo'   => $livreTrouve->nb_disponibles,
                ],
                'ocr' => [
                    'titre'     => $resultat['titre'],
                    'auteur'    => $resultat['auteur'],
                    'isbn'      => $resultat['isbn'],
                    'confiance' => $resultat['confiance'],
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'source'  => 'scan',
            'data'    => null,
            'ocr'     => [
                'titre'      => $resultat['titre'],
                'auteur'     => $resultat['auteur'],
                'isbn'       => $resultat['isbn'],
                'confiance'  => $resultat['confiance'],
                'texte_brut' => $resultat['texte'],
            ],
            'message' => 'Livre non trouvé dans le catalogue. Vous pouvez l\'ajouter.',
        ]);
    }
}
