<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Salle extends BaseModel
{
    protected $table = 'salles';

    protected $fillable = [
        'tenant_id', 'nom', 'capacite', 'equipements', 'localisation', 'statut',
    ];

    public function cours(): HasMany
    {
        return $this->hasMany(Cours::class);
    }
}
