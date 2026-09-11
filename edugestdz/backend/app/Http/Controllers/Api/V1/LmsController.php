<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LmsChapitre;
use App\Models\LmsCours;
use App\Models\LmsInscription;
use App\Models\LmsLecon;
use App\Services\LmsContenuService;
use App\Services\LmsCoursService;
use App\Services\LmsDevoirService;
use App\Services\LmsInscriptionService;
use App\Services\LmsQuizService;
use App\Services\LmsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LmsController extends Controller
{
    public function __construct(
        private LmsCoursService $cours,
        private LmsContenuService $contenu,
        private LmsQuizService $quiz,
        private LmsInscriptionService $suivi,
        private LmsDevoirService $devoirs,
        private LmsService $lms,
    ) {}

    public function indexCours(Request $request): JsonResponse
    {
        $result = $this->cours->indexCours($request);

        return response()->json([
            'success' => true,
            'data'    => $result['paginator'],
            'stats'   => $result['stats'],
        ]);
    }

    public function storeCours(Request $request): JsonResponse
    {
        $result = $this->cours->storeCours($request->all());

        return response()->json(['success' => true, 'data' => $result['cours'], 'message' => 'Cours créé'], 201);
    }

    public function showCours(string $id): JsonResponse
    {
        $result = $this->cours->showCours($id);

        return response()->json(['success' => true, 'data' => $result['cours']]);
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
        $result = $this->cours->publierCours($id);

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'message' => $result['error']], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => $result['cours'],
            'message' => $result['message'],
        ]);
    }

    public function storeChapitre(Request $request, string $coursId): JsonResponse
    {
        $result = $this->contenu->storeChapitre($coursId, $request->all());

        return response()->json(['success' => true, 'data' => $result['chapitre']], 201);
    }

    public function updateChapitre(Request $request, string $id): JsonResponse
    {
        $chapitre = LmsChapitre::findOrFail($id);
        $chapitre->update($request->only(['titre', 'description', 'ordre', 'publie']));
        return response()->json(['success' => true, 'data' => $chapitre->fresh()]);
    }

    public function deleteChapitre(string $id): JsonResponse
    {
        $result = $this->contenu->deleteChapitre($id);

        return response()->json(['success' => true, 'message' => $result['message']]);
    }

    public function storeLecon(Request $request, string $chapitreId): JsonResponse
    {
        $result = $this->contenu->storeLecon($chapitreId, $request->all());

        return response()->json(['success' => true, 'data' => $result['lecon']], 201);
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
        $result = $this->contenu->uploadFichierLecon($id, $request);

        return response()->json(['success' => true, 'data' => $result['lecon']]);
    }

    public function storeQuiz(Request $request, string $leconId): JsonResponse
    {
        $result = $this->quiz->storeQuiz($leconId, $request->all());

        return response()->json(['success' => true, 'data' => $result['quiz']], 201);
    }

    public function storeQuestion(Request $request, string $quizId): JsonResponse
    {
        $result = $this->quiz->storeQuestion($quizId, $request->all());

        return response()->json(['success' => true, 'data' => $result['question']], 201);
    }

    public function passerQuiz(Request $request, string $quizId): JsonResponse
    {
        $result = $this->quiz->passerQuiz($quizId, $request->all());

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'message' => $result['error']], 422);
        }

        return response()->json([
            'success'    => true,
            'data'       => $result['tentative'],
            'message'    => $result['message'],
        ], 201);
    }

    public function inscrire(Request $request): JsonResponse
    {
        $result = $this->suivi->inscrire($request->all());

        return response()->json(['success' => true, 'data' => $result['inscription'], 'message' => 'Inscrit au cours'], 201);
    }

    public function inscriptionEleve(string $eleveId): JsonResponse
    {
        $result = $this->suivi->inscriptionEleve($eleveId);

        return response()->json(['success' => true, 'data' => $result['inscriptions']]);
    }

    public function marquerLecon(Request $request, string $inscriptionId, string $leconId): JsonResponse
    {
        $result = $this->suivi->marquerLecon($inscriptionId, $leconId, $request->all());

        return response()->json([
            'success' => true,
            'data'    => $result['result'],
            'message' => $result['message'],
        ]);
    }

    public function progressionEleve(string $inscriptionId): JsonResponse
    {
        $result = $this->suivi->progressionEleve($inscriptionId);

        return response()->json([
            'success' => true,
            'data'    => [
                'inscription'    => $result['inscription'],
                'chapitres'      => $result['chapitres'],
                'progression_pct'=> $result['progression_pct'],
                'certificat_url' => $result['certificat_url'],
            ],
        ]);
    }

    public function soumettreDevoir(Request $request, string $leconId): JsonResponse
    {
        $result = $this->devoirs->soumettreDevoir($leconId, $request);

        return response()->json(['success' => true, 'data' => $result['devoir'], 'message' => 'Devoir soumis'], 201);
    }

    public function corrigerDevoir(Request $request, string $devoirId): JsonResponse
    {
        $result = $this->devoirs->corrigerDevoir($devoirId, $request->all());

        return response()->json(['success' => true, 'data' => $result['devoir']]);
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
