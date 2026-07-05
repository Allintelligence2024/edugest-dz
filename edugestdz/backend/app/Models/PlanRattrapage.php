<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PlanRattrapage extends Model
{
    use HasUuids;

    protected $table = 'plans_rattrapage';

    protected $fillable = [
        'tenant_id', 'eleve_id', 'enseignant_id', 'matiere',
        'objectifs', 'programme', 'date_debut', 'date_fin',
        'statut', 'resultat', 'cree_par',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin'   => 'date',
    ];

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function enseignant()
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    public function createur()
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}
