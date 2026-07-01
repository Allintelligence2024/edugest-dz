<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrestatireEntretien extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'prestataires_entretien';

    protected $fillable = [
        'tenant_id', 'nom', 'specialite', 'telephone',
        'email', 'adresse', 'actif', 'note',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function getSpecialiteLabelAttribute(): string
    {
        return match ($this->specialite) {
            'plomberie'    => 'Plomberie',
            'electricite'  => 'Électricité',
            'peinture'     => 'Peinture',
            'climatisation'=> 'Climatisation',
            'menuiserie'   => 'Menuiserie',
            'maconnerie'   => 'Maçonnerie',
            'nettoyage'    => 'Nettoyage',
            'informatique' => 'Informatique',
            'jardinage'    => 'Jardinage',
            'securite'     => 'Sécurité',
            'general'      => 'Général',
            default        => 'Autre',
        };
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(InterventionEntretien::class, 'prestataire_id');
    }
}
