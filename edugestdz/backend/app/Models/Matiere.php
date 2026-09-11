<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Matiere extends BaseModel
{
    protected $table = 'matieres';

    protected $fillable = [
        'tenant_id', 'nom_fr', 'nom_ar', 'couleur', 'description', 'statut',
    ];

    public function groupes(): HasMany
    {
        return $this->hasMany(Groupe::class);
    }
}
