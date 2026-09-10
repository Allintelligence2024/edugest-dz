<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntretienPreventif extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'entretiens_preventifs';

    protected $fillable = [
        'tenant_id', 'local_id', 'prestataire_id', 'nom', 'description',
        'frequence', 'prochaine_echeance', 'derniere_realisation',
        'cout_estime', 'actif',
    ];

    protected $casts = [
        'prochaine_echeance'    => 'date',
        'derniere_realisation'  => 'date',
        'cout_estime'           => 'decimal:2',
        'actif'                 => 'boolean',
    ];

    public function local(): BelongsTo
    {
        return $this->belongsTo(LocalBatiment::class, 'local_id');
    }

    public function prestataire(): BelongsTo
    {
        return $this->belongsTo(PrestatireEntretien::class, 'prestataire_id');
    }

    public function getEnRetardAttribute(): bool
    {
        return $this->actif
            && $this->prochaine_echeance
            && $this->prochaine_echeance->isPast();
    }

    public function getJoursAvantEcheanceAttribute(): int
    {
        if (!$this->prochaine_echeance) return 999;
        return (int) today()->diffInDays($this->prochaine_echeance, false);
    }
}
