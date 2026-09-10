<?php

namespace App\Services;

use App\Models\{AbsenceEnseignant, User};
use Illuminate\Support\Facades\{DB, Log};

class AbsenceEnseignantService
{
    public function __construct(
        private ParentNotificationService    $parentNotif,
        private NotificationInAppService     $inAppNotif,
    ) {}

    public function signalerAbsence(
        string  $enseignantUserId,
        string  $dateAbsence,
        ?string $motif = null,
        ?string $tenantId = null,
    ): AbsenceEnseignant {
        $tenantId = $tenantId ?? config('tenant.current_id');

        $existante = AbsenceEnseignant::withoutGlobalScopes()
            ->where('enseignant_user_id', $enseignantUserId)
            ->where('date_absence', $dateAbsence)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($existante) {
            if ($motif) $existante->update(['motif' => $motif]);
            return $existante;
        }

        $absence = AbsenceEnseignant::create([
            'tenant_id'          => $tenantId,
            'enseignant_user_id' => $enseignantUserId,
            'date_absence'       => $dateAbsence,
            'motif'              => $motif,
            'statut'             => 'signale',
        ]);

        $this->notifierDirecteur($absence);

        return $absence;
    }

    public function assignerRemplacant(
        string $absenceId,
        string $remplacantUserId,
    ): AbsenceEnseignant {
        $absence = AbsenceEnseignant::withoutGlobalScopes()->findOrFail($absenceId);

        $absence->update([
            'remplacant_user_id' => $remplacantUserId,
            'statut'             => 'remplace',
        ]);

        $seancesAffectees = $absence->seancesAffectees()->get();

        foreach ($seancesAffectees as $seance) {
            $seance->update([
                'enseignant_remplacement_id' => $remplacantUserId,
                'statut'                     => 'remplacement_confirme',
            ]);
        }

        $this->notifierElevesEtParents($absence);
        $this->notifierRemplacant($absence);

        return $absence;
    }

    private function notifierDirecteur(AbsenceEnseignant $absence): void
    {
        $enseignant  = User::find($absence->enseignant_user_id);
        $nomEns      = ($enseignant->nom ?? '') . ' ' . ($enseignant->prenom ?? '');
        $dateFormate = \Carbon\Carbon::parse($absence->date_absence)
            ->locale('fr')
            ->isoFormat('dddd D MMMM YYYY');

        $nbSeances = DB::table('seances as s')
            ->join('cours as c', 's.cours_id', '=', 'c.id')
            ->join('enseignants as ens', 'c.enseignant_id', '=', 'ens.id')
            ->where('c.tenant_id', $absence->tenant_id)
            ->where('ens.user_id', $absence->enseignant_user_id)
            ->where('s.date_seance', $absence->date_absence)
            ->count();

        $directeurs = User::where('tenant_id', $absence->tenant_id)
            ->whereHas('role', fn($q) => $q->where('nom', 'admin'))
            ->get();

        foreach ($directeurs as $directeur) {
            $this->inAppNotif->creer(
                userId:   $directeur->id,
                type:     'absence_enseignant',
                titre:    "Absence signal\u00e9e \u2014 Prof. {$nomEns}",
                corps:    "{$nbSeances} s\u00e9ance(s) affect\u00e9e(s) le {$dateFormate}" .
                         ($absence->motif ? " \u00b7 Motif : {$absence->motif}" : ''),
                meta:    [
                    'absence_id'         => $absence->id,
                    'enseignant_user_id' => $absence->enseignant_user_id,
                    'date_absence'       => $absence->date_absence,
                    'nb_seances'         => $nbSeances,
                    'action_url'         => "/planning/remplacements/{$absence->id}",
                    'urgence'            => true,
                ],
                tenantId: $absence->tenant_id,
            );
        }

        Log::info("AbsenceEnseignant: directeur notifi\u00e9 \u2014 {$nomEns} absent le {$absence->date_absence}");
    }

    private function notifierElevesEtParents(AbsenceEnseignant $absence): void
    {
        if ($absence->eleves_notifies) return;

        $remplacant = $absence->remplacant_user_id
            ? User::find($absence->remplacant_user_id)
            : null;

        $dateFormate = \Carbon\Carbon::parse($absence->date_absence)
            ->locale('fr')
            ->isoFormat('dddd D MMMM');

        $eleveIds = DB::table('seances as s')
            ->join('cours as c', 's.cours_id', '=', 'c.id')
            ->join('enseignants as ens', 'c.enseignant_id', '=', 'ens.id')
            ->join('inscriptions as i', 'c.groupe_id', '=', 'i.groupe_id')
            ->where('c.tenant_id', $absence->tenant_id)
            ->where('ens.user_id', $absence->enseignant_user_id)
            ->where('s.date_seance', $absence->date_absence)
            ->where('i.statut', 'valid\u00e9e')
            ->distinct()
            ->pluck('i.eleve_id');

        foreach ($eleveIds as $eleveId) {
            $eleve = \App\Models\Eleve::find($eleveId);
            if (!$eleve || !$eleve->user_id) continue;

            $this->inAppNotif->creer(
                userId:   $eleve->user_id,
                type:     'cours_modifie',
                titre:    $remplacant
                    ? "Cours remplac\u00e9 le {$dateFormate}"
                    : "Cours modifi\u00e9 le {$dateFormate}",
                corps:    $remplacant
                    ? "Prof. " . ($remplacant->nom ?? '') . " vous remplacera"
                    : "Cours suspendu \u2014 contactez l'administration",
                meta:     ['absence_id' => $absence->id, 'date' => $absence->date_absence],
                tenantId: $absence->tenant_id,
            );

            try {
                $this->parentNotif->notifier(
                    eleveId: $eleveId,
                    type:    'cours_modifie',
                    titre:   "Cours modifi\u00e9 le {$dateFormate}",
                    corps:   $remplacant
                        ? "Un rempla\u00e7ant a \u00e9t\u00e9 assign\u00e9 pour votre enfant"
                        : "Un cours de votre enfant est modifi\u00e9. Contactez l'\u00e9cole.",
                    meta:    ['absence_id' => $absence->id],
                    avecSMS: false,
                );
            } catch (\Throwable $e) {
                Log::warning("AbsenceEnseignant: notif parent \u00e9chou\u00e9e: " . $e->getMessage());
            }
        }

        $absence->update(['eleves_notifies' => true, 'parents_notifies' => true]);
    }

    private function notifierRemplacant(AbsenceEnseignant $absence): void
    {
        if (!$absence->remplacant_user_id) return;

        $dateFormate = \Carbon\Carbon::parse($absence->date_absence)
            ->locale('fr')
            ->isoFormat('dddd D MMMM YYYY');

        $this->inAppNotif->creer(
            userId:   $absence->remplacant_user_id,
            type:     'remplacement_assigne',
            titre:    "Remplacement assign\u00e9 \u2014 {$dateFormate}",
            corps:    "Vous \u00eates \u00e9t\u00e9 d\u00e9sign\u00e9(e) rempla\u00e7ant(e) pour ce jour. Consultez votre planning.",
            meta:     ['absence_id' => $absence->id, 'date' => $absence->date_absence, 'action_url' => '/planning'],
            tenantId: $absence->tenant_id,
        );
    }
}
