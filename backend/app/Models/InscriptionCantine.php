<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InscriptionCantine extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'inscriptions_cantine';

    protected $fillable = [
        'tenant_id', 'eleve_id', 'type_abonnement', 'regime',
        'allergies', 'actif', 'date_debut', 'date_fin',
        'tarif_mensuel', 'note',
    ];

    protected $casts = [
        'date_debut'    => 'date',
        'date_fin'      => 'date',
        'actif'         => 'boolean',
        'tarif_mensuel' => 'decimal:2',
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function repas(): HasMany
    {
        return $this->hasMany(RepasJournalier::class, 'eleve_id', 'eleve_id');
    }

    public function isActif(): bool
    {
        if (!$this->actif) return false;
        if ($this->date_fin && $this->date_fin->isPast()) return false;
        return true;
    }

    public function getRegimeLabelAttribute(): string
    {
        return match ($this->regime) {
            'sans_porc'   => 'Sans porc',
            'vegetarien'  => 'Vegetarien',
            'sans_gluten' => 'Sans gluten',
            'autre'       => 'Regime special',
            default       => 'Normal',
        };
    }
}
