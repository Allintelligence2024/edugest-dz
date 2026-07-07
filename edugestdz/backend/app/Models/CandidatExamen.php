<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CandidatExamen extends Model
{
    use HasUuids;

    protected $table = 'candidats_examen';

    protected $fillable = [
        'session_id', 'tenant_id', 'eleve_id',
        'nom', 'prenom', 'date_naissance', 'lieu_naissance',
        'numero_inscription', 'type_candidat', 'filiere',
        'salle_id', 'numero_place', 'rangee', 'colonne',
        'convocation_imprimee', 'present', 'present_marque_le',
        'besoins_speciaux', 'notes_speciaux',
    ];

    protected $casts = [
        'date_naissance'      => 'date',
        'convocation_imprimee'=> 'boolean',
        'present'             => 'boolean',
        'besoins_speciaux'    => 'boolean',
        'present_marque_le'   => 'datetime',
    ];

    public function session() { return $this->belongsTo(SessionExamen::class, 'session_id'); }
    public function salle()   { return $this->belongsTo(SalleExamen::class,   'salle_id'); }
    public function eleve()   { return $this->belongsTo(Eleve::class,         'eleve_id'); }

    public function getNomCompletAttribute(): string
    {
        return "{$this->nom} {$this->prenom}";
    }
}
