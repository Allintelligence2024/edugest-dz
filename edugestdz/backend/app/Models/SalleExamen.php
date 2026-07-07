<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SalleExamen extends Model
{
    use HasUuids;

    protected $table = 'salles_examen';

    protected $fillable = [
        'session_id', 'tenant_id', 'nom', 'numero', 'batiment', 'etage',
        'capacite_totale', 'nb_candidats_affectes', 'nb_rangees', 'nb_colonnes',
        'climatisee', 'accessible_pmr',
    ];

    protected $casts = [
        'climatisee'    => 'boolean',
        'accessible_pmr'=> 'boolean',
    ];

    public function session()    { return $this->belongsTo(SessionExamen::class,  'session_id'); }
    public function candidats()  { return $this->hasMany(CandidatExamen::class,   'salle_id'); }
    public function surveillants(){ return $this->hasMany(SurveiillantExamen::class,'salle_id'); }

    public function getPlacesRestantesAttribute(): int
    {
        return max(0, $this->capacite_totale - $this->nb_candidats_affectes);
    }
}
