<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArretBus extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'arrets_bus';

    protected $fillable = [
        'tenant_id', 'circuit_id', 'nom', 'adresse',
        'wilaya', 'ordre', 'heure_matin', 'heure_soir', 'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function circuit(): BelongsTo
    {
        return $this->belongsTo(CircuitTransport::class, 'circuit_id');
    }

    public function elevesInscrits(): HasMany
    {
        return $this->hasMany(TransportEleve::class, 'arret_id')->where('actif', true);
    }
}
