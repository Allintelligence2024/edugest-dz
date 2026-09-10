<?php

namespace App\Services;

use App\Models\MenuCantine;
use App\Models\RepasJournalier;
use Illuminate\Support\Facades\Validator;

class PointageCantineService
{
    /**
     * @return array{date: mixed, type_repas: mixed, enregistres: int, presents: int, absents: int, message: string}
     */
    public function pointer(array $data): array
    {
        $validated = Validator::make($data, [
            'date'                   => 'nullable|date',
            'type_repas'             => 'required|in:dejeuner,diner,petit_dejeuner',
            'pointages'              => 'required|array|min:1',
            'pointages.*.eleve_id'   => 'required|uuid|exists:eleves,id',
            'pointages.*.present'    => 'required|boolean',
        ])->validate();

        /** @var array<int, array<string, mixed>> $pointagesData */
        $pointagesData = $validated['pointages'];

        $date = $validated['date'] ?? today()->toDateString();
        $menu = MenuCantine::where('date_repas', $date)
            ->where('type_repas', $validated['type_repas'])
            ->first();

        $enregistres = 0;
        foreach ($pointagesData as $p) {
            RepasJournalier::updateOrCreate(
                [
                    'tenant_id'  => config('tenant.current_id'),
                    'eleve_id'   => $p['eleve_id'],
                    'date_repas' => $date,
                    'type_repas' => $validated['type_repas'],
                ],
                [
                    'menu_id'       => $menu?->id,
                    'present'       => $p['present'],
                    'prix_applique' => $p['present'] ? ($menu?->prix_unitaire ?? 0) : 0,
                    'signale_par'   => 'admin',
                ]
            );
            $enregistres++;
        }

        $presents = collect($pointagesData)->where('present', true)->count();
        $absents  = collect($pointagesData)->where('present', false)->count();

        return [
            'date'        => $date,
            'type_repas'  => $validated['type_repas'],
            'enregistres' => $enregistres,
            'presents'    => $presents,
            'absents'     => $absents,
            'message'     => "{$enregistres} repas pointe(s) pour le {$date}",
        ];
    }

    /**
     * @return array{date: string, menu: mixed, repas: mixed, stats: array{total: int, presents: int, absents: int, ca_jour: mixed}}
     */
    public function pointageDate(string $date): array
    {
        $repas = RepasJournalier::with('eleve:id,nom,prenom', 'menu')
            ->where('date_repas', $date)
            ->get();

        $menu = MenuCantine::where('date_repas', $date)
            ->where('type_repas', 'dejeuner')
            ->first();

        return [
            'date'  => $date,
            'menu'  => $menu,
            'repas' => $repas,
            'stats' => [
                'total'   => $repas->count(),
                'presents'=> $repas->where('present', true)->count(),
                'absents' => $repas->where('present', false)->count(),
                'ca_jour' => $repas->where('present', true)->sum('prix_applique'),
            ],
        ];
    }
}
