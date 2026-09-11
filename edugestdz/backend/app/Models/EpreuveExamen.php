<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class EpreuveExamen extends Model
{
    use HasUuids;

    protected $table = 'epreuves_examen';

    protected $fillable = [
        'session_id', 'matiere', 'code_matiere', 'coefficient',
        'date_epreuve', 'moment', 'heure_debut', 'heure_fin',
        'duree_minutes', 'type_epreuve', 'calculatrice_autorisee', 'documents_autorises',
    ];

    protected $casts = [
        'date_epreuve'           => 'date',
        'coefficient'            => 'decimal:1',
        'calculatrice_autorisee' => 'boolean',
        'documents_autorises'    => 'boolean',
    ];

    public function session() { return $this->belongsTo(SessionExamen::class, 'session_id'); }
}
