<?php

namespace App\Services;

use App\Models\CongePersonnel;
use App\Models\PaiePersonnel;
use App\Models\PersonnelNonEnseignant;
use App\Models\PointagePersonnel;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaiePersonnelService
{
    private array $baremeIRG = [
        ['min' => 0,      'max' => 20000,  'taux' => 0,  'deduction' => 0],
        ['min' => 20001,  'max' => 40000,  'taux' => 23, 'deduction' => 4600],
        ['min' => 40001,  'max' => 80000,  'taux' => 27, 'deduction' => 6200],
        ['min' => 80001,  'max' => 160000, 'taux' => 30, 'deduction' => 8600],
        ['min' => 160001, 'max' => 320000, 'taux' => 33, 'deduction' => 13400],
        ['min' => 320001, 'max' => null,   'taux' => 35, 'deduction' => 19800],
    ];

    public function calculerPaie(PersonnelNonEnseignant $agent, int $mois, int $annee): array
    {
        $debut = Carbon::create($annee, $mois, 1)->startOfMonth();
        $fin   = $debut->copy()->endOfMonth();

        $joursTravailles = PointagePersonnel::where('agent_id', $agent->id)
            ->whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            ->whereIn('statut', ['present', 'retard'])
            ->count();

        $joursOuvrables = 0;
        $current = $debut->copy();
        while ($current->lte($fin)) {
            if (!in_array($current->dayOfWeek, [5, 6])) {
                $joursOuvrables++;
            }
            $current->addDay();
        }

        $salaireBrut = match ($agent->type_contrat) {
            'journalier' => $joursTravailles * ($agent->salaire_base / 26),
            'vacataire'  => $joursTravailles * ($agent->salaire_base),
            default      => (float) $agent->salaire_base,
        };

        $absencesInjustifiees = PointagePersonnel::where('agent_id', $agent->id)
            ->whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            ->where('statut', 'absent')
            ->where('impact_paie', true)
            ->sum('retenue_dzd');

        $salaireBrut = max(0, $salaireBrut - $absencesInjustifiees);

        $cnas = $agent->num_cnas ? round($salaireBrut * 0.09, 2) : 0.0;

        $baseImposable = $salaireBrut - $cnas;
        $irg = $this->calculerIRG($baseImposable);

        $salaireNet = max(0, round($salaireBrut - $cnas - $irg, 2));

        return [
            'agent_id'            => $agent->id,
            'mois'                => $mois,
            'annee'               => $annee,
            'salaire_base'        => round($salaireBrut, 2),
            'jours_travailles'    => $joursTravailles,
            'jours_ouvrables'     => $joursOuvrables,
            'retenues_absences'   => round($absencesInjustifiees, 2),
            'cnas'                => $cnas,
            'irg'                 => $irg,
            'salaire_net'         => $salaireNet,
            'statut'              => 'brouillon',
        ];
    }

    private function calculerIRG(float $base): float
    {
        if ($base <= 0) return 0.0;
        foreach ($this->baremeIRG as $tranche) {
            if ($tranche['max'] === null || $base <= $tranche['max']) {
                return max(0.0, round(($base * $tranche['taux'] / 100) - $tranche['deduction'], 2));
            }
        }
        $last = end($this->baremeIRG);
        return max(0.0, round(($base * $last['taux'] / 100) - $last['deduction'], 2));
    }

    public function genererPDF(PaiePersonnel $paie): string
    {
        $paie->load('agent');
        $tenant  = Tenant::find($paie->tenant_id);
        $moisNom = Carbon::create($paie->annee, $paie->mois)->translatedFormat('F Y');

        $pdf = Pdf::loadView('pdf.paie_personnel', [
            'paie'    => $paie,
            'agent'   => $paie->agent,
            'tenant'  => $tenant,
            'moisNom' => $moisNom,
            'detail'  => [
                'taux_cnas'      => '9%',
                'base_imposable' => max(0, $paie->salaire_base - $paie->cnas),
                'smig'           => '20 000 DA',
            ],
        ])->setPaper('A4', 'portrait');

        $path = "paies_personnel/{$paie->tenant_id}/{$paie->annee}/{$paie->mois}/{$paie->agent->matricule}.pdf";
        Storage::disk('public')->put($path, $pdf->output());
        $paie->update(['fichier_url' => $path]);
        return $path;
    }

    public function calculerTousMois(int $mois, int $annee): array
    {
        $agents    = PersonnelNonEnseignant::actifs()->get();
        $resultats = [];

        DB::transaction(function () use ($agents, $mois, $annee, &$resultats) {
            foreach ($agents as $agent) {
                $calcul = $this->calculerPaie($agent, $mois, $annee);
                $paie   = PaiePersonnel::updateOrCreate(
                    ['agent_id' => $agent->id, 'mois' => $mois, 'annee' => $annee],
                    array_merge($calcul, ['tenant_id' => $agent->tenant_id])
                );
                $resultats[] = [
                    'agent'   => $agent->nom_complet,
                    'poste'   => $agent->poste_affiche,
                    'paie_id' => $paie->id,
                    'net'     => $calcul['salaire_net'],
                ];
            }
        });

        return $resultats;
    }
}
