<?php

namespace App\Services;

use App\Models\SessionExamen;
use App\Models\CandidatExamen;
use App\Models\SalleExamen;
use App\Models\SurveiillantExamen;
use App\Models\Eleve;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class ExamenService
{
    public function affecterCandidatsAuxSalles(string $sessionId): array
    {
        $session   = SessionExamen::with(['salles', 'candidats'])->findOrFail($sessionId);
        $salles    = $session->salles->sortBy('nom');
        $candidats = $session->candidats()
            ->orderByRaw("CASE WHEN besoins_speciaux THEN 0 ELSE 1 END")
            ->orderBy('nom')
            ->get();

        if ($salles->isEmpty()) {
            throw new \RuntimeException("Aucune salle définie pour cette session.");
        }

        $maxParSalle = $session->max_candidats_par_salle ?: 20;
        $total       = $candidats->count();
        $affectes    = 0;
        $salleIndex  = 0;
        $placeIndex  = 1;

        DB::transaction(function () use ($candidats, $salles, $maxParSalle, &$affectes, &$salleIndex, &$placeIndex) {
            foreach ($salles as $salle) {
                $salle->update(['nb_candidats_affectes' => 0]);
            }

            foreach ($candidats as $candidat) {
                if ($salleIndex >= $salles->count()) {
                    Log::warning("Manque de salles pour affecter tous les candidats");
                    break;
                }

                $salle = $salles->values()->get($salleIndex);

                $nbCol = $salle->nb_colonnes ?: 5;
                $rangee = chr(64 + (int) ceil($placeIndex / $nbCol));
                $col    = (($placeIndex - 1) % $nbCol) + 1;

                $candidat->update([
                    'salle_id'      => $salle->id,
                    'numero_place'  => $placeIndex,
                    'rangee'        => $rangee,
                    'colonne'       => $col,
                ]);

                $salle->increment('nb_candidats_affectes');
                $affectes++;
                $placeIndex++;

                if ($salle->nb_candidats_affectes >= $maxParSalle) {
                    $salleIndex++;
                    $placeIndex = 1;
                }
            }
        });

        return [
            'total_candidats' => $total,
            'affectes'        => $affectes,
            'salles_utilisees'=> $salleIndex + 1,
            'message'         => "Affectation terminée : {$affectes}/{$total} candidats répartis en " . ($salleIndex + 1) . " salle(s).",
        ];
    }

    public function affecterSurveillantsAuxSalles(string $sessionId): array
    {
        $session     = SessionExamen::with(['salles', 'epreuves'])->findOrFail($sessionId);
        $salles      = $session->salles->where('nb_candidats_affectes', '>', 0)->sortBy('nom');
        $surveillants= SurveiillantExamen::where('session_id', $sessionId)
            ->where('role', 'surveillant')
            ->where('disponible', true)
            ->get();

        $nbSalles        = $salles->count();
        $nbSurveillants  = $surveillants->count();
        $nbParSalle      = $session->nb_surveillants_par_salle ?: 3;
        $nbRequis        = $nbSalles * $nbParSalle;

        if ($nbSurveillants < $nbRequis) {
            Log::warning("Session {$sessionId}: {$nbSurveillants}/{$nbRequis} surveillants disponibles");
        }

        $matieresDeLaSession = $session->epreuves->pluck('matiere')->unique()->toArray();

        SurveiillantExamen::where('session_id', $sessionId)
            ->where('role', 'surveillant')
            ->update(['salle_id' => null, 'salle_nom' => null]);

        $affectations = 0;
        $sallesList   = $salles->values();
        $survIndex    = 0;

        DB::transaction(function () use (
            $sallesList, $surveillants, $matieresDeLaSession,
            $nbParSalle, &$affectations, &$survIndex
        ) {
            foreach ($sallesList as $salle) {
                $affectesASalle = 0;
                $tentatives     = 0;
                $nbSurv         = $surveillants->count();

                while ($affectesASalle < $nbParSalle && $tentatives < $nbSurv * 2) {
                    if ($survIndex >= $nbSurv) $survIndex = 0;

                    $surveillant = $surveillants->values()->get($survIndex);
                    $tentatives++;

                    $specialiteOk = !in_array(
                        $surveillant->specialite,
                        $matieresDeLaSession
                    );

                    if ($specialiteOk) {
                        $surveillant->update([
                            'salle_id'  => $salle->id,
                            'salle_nom' => $salle->nom,
                        ]);
                        $affectesASalle++;
                        $affectations++;
                    }

                    $survIndex++;
                }
            }
        });

        return [
            'salles_traitees' => $nbSalles,
            'affectations'    => $affectations,
            'surveillants_disponibles' => $nbSurveillants,
            'requis'          => $nbRequis,
            'manque'          => max(0, $nbRequis - $nbSurveillants),
            'message'         => $affectations >= $nbRequis
                ? "✅ Tous les surveillants affectés ({$affectations}/{$nbRequis})"
                : "⚠️ {$affectations}/{$nbRequis} affectations. Manque " . max(0, $nbRequis - $nbSurveillants) . " surveillant(s).",
        ];
    }

    public function genererConvocationCandidat(string $candidatId): \Barryvdh\DomPDF\PDF
    {
        $candidat = CandidatExamen::with(['session.epreuves', 'salle'])->findOrFail($candidatId);
        $session  = $candidat->session;

        $epreuves = $session->epreuves->sortBy('date_epreuve');

        $html = view('pdf.convocation-candidat', compact('candidat', 'session', 'epreuves'))->render();

        $candidat->update(['convocation_imprimee' => true]);

        return Pdf::loadHtml($html)->setPaper('a5', 'portrait');
    }

    public function genererToutesConvocations(string $sessionId): \Barryvdh\DomPDF\PDF
    {
        $session  = SessionExamen::with(['epreuves', 'candidats.salle'])->findOrFail($sessionId);
        $candidats= $session->candidats->sortBy(['salle.nom', 'numero_place']);
        $epreuves = $session->epreuves->sortBy('date_epreuve');

        $html = view('pdf.convocations-masse', compact('session', 'candidats', 'epreuves'))->render();

        CandidatExamen::where('session_id', $sessionId)
            ->update(['convocation_imprimee' => true]);

        return Pdf::loadHtml($html)->setPaper('a5', 'portrait');
    }

    public function genererConvocationSurveillant(string $surveillantId): \Barryvdh\DomPDF\PDF
    {
        $surveillant = SurveiillantExamen::with(['session.epreuves', 'salle'])->findOrFail($surveillantId);
        $session     = $surveillant->session;
        $epreuves    = $session->epreuves->sortBy('date_epreuve');

        $html = view('pdf.convocation-surveillant', compact('surveillant', 'session', 'epreuves'))->render();

        $surveillant->update(['convocation_imprimee' => true]);

        return Pdf::loadHtml($html)->setPaper('a4', 'portrait');
    }

    public function genererFeuillePresence(string $salleId): \Barryvdh\DomPDF\PDF
    {
        $salle      = SalleExamen::with(['session.epreuves', 'candidats', 'surveillants'])->findOrFail($salleId);
        $session    = $salle->session;
        $candidats  = $salle->candidats->sortBy('numero_place');
        $surveillants = $salle->surveillants;
        $epreuves   = $session->epreuves->sortBy('date_epreuve');

        $html = view('pdf.feuille-presence', compact('salle', 'session', 'candidats', 'surveillants', 'epreuves'))->render();

        return Pdf::loadHtml($html)->setPaper('a4', 'portrait');
    }

    public function genererPlanSalle(string $salleId): \Barryvdh\DomPDF\PDF
    {
        $salle     = SalleExamen::with(['candidats'])->findOrFail($salleId);
        $candidats = $salle->candidats->keyBy(fn($c) => "{$c->rangee}{$c->colonne}");
        $nbCol     = $salle->nb_colonnes ?: 5;
        $nbRangees = $salle->nb_rangees  ?: (int) ceil($salle->nb_candidats_affectes / $nbCol);

        $html = view('pdf.plan-salle', compact('salle', 'candidats', 'nbCol', 'nbRangees'))->render();

        return Pdf::loadHtml($html)->setPaper('a4', 'landscape');
    }

    public function importerCandidats(string $sessionId, string $csvPath): array
    {
        $lignes   = array_map('str_getcsv', file($csvPath));
        $entetes  = array_map('trim', array_shift($lignes));
        $importes = 0;
        $erreurs  = [];

        foreach ($lignes as $i => $ligne) {
            try {
                $data = array_combine($entetes, $ligne);
                CandidatExamen::create([
                    'session_id'          => $sessionId,
                    'tenant_id'           => config('tenant.current_id'),
                    'nom'                 => trim($data['nom'] ?? ''),
                    'prenom'              => trim($data['prenom'] ?? ''),
                    'date_naissance'      => $data['date_naissance'] ?? null,
                    'lieu_naissance'      => $data['lieu_naissance'] ?? null,
                    'numero_inscription'  => trim($data['numero_inscription'] ?? ''),
                    'type_candidat'       => $data['type'] ?? 'scolarise',
                    'filiere'             => $data['filiere'] ?? null,
                ]);
                $importes++;
            } catch (\Throwable $e) {
                $erreurs[] = "Ligne " . ($i + 2) . ": " . $e->getMessage();
            }
        }

        return ['importes' => $importes, 'erreurs' => $erreurs];
    }

    public function importerElevesSysteme(string $sessionId, array $eleveIds): array
    {
        $session  = SessionExamen::findOrFail($sessionId);
        $importes = 0;

        foreach ($eleveIds as $eleveId) {
            $eleve = Eleve::find($eleveId);
            if (!$eleve) continue;

            $existe = CandidatExamen::where('session_id', $sessionId)
                ->where('eleve_id', $eleveId)->exists();
            if ($existe) continue;

            CandidatExamen::create([
                'session_id'   => $sessionId,
                'tenant_id'    => config('tenant.current_id'),
                'eleve_id'     => $eleveId,
                'nom'          => $eleve->nom,
                'prenom'       => $eleve->prenom,
                'date_naissance'=> $eleve->date_naissance,
                'type_candidat' => 'scolarise',
                'filiere'       => $eleve->niveau_scolaire,
            ]);
            $importes++;
        }

        return ['importes' => $importes];
    }

    public function getDashboard(string $sessionId): array
    {
        $session = SessionExamen::with(['salles', 'candidats', 'surveillants'])->findOrFail($sessionId);

        $nbSalles      = $session->salles->count();
        $nbCandidats   = $session->candidats->count();
        $nbAffectes    = $session->candidats->whereNotNull('salle_id')->count();
        $nbSurv        = $session->surveillants->where('role', 'surveillant')->count();
        $nbSurvAfff    = $session->surveillants->where('role', 'surveillant')->whereNotNull('salle_id')->count();
        $nbSurvRequis  = $nbSalles * ($session->nb_surveillants_par_salle ?: 3);

        return [
            'session'                  => $session,
            'nb_candidats_total'       => $nbCandidats,
            'nb_candidats_affectes'    => $nbAffectes,
            'nb_candidats_non_affectes'=> $nbCandidats - $nbAffectes,
            'nb_salles'                => $nbSalles,
            'nb_salles_requises'       => $session->getNbSallesRequiseAttribute(),
            'nb_surveillants'          => $nbSurv,
            'nb_surveillants_requis'   => $nbSurvRequis,
            'nb_surveillants_manquants'=> max(0, $nbSurvRequis - $nbSurv),
            'nb_surveillants_affectes' => $nbSurvAfff,
            'nb_convocations_imprimees'=> $session->candidats->where('convocation_imprimee', true)->count(),
            'pret_pour_examen'         => $nbAffectes === $nbCandidats && $nbSurv >= $nbSurvRequis,
            'alertes'                  => $this->getAlertes($session, $nbSurvRequis, $nbSurv, $nbCandidats, $nbAffectes),
        ];
    }

    private function getAlertes($session, $nbSurvRequis, $nbSurv, $nbCandidats, $nbAffectes): array
    {
        $alertes = [];
        if ($nbAffectes < $nbCandidats)
            $alertes[] = ['type' => 'danger', 'msg' => ($nbCandidats - $nbAffectes) . " candidat(s) sans salle — Lancer l'affectation automatique"];
        if ($nbSurv < $nbSurvRequis)
            $alertes[] = ['type' => 'danger', 'msg' => "Manque " . ($nbSurvRequis - $nbSurv) . " surveillant(s) — Ajouter des surveillants"];
        if ($session->salles->isEmpty())
            $alertes[] = ['type' => 'warning', 'msg' => "Aucune salle définie — Créer les salles d'abord"];
        if ($session->epreuves->isEmpty())
            $alertes[] = ['type' => 'warning', 'msg' => "Aucune épreuve planifiée — Ajouter le calendrier des matières"];
        return $alertes;
    }
}
