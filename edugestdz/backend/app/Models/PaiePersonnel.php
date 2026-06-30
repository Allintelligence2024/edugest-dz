<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiePersonnel extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'paies_personnel';

    protected $fillable = [
        'tenant_id', 'agent_id', 'mois', 'annee',
        'salaire_base', 'jours_travailles', 'jours_ouvrables',
        'retenues_absences', 'cnas', 'irg', 'salaire_net',
        'statut', 'date_paiement', 'fichier_url',
    ];

    protected $casts = [
        'salaire_base'      => 'decimal:2',
        'retenues_absences' => 'decimal:2',
        'cnas'              => 'decimal:2',
        'irg'               => 'decimal:2',
        'salaire_net'       => 'decimal:2',
        'date_paiement'     => 'date',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(PersonnelNonEnseignant::class, 'agent_id');
    }
}
