<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FactureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'eleve_id' => $this->eleve_id,
            'numero' => $this->numero,
            'montant' => (float) $this->montant,
            'statut' => $this->statut,
            'date_emission' => $this->date_emission,
            'date_echeance' => $this->date_echeance,
            'mois' => $this->mois,
            'annee' => $this->annee,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
