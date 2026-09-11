<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Bulletin;
use App\Services\BulletinService;
use App\Services\PerimetreAccesService;
use App\Jobs\GenerateBulletinPdfJob;
use Illuminate\Http\{Request, JsonResponse};

class BulletinController extends Controller
{
    public function __construct(
        private BulletinService $service,
        private PerimetreAccesService $perimetre,
    ) {}

    public function index(Request $request): JsonResponse
    {
        // Un parent ne voit que les bulletins de ses enfants, un enseignant
        // que ceux de ses groupes. Sans cela, tout utilisateur authentifié
        // listait les bulletins de TOUS les élèves de l'établissement.
        $query = $this->perimetre->restreindreParEleve(
            Bulletin::query(),
            auth('api')->user(),
            'eleve_id'
        );

        $bulletins = $query->with(['eleve', 'groupe.matiere'])
            ->when($request->groupe_id,  fn($q) => $q->where('groupe_id', $request->groupe_id))
            ->when($request->trimestre,  fn($q) => $q->where('trimestre', $request->trimestre))
            ->when($request->annee_scolaire, fn($q) => $q->where('annee_scolaire', $request->annee_scolaire))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data'    => $bulletins->items(),
            'meta'    => ['total' => $bulletins->total()],
        ]);
    }

    public function generer(Request $request): JsonResponse
    {
        $request->validate([
            'groupe_id'     => 'required|uuid|exists:groupes,id',
            'trimestre'     => 'required|in:T1,T2,T3',
            'annee_scolaire'=> 'required|string|max:10',
        ]);

        $resultats = $this->service->genererBulletins(
            $request->groupe_id,
            $request->trimestre,
            $request->annee_scolaire
        );

        return response()->json([
            'success' => true,
            'message' => count($resultats) . ' bulletin(s) généré(s)',
            'data'    => $resultats,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $bulletin = Bulletin::with(['eleve.parents', 'groupe.matiere'])->findOrFail($id);

        if (!$this->perimetre->peutVoirEleve(auth('api')->user(), $bulletin->eleve_id)) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'FORBIDDEN', 'message' => "Ce bulletin n'est pas dans votre périmètre"],
            ], 403);
        }

        return response()->json(['success' => true, 'data' => $bulletin]);
    }

    public function pdf(string $id)
    {
        $bulletin = Bulletin::findOrFail($id);

        if (!$this->perimetre->peutVoirEleve(auth('api')->user(), $bulletin->eleve_id)) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'FORBIDDEN', 'message' => "Ce bulletin n'est pas dans votre périmètre"],
            ], 403);
        }

        if ($bulletin->fichier_url && \Storage::disk('public')->exists($bulletin->fichier_url)) {
            return response()->download(storage_path('app/public/' . $bulletin->fichier_url));
        }

        if (in_array($bulletin->statut_pdf ?? '', ['en_attente', 'en_cours', null])) {
            GenerateBulletinPdfJob::dispatch($bulletin);
            $bulletin->update(['statut_pdf' => 'en_attente']);

            return response()->json([
                'success' => true,
                'message' => 'Génération du PDF en cours. Réessayez dans quelques instants.',
                'statut_pdf' => 'en_attente',
            ], 202);
        }

        if ($bulletin->statut_pdf === 'erreur') {
            GenerateBulletinPdfJob::dispatch($bulletin);
            $bulletin->update(['statut_pdf' => 'en_attente']);

            return response()->json([
                'success' => true,
                'message' => 'Nouvelle tentative de génération lancée.',
                'statut_pdf' => 'en_attente',
            ], 202);
        }

        return response()->download(storage_path('app/public/' . $bulletin->fichier_url));
    }

    public function envoyer(string $id): JsonResponse
    {
        $bulletin = Bulletin::with('eleve.parents')->findOrFail($id);
        $parent = $bulletin->eleve->parents->firstWhere('pivot.est_principal', true);
        if ($parent?->email) {
            \Mail::to($parent->email)->queue(new \App\Mail\BulletinMail($bulletin));
        }
        return response()->json(['success' => true, 'message' => 'Bulletin envoyé']);
    }
}
