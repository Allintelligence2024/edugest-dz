<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SurveiillantExamen extends Model
{
    use HasUuids;

    protected $table = 'surveillants_examen';

    protected $fillable = [
        'session_id', 'tenant_id', 'user_id',
        'nom', 'prenom', 'specialite', 'commune_origine', 'role',
        'salle_id', 'salle_nom', 'disponible',
        'convocation_imprimee', 'motif_exemption',
    ];

    protected $casts = [
        'disponible'           => 'boolean',
        'convocation_imprimee' => 'boolean',
    ];

    public const ROLES = [
        'chef_centre' => 'Chef de Centre',
        'surveillant' => 'Surveillant',
        'secretaire'  => 'Secrétaire',
        'observateur' => 'Observateur',
    ];

    public function session() { return $this->belongsTo(SessionExamen::class, 'session_id'); }
    public function salle()   { return $this->belongsTo(SalleExamen::class,   'salle_id'); }
    public function user()    { return $this->belongsTo(User::class,          'user_id'); }

    public function getNomCompletAttribute(): string
    {
        return "{$this->nom} {$this->prenom}";
    }
}
