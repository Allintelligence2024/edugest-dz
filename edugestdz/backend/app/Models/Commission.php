<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends BaseModel
{
    protected $table = 'marketplace_commissions';

    protected $fillable = [
        'tenant_id', 'enseignant_id', 'reservation_id',
        'montant_total', 'taux_commission', 'montant_commission',
        'montant_enseignant', 'statut', 'plan_tenant', 'paye_le',
    ];

    protected $casts = [
        'montant_total'       => 'decimal:2',
        'taux_commission'     => 'decimal:4',
        'montant_commission'  => 'decimal:2',
        'montant_enseignant'  => 'decimal:2',
        'paye_le'             => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
