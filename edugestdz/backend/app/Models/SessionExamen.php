<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SessionExamen extends Model
{
    use HasUuids;

    protected $table = 'sessions_examen';

    protected $fillable = [
        'tenant_id', 'type', 'filiere', 'annee_scolaire', 'session',
        'date_debut', 'date_fin', 'wilaya', 'commune',
        'nom_centre', 'adresse_centre', 'capacite_max',
        'max_candidats_par_salle', 'max_candidats_libres_par_salle',
        'nb_surveillants_par_salle', 'statut', 'notes',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin'   => 'date',
    ];

    public const TYPES = ['BEM' => 'Brevet d\'Enseignement Moyen', 'BAC' => 'Baccalauréat', 'autre' => 'Autre'];

    public const FILIERES_BAC = [
        'sciences'        => 'Sciences de la Nature et de la Vie',
        'maths'           => 'Mathématiques',
        'lettres_langues' => 'Lettres et Langues Étrangères',
        'lettres_philo'   => 'Lettres et Philosophie',
        'gestion'         => 'Gestion et Économie',
        'technique_math'  => 'Technique Mathématique',
        'musique'         => 'Musique',
    ];

    public function epreuves()   { return $this->hasMany(EpreuveExamen::class, 'session_id'); }
    public function salles()     { return $this->hasMany(SalleExamen::class,   'session_id'); }
    public function candidats()  { return $this->hasMany(CandidatExamen::class,'session_id'); }
    public function surveillants(){ return $this->hasMany(SurveiillantExamen::class,'session_id'); }

    public function getNbCandidatsAttribute(): int
    {
        return $this->candidats()->count();
    }

    public function getNbSallesRequiseAttribute(): int
    {
        $max = $this->max_candidats_par_salle ?: 20;
        return (int) ceil($this->getNbCandidatsAttribute() / $max);
    }
}
