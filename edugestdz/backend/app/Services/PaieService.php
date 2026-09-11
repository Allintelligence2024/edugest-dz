<?php
namespace App\Services;

use App\Models\{Enseignant, Paie, Seance};
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PaieService
{
    private array $baremeIRG = [
        ['min' => 0,      'max' => 20000,  'taux' => 0,    'deduction' => 0],
        ['min' => 20001,  'max' => 40000,  'taux' => 23,   'deduction' => 4600],
        ['min' => 40001,  'max' => 80000,  'taux' => 27,   'deduction' => 6200],
        ['min' => 80001,  'max' => 160000, 'taux' => 30,   'deduction' => 8600],
        ['min' => 160001, 'max' => 320000, 'taux' => 33,   'deduction' => 13400],
        ['min' => 320001, 'max' => null,   'taux' => 35,   'deduction' => 19800],
    ];

    public function calculerPaie(Enseignant $enseignant, int $mois, int $annee): array
    {
        $heures = $this->calculerHeures($enseignant, $mois, $annee);

        $salaireBrut = match ($enseignant->type_contrat) {
            'vacataire', 'freelance' => $heures * ($enseignant->taux_horaire ?? 0),
            default => $enseignant->salaire_base ?? 0,
        };

        $cnas        = $this->calculerCNAS($enseignant, $salaireBrut);
        $salaireable = $salaireBrut - $cnas;
        $irg         = $this->calculerIRG($salaireable);
        $salaireNet  = $salaireBrut - $cnas - $irg;

        return [
            'enseignant_id'    => $enseignant->id,
            'mois'             => $mois,
            'annee'            => $annee,
            'salaire_base'     => round($salaireBrut, 2),
            'heures_travaillees' => $heures,
            'taux_horaire'     => $enseignant->taux_horaire ?? 0,
            'irg'              => round($irg, 2),
            'cnas'             => round($cnas, 2),
            'salaire_net'      => round(max(0, $salaireNet), 2),
            'statut'           => 'brouillon',
        ];
    }

    public function calculerHeures(Enseignant $enseignant, int $mois, int $annee): float
    {
        $debut = Carbon::create($annee, $mois, 1)->startOfMonth();
        $fin   = $debut->copy()->endOfMonth();

        $seances = Seance::whereHas('cours', fn($q) =>
            $q->where('enseignant_id', $enseignant->id)
        )
        ->where('statut', 'terminée')
        ->whereBetween('date_seance', [$debut->toDateString(), $fin->toDateString()])
        ->with('cours')
        ->get();

        return $seances->sum(fn($s) =>
            Carbon::parse($s->cours->heure_debut)
                  ->diffInMinutes(Carbon::parse($s->cours->heure_fin)) / 60
        );
    }

    private function calculerCNAS(Enseignant $enseignant, float $brut): float
    {
        if (in_array($enseignant->type_contrat, ['freelance', 'vacataire'])
            && empty($enseignant->num_cnas)) {
            return 0.0;
        }
        return $brut * 0.09;
    }

    public function calculerIRG(float $base): float
    {
        if ($base <= 0) return 0.0;

        foreach ($this->baremeIRG as $tranche) {
            $max = $tranche['max'];
            if ($max === null || $base <= $max) {
                $irg = ($base * $tranche['taux'] / 100) - $tranche['deduction'];
                return max(0.0, round($irg, 2));
            }
        }

        $derniere = end($this->baremeIRG);
        return max(0.0, ($base * $derniere['taux'] / 100) - $derniere['deduction']);
    }

    public function genererBulletinPDF(Paie $paie): string
    {
        $paie->load('enseignant');
        $tenant  = \App\Models\Tenant::find($paie->tenant_id);
        $moisNom = Carbon::create($paie->annee, $paie->mois)->translatedFormat('F Y');

        $pdf = Pdf::loadView('pdf.bulletin_paie', [
            'paie'    => $paie,
            'tenant'  => $tenant,
            'moisNom' => $moisNom,
            'detail'  => [
                'taux_cnas'       => '9%',
                'base_imposable'  => max(0, $paie->salaire_base - ($paie->cnas ?? 0)),
                'smig'            => '20 000 DA',
            ],
        ])->setPaper('A4', 'portrait');

        $path = "paies/{$tenant->id}/{$paie->annee}/{$paie->mois}/{$paie->enseignant->matricule}.pdf";
        \Storage::disk('public')->put($path, $pdf->output());
        $paie->update(['bulletin_url' => $path]);
        return $path;
    }

    public function calculerPaiesMensuelles(int $mois, int $annee): array
    {
        $enseignants = Enseignant::where('statut', 'actif')->get();
        $resultats   = [];

        DB::transaction(function () use ($enseignants, $mois, $annee, &$resultats) {
            foreach ($enseignants as $enseignant) {
                $calcul = $this->calculerPaie($enseignant, $mois, $annee);

                $paie = Paie::updateOrCreate(
                    ['enseignant_id' => $enseignant->id, 'mois' => $mois, 'annee' => $annee],
                    [...$calcul, 'tenant_id' => $enseignant->tenant_id]
                );

                $resultats[] = [
                    'enseignant' => "{$enseignant->nom} {$enseignant->prenom}",
                    'paie_id'    => $paie->id,
                    'net'        => $calcul['salaire_net'],
                    'heures'     => $calcul['heures_travaillees'],
                ];
            }
        });

        return $resultats;
    }
}
