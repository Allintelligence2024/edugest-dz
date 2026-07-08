<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LmsCours;
use App\Models\LmsChapitre;
use App\Models\LmsLecon;
use App\Models\LmsQuiz;
use App\Models\LmsQuestion;
use App\Models\LmsInscription;
use App\Models\LmsProgression;
use App\Models\LmsTentativeQuiz;
use App\Models\LmsDevoir;
use App\Services\LmsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class LmsController extends Controller
{
    public function __construct(private LmsService $lms) {}

    public function indexCours(Request $request): JsonResponse
    {
        $cours = LmsCours::with('enseignant:id,nom,prenom')
            ->withCount('inscriptions')
            ->when($request->filled('matiere'),  fn($q) => $q->where('matiere', $request->matiere))
            ->when($request->filled('publie'),   fn($q) => $q->where('publie', (bool) $request->publie))
            ->when($request->filled('niveau'),   fn($q) => $q->whereJsonContains('niveaux_cibles', $request->niveau))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data'    => $cours,
            'stats'   => $this->lms->getDashboard(),
        ]);
    }

    public function storeCours(Request $request): JsonResponse
    {
        $v = $request->validate([
            'titre'           => 'required|string|max:200',
            'description'     => 'nullable|string',
            'matiere'         => 'nullable|string|max:100',
            'niveaux_cibles'  => 'array',
            'langue'          => 'in:ar,fr,en',
            'duree_estimee'   => 'nullable|string|max:20',
            'seuil_completion'=> 'integer|min:1|max:100',
            'certificat_actif'=> 'boolean',
        ]);

        $cours = LmsCours::create([
            ...$v,
            'tenant_id'      => config('tenant.current_id'),
            'enseignant_id'  => auth('api')->id(),
            'publie'         => false,
        ]);

        return response()->json(['success' => true, 'data' => $cours, 'message' => 'Cours créé'], 201);
    }

    public function showCours(string $id): JsonResponse
    {
        $cours = LmsCours::with([
            'enseignant:id,nom,prenom',
            'chapitres.lecons',
            'inscriptions' => fn($q) => $q->limit(5),
        ])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $cours]);
    }

    public function updateCours(Request $request, string $id): JsonResponse
    {
        $cours = LmsCours::findOrFail($id);
        $cours->update($request->only([
            'titre', 'description', 'matiere', 'niveaux_cibles', 'langue',
            'duree_estimee', 'seuil_completion', 'certificat_actif', 'publie',
        ]));
        return response()->json(['success' => true, 'data' => $cours->fresh()]);
    }

    public function publierCours(string $id): JsonResponse
    {
        $cours = LmsCours::findOrFail($id);

        $nbLecons = $cours->chapitres()->withCount('lecons')->get()->sum('lecons_count');
        if ($nbLecons === 0) {
            return response()->json(['success' => false, 'message' => 'Ajouter au moins 1 chapitre et 1 leçon avant de publier.'], 422);
        }

        $cours->update([
            'publie'       => !$cours->publie,
            'nb_chapitres' => $cours->chapitres()->count(),
            'nb_lecons'    => $nbLecons,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $cours->fresh(),
            'message' => $cours->publie ? 'Cours publié ✅' : 'Cours dépublié',
        ]);
    }

    public function storeChapitre(Request $request, string $coursId): JsonResponse
    {
        $v = $request->validate([
            'titre'      => 'required|string|max:200',
            'description'=> 'nullable|string',
            'ordre'      => 'integer|min:1',
        ]);

        $ordre    = $v['ordre'] ?? LmsChapitre::where('cours_id', $coursId)->max('ordre') + 1;
        $chapitre = LmsChapitre::create([...$v, 'cours_id' => $coursId, 'ordre' => $ordre]);
        LmsCours::find($coursId)?->increment('nb_chapitres');

        return response()->json(['success' => true, 'data' => $chapitre], 201);
    }

    public function updateChapitre(Request $request, string $id): JsonResponse
    {
        $chapitre = LmsChapitre::findOrFail($id);
        $chapitre->update($request->only(['titre', 'description', 'ordre', 'publie']));
        return response()->json(['success' => true, 'data' => $chapitre->fresh()]);
    }

    public function deleteChapitre(string $id): JsonResponse
    {
        $chapitre = LmsChapitre::findOrFail($id);
        LmsCours::find($chapitre->cours_id)?->decrement('nb_chapitres');
        $chapitre->delete();
        return response()->json(['success' => true, 'message' => 'Chapitre supprimé']);
    }

    public function storeLecon(Request $request, string $chapitreId): JsonResponse
    {
        $v = $request->validate([
            'titre'         => 'required|string|max:200',
            'type'          => 'required|in:texte,video,pdf,audio,lien,quiz,devoir',
            'contenu'       => 'nullable|string',
            'ressource_url' => 'nullable|string|max:500',
            'ressource_nom' => 'nullable|string|max:200',
            'duree_minutes' => 'nullable|integer|min:1',
            'ordre'         => 'integer|min:1',
            'gratuite'      => 'boolean',
        ]);

        $ordre = $v['ordre'] ?? LmsLecon::where('chapitre_id', $chapitreId)->max('ordre') + 1;
        $lecon = LmsLecon::create([...$v, 'chapitre_id' => $chapitreId, 'ordre' => $ordre]);

        return response()->json(['success' => true, 'data' => $lecon], 201);
    }

    public function updateLecon(Request $request, string $id): JsonResponse
    {
        $lecon = LmsLecon::findOrFail($id);
        $lecon->update($request->only([
            'titre', 'contenu', 'type', 'ressource_url', 'ressource_nom',
            'duree_minutes', 'ordre', 'gratuite', 'publiee',
        ]));
        return response()->json(['success' => true, 'data' => $lecon->fresh()]);
    }

    public function uploadFichierLecon(Request $request, string $id): JsonResponse
    {
        $request->validate(['fichier' => 'required|file|max:51200']);
        $lecon   = LmsLecon::findOrFail($id);
        $path    = $request->file('fichier')->store("lms/lecons/{$lecon->id}", 'public');
        $lecon->update([
            'ressource_url' => Storage::url($path),
            'ressource_nom' => $request->file('fichier')->getClientOriginalName(),
        ]);
        return response()->json(['success' => true, 'data' => $lecon->fresh()]);
    }

    public function storeQuiz(Request $request, string $leconId): JsonResponse
    {
        $v = $request->validate([
            'titre'               => 'required|string|max:200',
            'duree_minutes'       => 'integer|min:1',
            'seuil_reussite'      => 'integer|min:1|max:100',
            'nb_tentatives_max'   => 'integer|min:1|max:10',
            'correction_immediate'=> 'boolean',
            'ordre_aleatoire'     => 'boolean',
        ]);

        $quiz = LmsQuiz::create([...$v, 'lecon_id' => $leconId]);
        return response()->json(['success' => true, 'data' => $quiz], 201);
    }

    public function storeQuestion(Request $request, string $quizId): JsonResponse
    {
        $v = $request->validate([
            'type'        => 'required|in:qcm,vrai_faux,reponse_courte',
            'enonce'      => 'required|string',
            'options'     => 'required|array',
            'explication' => 'nullable|string',
            'points'      => 'integer|min:1',
            'ordre'       => 'integer|min:1',
        ]);

        $ordre    = $v['ordre'] ?? LmsQuestion::where('quiz_id', $quizId)->max('ordre') + 1;
        $question = LmsQuestion::create([...$v, 'quiz_id' => $quizId, 'ordre' => $ordre]);
        LmsQuiz::find($quizId)?->increment('nb_questions');

        return response()->json(['success' => true, 'data' => $question], 201);
    }

    public function passerQuiz(Request $request, string $quizId): JsonResponse
    {
        $v = $request->validate([
            'inscription_id'  => 'required|uuid|exists:lms_inscriptions,id',
            'reponses'        => 'required|array',
            'duree_secondes'  => 'integer|min:0',
        ]);

        try {
            $tentative = $this->lms->soumettreTentativeQuiz(
                $quizId,
                auth('api')->id(),
                $v['inscription_id'],
                $v['reponses'],
                $v['duree_secondes'] ?? 0
            );

            return response()->json([
                'success'    => true,
                'data'       => $tentative,
                'message'    => $tentative->reussi ? '✅ Quiz réussi !' : '❌ Quiz non réussi — réessayez',
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function inscrire(Request $request): JsonResponse
    {
        $v = $request->validate([
            'cours_id' => 'required|uuid|exists:lms_cours,id',
            'eleve_id' => 'required|uuid|exists:eleves,id',
        ]);

        $inscription = $this->lms->inscrireEleve($v['cours_id'], $v['eleve_id']);
        return response()->json(['success' => true, 'data' => $inscription, 'message' => 'Inscrit au cours'], 201);
    }

    public function inscriptionEleve(string $eleveId): JsonResponse
    {
        $inscriptions = LmsInscription::where('eleve_id', $eleveId)
            ->with('cours:id,titre,matiere,image_url,nb_lecons,seuil_completion')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['success' => true, 'data' => $inscriptions]);
    }

    public function marquerLecon(Request $request, string $inscriptionId, string $leconId): JsonResponse
    {
        $v = $request->validate(['temps_secondes' => 'integer|min:0']);

        $result = $this->lms->marquerLeconComplete($inscriptionId, $leconId, $v['temps_secondes'] ?? 0);
        return response()->json([
            'success' => true,
            'data'    => $result,
            'message' => $result['cours_complete']
                ? '🎓 Cours terminé ! Certificat généré.'
                : "Progression : {$result['progression_pct']}%",
        ]);
    }

    public function progressionEleve(string $inscriptionId): JsonResponse
    {
        $inscription = LmsInscription::with([
            'cours.chapitres.lecons',
            'progressions',
        ])->findOrFail($inscriptionId);

        $completees = $inscription->progressions->where('completee', true)->pluck('lecon_id')->toArray();

        $chapitres = $inscription->cours->chapitres->map(fn($ch) => [
            'id'     => $ch->id,
            'titre'  => $ch->titre,
            'lecons' => $ch->lecons->map(fn($l) => [
                'id'        => $l->id,
                'titre'     => $l->titre,
                'type'      => $l->type,
                'completee' => in_array($l->id, $completees),
                'duree'     => $l->duree_minutes,
            ]),
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'inscription'    => $inscription,
                'chapitres'      => $chapitres,
                'progression_pct'=> $inscription->progression_pct,
                'certificat_url' => $inscription->certificat_url,
            ],
        ]);
    }

    public function soumettreDevoir(Request $request, string $leconId): JsonResponse
    {
        $request->validate([
            'inscription_id'    => 'required|uuid|exists:lms_inscriptions,id',
            'fichier'           => 'nullable|file|max:20480',
            'commentaire_eleve' => 'nullable|string|max:1000',
        ]);

        $cheminFichier = null;
        $nomFichier    = null;

        if ($request->hasFile('fichier')) {
            $file          = $request->file('fichier');
            $cheminFichier = $file->store("lms/devoirs/{$leconId}", 'public');
            $nomFichier    = $file->getClientOriginalName();
        }

        $devoir = LmsDevoir::updateOrCreate(
            ['lecon_id' => $leconId, 'eleve_id' => auth('api')->id()],
            [
                'inscription_id'    => $request->inscription_id,
                'fichier_url'       => $cheminFichier ? Storage::url($cheminFichier) : null,
                'fichier_nom'       => $nomFichier,
                'commentaire_eleve' => $request->commentaire_eleve,
                'statut'            => 'soumis',
                'soumis_le'         => now(),
            ]
        );

        return response()->json(['success' => true, 'data' => $devoir, 'message' => 'Devoir soumis'], 201);
    }

    public function corrigerDevoir(Request $request, string $devoirId): JsonResponse
    {
        $v = $request->validate([
            'note'                => 'required|numeric|min:0|max:20',
            'feedback_enseignant' => 'nullable|string|max:1000',
        ]);

        $devoir = LmsDevoir::findOrFail($devoirId);
        $devoir->update([
            ...$v,
            'statut'      => 'corrige',
            'corrige_par' => auth('api')->id(),
            'corrige_le'  => now(),
        ]);

        return response()->json(['success' => true, 'data' => $devoir->fresh()]);
    }

    public function telechargerCertificat(string $inscriptionId)
    {
        $inscription = LmsInscription::with(['cours.enseignant', 'eleve'])->findOrFail($inscriptionId);

        if (!$inscription->certificat_url) {
            $url = $this->lms->genererCertificat($inscription);
            $inscription->update(['certificat_url' => $url]);
        }

        $nomFichier = "certificat-{$inscription->eleve->nom}-{$inscription->cours->titre}.pdf";

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.lms-certificat', [
            'cours'       => $inscription->cours,
            'eleve'       => $inscription->eleve,
            'inscription' => $inscription,
        ])->setPaper('a4', 'landscape')->download($nomFichier);
    }

    public function dashboard(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->lms->getDashboard()]);
    }
}
