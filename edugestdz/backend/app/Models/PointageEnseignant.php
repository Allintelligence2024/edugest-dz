<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PointageEnseignant extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'pointage_enseignants';

    protected $fillable = [
        'tenant_id',
        'enseignant_id',
        'date',
        'heure_arrivee',
        'heure_depart',
        'methode',
        'badge_uid',
        'statut',
        'notif_eleves_envoye',
        'impact_paie',
        'retenue_dzd',
        'note',
    ];

    protected $casts = [
        'date'                 => 'date',
        'notif_eleves_envoye'  => 'boolean',
        'impact_paie'          => 'boolean',
        'retenue_dzd'          => 'decimal:2',
    ];

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }

    public function getDureeTravailleeAttribute(): ?int
    {
        if (!$this->heure_arrivee || !$this->heure_depart) return null;
        $debut = \Carbon\Carbon::createFromTimeString($this->heure_arrivee);
        $fin   = \Carbon\Carbon::createFromTimeString($this->heure_depart);
        return $debut->diffInMinutes($fin);
    }
}
