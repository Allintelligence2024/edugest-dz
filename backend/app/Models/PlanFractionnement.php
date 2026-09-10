<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFractionnement extends BaseModel
{
    protected $table = 'plans_fractionnement';

    protected $fillable = [
        'tenant_id',
        'facture_id',
        'eleve_id',
        'nb_tranches',
        'montant_total',
        'statut',
        'notes',
    ];

    protected $casts = [
        'montant_total' => 'decimal:2',
        'nb_tranches' => 'integer',
    ];

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function tranches(): HasMany
    {
        return $this->hasMany(TrancheFractionnement::class, 'plan_id');
    }
}
