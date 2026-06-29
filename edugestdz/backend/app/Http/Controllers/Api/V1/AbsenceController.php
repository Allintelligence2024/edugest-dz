<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Jobs\NotifierAbsenceParent;
use App\Models\AbsenceJournaliere;
use App\Models\Badge;
use App\Models\Eleve;
use App\Models\JustificatifAbsence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbsenceController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date'          => 'nullable|date',
            'statut'        => 'nullable|in:absent,present,retard,demi_journee',
            'classe_id'     => 'nullable|uuid',
            'per_page'      => 'nullable|integer|min:5|max:100',
        ]);

        $date = $validated['date'] ?? today()->toDateString();

        $query = AbsenceJournaliere::with(['eleve:id,nom,prenom,photo_url,niveau_scolaire', 'justificatif'])
            ->whereDate('date_absence', $date);

        if (isset($validated['statut'])) {
            $query->where('statut', $validated['statut']);
        }

        $paginator = $query->orderBy('created_at', 'asc')
            ->paginate($validated['per_page'] ?? 30);

        $stats = AbsenceJournaliere::whereDate('date_absence', $date)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN statut = 'absent' THEN 1 ELSE 0 END) as absents,
                SUM(CASE WHEN statut = 'present' THEN 1 ELSE 0 END) as presents,
                SUM(CASE WHEN statut = 'retard' THEN 1 ELSE 0 END) as retards
            ")
            ->first();

        return $this->paginatedResponse($paginator, "Absences du {$date}", [
            'date'  => $date,
            'stats' => $stats,
        ]);
    }

    public function marquerPresent(Request $request, string $eleveId): JsonResponse
    {
        $validated = $request->validate([
            'statut'        => 'required|in:present,retard,demi_journee',
            'heure_arrivee' => 'nullable|date_format:H:i',
            'note'          => 'nullable|string|max:500',
        ]);

        $eleve = Eleve::findOrFail($eleveId);
        $today = today();

        $absence = AbsenceJournaliere::updateOrCreate(
            [
                'tenant_id'    => config('tenant.current_id'),
                'eleve_id'     => $eleve->id,
                'date_absence' => $today,
            ],
            [
                'statut'        => $validated['statut'],
                'heure_arrivee' => $validated['heure_arrivee'] ?? now()->format('H:i'),
                'signale_par'   => 'admin',
                'motif'         => $validated['note'] ?? null,
            ]
        );

        return $this->success([
            'absence' => $absence,
            'eleve'   => ['nom' => $eleve->nom, 'prenom' => $eleve->prenom],
        ], "{$eleve->prenom} {$eleve->nom} marqué(e) {$validated['statut']}");
    }

    public function justifier(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'motif'    => 'required|string|max:500',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'action'   => 'nullable|in:soumettre,valider,refuser',
        ]);

        $absence = AbsenceJournaliere::with('eleve')->findOrFail($id);
        $action  = $validated['action'] ?? 'soumettre';

        $documentUrl = null;
        if ($request->hasFile('document')) {
            $documentUrl = $request->file('document')
                ->store('justificatifs/' . config('tenant.current_id'), 'public');
        }

        $justificatif = JustificatifAbsence::updateOrCreate(
            ['absence_id' => $absence->id],
            [
                'tenant_id'    => config('tenant.current_id'),
                'motif'        => $validated['motif'],
                'document_url' => $documentUrl ?? null,
                'statut'       => match ($action) {
                    'valider'  => 'valide',
                    'refuser'  => 'refuse',
                    default    => 'en_attente',
                },
                'valide_par'   => in_array($action, ['valider', 'refuser']) ? auth()->id() : null,
                'valide_at'    => in_array($action, ['valider', 'refuser']) ? now() : null,
            ]
        );

        return $this->success([
            'justificatif' => $justificatif,
            'absence'      => $absence,
        ], "Justificatif {$justificatif->statut}");
    }

    public function rapport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mois'  => 'nullable|integer|min:1|max:12',
            'annee' => 'nullable|integer|min:2020|max:2030',
        ]);

        $mois  = $validated['mois']  ?? now()->month;
        $annee = $validated['annee'] ?? now()->year;

        $absences = AbsenceJournaliere::with('eleve:id,nom,prenom,niveau_scolaire')
            ->whereMonth('date_absence', $mois)
            ->whereYear('date_absence', $annee)
            ->get();

        $parEleve = $absences->groupBy('eleve_id')->map(function ($absences) {
            $eleve = $absences->first()->eleve;
            return [
                'eleve'          => ['id' => $eleve->id, 'nom' => $eleve->nom, 'prenom' => $eleve->prenom, 'niveau' => $eleve->niveau_scolaire],
                'total_absences' => $absences->where('statut', 'absent')->count(),
                'total_retards'  => $absences->where('statut', 'retard')->count(),
                'justifiees'     => $absences->filter(fn($a) => $a->justificatif?->statut === 'valide')->count(),
                'non_justifiees' => $absences->where('statut', 'absent')
                    ->filter(fn($a) => !$a->justificatif || $a->justificatif->statut !== 'valide')->count(),
            ];
        })->values();

        $aRisque = $parEleve->filter(fn($e) => $e['non_justifiees'] >= 3);

        return $this->success([
            'periode'       => ['mois' => $mois, 'annee' => $annee],
            'par_eleve'     => $parEleve,
            'a_risque'      => $aRisque->values(),
            'total_absences'=> $absences->where('statut', 'absent')->count(),
            'total_retards' => $absences->where('statut', 'retard')->count(),
        ], "Rapport absences {$mois}/{$annee}");
    }

    public function assignerBadge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eleve_id'      => 'required|uuid|exists:eleves,id',
            'badge_uid'     => 'required|string|max:100',
            'date_emission' => 'nullable|date',
        ]);

        $existant = Badge::where('badge_uid', $validated['badge_uid'])
            ->where('proprietaire_id', '!=', $validated['eleve_id'])
            ->exists();

        if ($existant) {
            return $this->error('Ce badge est déjà assigné à un autre utilisateur', 'BADGE_DEJA_ASSIGNE', 409);
        }

        $badge = Badge::updateOrCreate(
            [
                'tenant_id' => config('tenant.current_id'),
                'badge_uid' => $validated['badge_uid'],
            ],
            [
                'proprietaire_id'    => $validated['eleve_id'],
                'type_proprietaire'  => 'eleve',
                'actif'              => true,
                'date_emission'      => $validated['date_emission'] ?? today(),
            ]
        );

        $eleve = Eleve::find($validated['eleve_id']);

        return $this->created([
            'badge' => $badge,
            'eleve' => ['nom' => $eleve->nom, 'prenom' => $eleve->prenom],
        ], "Badge {$validated['badge_uid']} assigné à {$eleve->prenom} {$eleve->nom}");
    }
}
