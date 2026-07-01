<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LocalBatiment extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'locaux_batiment';

    protected $fillable = [
        'tenant_id', 'nom', 'type', 'etage',
        'superficie_m2', 'etat_general', 'actif', 'note',
    ];

    protected $casts = [
        'actif'        => 'boolean',
        'superficie_m2'=> 'float',
    ];

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'salle_cours'  => 'Salle de cours',
            'bureau'       => 'Bureau',
            'couloir'      => 'Couloir',
            'cour'         => 'Cour',
            'sanitaires'   => 'Sanitaires',
            'cantine'      => 'Cantine',
            'gymnase'      => 'Gymnase',
            'entree'       => 'Entrée',
            'parking'      => 'Parking',
            'laboratoire'  => 'Laboratoire',
            'bibliotheque' => 'Bibliothèque',
            default        => 'Autre',
        };
    }

    public function getEtatLabelAttribute(): string
    {
        return match ($this->etat_general) {
            'bon'      => 'Bon état',
            'moyen'    => 'État moyen',
            'mauvais'  => 'Mauvais état',
            'critique' => 'État critique',
            default    => ucfirst($this->etat_general),
        };
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(InterventionEntretien::class, 'local_id');
    }

    public function interventionsOuvertes(): HasMany
    {
        return $this->hasMany(InterventionEntretien::class, 'local_id')
            ->whereIn('statut', ['signale', 'en_cours', 'en_attente']);
    }

    public function entretiensPlanifies(): HasMany
    {
        return $this->hasMany(EntretienPreventif::class, 'local_id');
    }

    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }
}
