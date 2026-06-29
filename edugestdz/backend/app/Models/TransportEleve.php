<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransportEleve extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'transport_eleves';

    protected $fillable = [
        'tenant_id', 'eleve_id', 'circuit_id', 'arret_id',
        'abonnement', 'date_debut', 'date_fin',
        'actif', 'tarif_mensuel_applique', 'note',
    ];

    protected $casts = [
        'date_debut'             => 'date',
        'date_fin'               => 'date',
        'actif'                  => 'boolean',
        'tarif_mensuel_applique' => 'decimal:2',
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function circuit(): BelongsTo
    {
        return $this->belongsTo(CircuitTransport::class, 'circuit_id');
    }

    public function arret(): BelongsTo
    {
        return $this->belongsTo(ArretBus::class, 'arret_id');
    }

    public function isActif(): bool
    {
        if (!$this->actif) return false;
        if ($this->date_fin && $this->date_fin->isPast()) return false;
        return true;
    }
}
