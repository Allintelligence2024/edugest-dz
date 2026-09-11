<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PointagePersonnel extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'pointage_personnel';

    protected $fillable = [
        'tenant_id', 'agent_id', 'date',
        'heure_arrivee', 'heure_depart',
        'methode', 'badge_uid', 'statut',
        'impact_paie', 'retenue_dzd', 'note',
    ];

    protected $casts = [
        'date'        => 'date',
        'impact_paie' => 'boolean',
        'retenue_dzd' => 'decimal:2',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(PersonnelNonEnseignant::class, 'agent_id');
    }

    public function getDureeTravailleeAttribute(): ?int
    {
        if (!$this->heure_arrivee || !$this->heure_depart) return null;
        $debut = \Carbon\Carbon::createFromTimeString($this->heure_arrivee);
        $fin   = \Carbon\Carbon::createFromTimeString($this->heure_depart);
        return max(0, $debut->diffInMinutes($fin));
    }
}
