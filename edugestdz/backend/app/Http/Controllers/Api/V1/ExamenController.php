<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SessionExamen;
use App\Models\EpreuveExamen;
use App\Models\SalleExamen;
use App\Models\CandidatExamen;
use App\Models\SurveiillantExamen;
use App\Services\ExamenService;
use App\Exports\RapportOnecExport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;

class ExamenController extends Controller
{
    public function __construct(private ExamenService $service) {}

    public function indexSessions(Request $request): JsonResponse
    {
        $sessions = SessionExamen::withCount(['candidats','salles','epreuves'])
            ->when($request->filled('type'),   fn($q) => $q->where('type', $request->type))
            ->when($request->filled('statut'), fn($q) => $q->where('statut', $request->statut))
            ->orderByDesc('date_debut')
            ->paginate(20);
        return response()->json(['success' => true, 'data' => $sessions]);
    }

    public function storeSession(Request $request): JsonResponse
    {
        $v = $request->validate([
            'type'             => 'required|in:BEM,BAC,autre',
            'filiere'          => 'nullable|string',
            'annee_scolaire'   => 'required|string|max:10',
            'session'          => 'in:principale,rattrapage',
            'date_debut'       => 'required|date',
            'date_fin'         => 'required|date|after_or_equal:date_debut',
            'wilaya'           => 'nullable|string|max:60',
            'commune'          => 'nullable|string|max:60',
            'nom_centre'       => 'nullable|string|max:200',
            'adresse_centre'   => 'nullable|string|max:300',
            'max_candidats_par_salle'        => 'integer|min:1|max:30',
            'max_candidats_libres_par_salle' => 'integer|min:1|max:20',
            'nb_surveillants_par_salle'       => 'integer|min:1|max:10',
        ]);
        $session = SessionExamen::create([...$v, 'tenant_id' => config('tenant.current_id')]);
        return response()->json(['success' => true, 'data' => $session, 'message' => 'Session créée'], 201);
    }

    public function showSession(string $id): JsonResponse
    {
        $session = SessionExamen::with(['epreuves','salles','candidats','surveillants'])
            ->findOrFail($id);
        $dashboard = $this->service->getDashboard($id);
        return response()->json(['success' => true, 'data' => $session, 'dashboard' => $dashboard]);
    }

    public function updateSession(Request $request, string $id): JsonResponse
    {
        $session = SessionExamen::findOrFail($id);
        $session->update($request->only([
            'statut','nom_centre','adresse_centre','wilaya','commune',
            'max_candidats_par_salle','nb_surveillants_par_salle','notes',
        ]));
        return response()->json(['success' => true, 'data' => $session->fresh()]);
    }

    public function storeEpreuve(Request $request, string $sessionId): JsonResponse
    {
        $v = $request->validate([
            'matiere'                => 'required|string|max:100',
            'code_matiere'           => 'nullable|string|max:10',
            'coefficient'            => 'required|numeric|min:0.5|max:9',
            'date_epreuve'           => 'required|date',
            'moment'                 => 'required|in:matin,apres_midi',
            'heure_debut'            => 'required|date_format:H:i',
            'heure_fin'              => 'required|date_format:H:i|after:heure_debut',
            'duree_minutes'          => 'integer|min:30|max:360',
            'type_epreuve'           => 'in:ecrit,oral,pratique',
            'calculatrice_autorisee' => 'boolean',
            'documents_autorises'    => 'boolean',
        ]);
        $epreuve = EpreuveExamen::create([...$v, 'session_id' => $sessionId]);
        return response()->json(['success' => true, 'data' => $epreuve], 201);
    }

    public function deleteEpreuve(string $id): JsonResponse
    {
        EpreuveExamen::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Épreuve supprimée']);
    }

    public function storeSalle(Request $request, string $sessionId): JsonResponse
    {
        $v = $request->validate([
            'nom'             => 'required|string|max:50',
            'numero'          => 'nullable|string|max:20',
            'batiment'        => 'nullable|string|max:50',
            'etage'           => 'nullable|string|max:20',
            'capacite_totale' => 'required|integer|min:1|max:50',
            'nb_rangees'      => 'nullable|integer|min:1|max:10',
            'nb_colonnes'     => 'nullable|integer|min:1|max:10',
            'climatisee'      => 'boolean',
            'accessible_pmr'  => 'boolean',
        ]);
        $salle = SalleExamen::create([...$v, 'session_id' => $sessionId, 'tenant_id' => config('tenant.current_id')]);
        return response()->json(['success' => true, 'data' => $salle], 201);
    }

    public function indexCandidats(Request $request, string $sessionId): JsonResponse
    {
        $candidats = CandidatExamen::with('salle:id,nom')
            ->where('session_id', $sessionId)
            ->when($request->filled('salle_id'), fn($q) => $q->where('salle_id', $request->salle_id))
            ->orderBy('salle_id')->orderBy('numero_place')
            ->paginate(50);
        return response()->json(['success' => true, 'data' => $candidats]);
    }

    public function storeCandidat(Request $request, string $sessionId): JsonResponse
    {
        $v = $request->validate([
            'nom'                => 'required|string|max:100',
            'prenom'             => 'required|string|max:100',
            'date_naissance'     => 'nullable|date',
            'lieu_naissance'     => 'nullable|string|max:100',
            'numero_inscription' => 'nullable|string|max:20',
            'type_candidat'      => 'in:scolarise,libre',
            'filiere'            => 'nullable|string|max:50',
            'besoins_speciaux'   => 'boolean',
        ]);
        $candidat = CandidatExamen::create([...$v, 'session_id' => $sessionId, 'tenant_id' => config('tenant.current_id')]);
        return response()->json(['success' => true, 'data' => $candidat], 201);
    }

    public function importerElevesSysteme(Request $request, string $sessionId): JsonResponse
    {
        $request->validate(['eleve_ids' => 'required|array', 'eleve_ids.*' => 'uuid']);
        $result = $this->service->importerElevesSysteme($sessionId, $request->eleve_ids);
        return response()->json(['success' => true, 'data' => $result, 'message' => "{$result['importes']} élève(s) importé(s)"]);
    }

    public function importerCSV(Request $request, string $sessionId): JsonResponse
    {
        $request->validate(['fichier' => 'required|file|mimes:csv,txt|max:5120']);
        $result = $this->service->importerCandidats($sessionId, $request->file('fichier')->getPathname());
        return response()->json(['success' => true, 'data' => $result, 'message' => "{$result['importes']} candidat(s) importé(s)"]);
    }

    public function marquerPresence(Request $request, string $candidatId): JsonResponse
    {
        $candidat = CandidatExamen::findOrFail($candidatId);
        $candidat->update(['present' => $request->boolean('present', true), 'present_marque_le' => now()]);
        return response()->json(['success' => true, 'data' => $candidat]);
    }

    public function storeSurveillant(Request $request, string $sessionId): JsonResponse
    {
        $v = $request->validate([
            'user_id'          => 'required|uuid|exists:users,id',
            'nom'              => 'required|string|max:100',
            'prenom'           => 'required|string|max:100',
            'specialite'       => 'nullable|string|max:100',
            'commune_origine'  => 'nullable|string|max:60',
            'role'             => 'in:chef_centre,surveillant,secretaire,observateur',
            'disponible'       => 'boolean',
            'motif_exemption'  => 'nullable|string|max:300',
        ]);
        $surveillant = SurveiillantExamen::create([...$v, 'session_id' => $sessionId, 'tenant_id' => config('tenant.current_id')]);
        return response()->json(['success' => true, 'data' => $surveillant], 201);
    }

    public function importerEnseignantsSurveillants(string $sessionId): JsonResponse
    {
        $enseignants = \App\Models\User::where('tenant_id', config('tenant.current_id'))
            ->whereIn('role', ['enseignant'])->get();
        $importes = 0;
        foreach ($enseignants as $ens) {
            $existe = SurveiillantExamen::where('session_id', $sessionId)->where('user_id', $ens->id)->exists();
            if ($existe) continue;
            SurveiillantExamen::create([
                'session_id'      => $sessionId,
                'tenant_id'       => config('tenant.current_id'),
                'user_id'         => $ens->id,
                'nom'             => $ens->nom,
                'prenom'          => $ens->prenom,
                'specialite'      => $ens->specialite ?? null,
                'role'            => 'surveillant',
                'disponible'      => true,
            ]);
            $importes++;
        }
        return response()->json(['success' => true, 'message' => "{$importes} enseignant(s) ajouté(s) comme surveillants"]);
    }

    public function affecterCandidats(string $sessionId): JsonResponse
    {
        try {
            $result = $this->service->affecterCandidatsAuxSalles($sessionId);
            return response()->json(['success' => true, 'data' => $result, 'message' => $result['message']]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function affecterSurveilants(string $sessionId): JsonResponse
    {
        try {
            $result = $this->service->affecterSurveillantsAuxSalles($sessionId);
            return response()->json(['success' => true, 'data' => $result, 'message' => $result['message']]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function pdfConvocationCandidat(string $id): \Illuminate\Http\Response
    {
        $pdf = $this->service->genererConvocationCandidat($id);
        return $pdf->download("convocation-candidat-{$id}.pdf");
    }

    public function pdfToutesConvocations(string $sessionId): \Illuminate\Http\Response
    {
        $pdf = $this->service->genererToutesConvocations($sessionId);
        return $pdf->download("convocations-{$sessionId}.pdf");
    }

    public function pdfConvocationSurveillant(string $id): \Illuminate\Http\Response
    {
        $pdf = $this->service->genererConvocationSurveillant($id);
        return $pdf->download("convocation-surveillant-{$id}.pdf");
    }

    public function pdfFeuillePresence(string $salleId): \Illuminate\Http\Response
    {
        $pdf = $this->service->genererFeuillePresence($salleId);
        return $pdf->download("feuille-presence-{$salleId}.pdf");
    }

    public function pdfPlanSalle(string $salleId): \Illuminate\Http\Response
    {
        $pdf = $this->service->genererPlanSalle($salleId);
        return $pdf->download("plan-salle-{$salleId}.pdf");
    }

    /**
     * Export Excel du rapport ONDEC (candidats + statistiques).
     */
    public function exportOnec(string $sessionId): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $session = SessionExamen::findOrFail($sessionId);
        $nomFichier = "rapport-ondec-{$session->type}-{$session->annee_scolaire}.xlsx";

        return Excel::download(new RapportOnecExport($sessionId), $nomFichier);
    }
}
