<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrancheFractionnement extends BaseModel
{
    protected $table = 'tranches_fractionnement';
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'numero',
        'montant',
        'date_echeance',
        'statut',
        'montant_paye',
        'date_paiement',
        'notes',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'montant_paye' => 'decimal:2',
        'date_echeance' => 'date',
        'date_paiement' => 'date',
        'numero' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanFractionnement::class, 'plan_id');
    }
}
