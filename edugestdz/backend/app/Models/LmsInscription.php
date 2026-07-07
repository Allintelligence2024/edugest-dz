<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LmsInscription extends Model
{
    use HasUuids;
    protected $table = 'lms_inscriptions';
    protected $fillable = [
        'cours_id', 'eleve_id', 'tenant_id', 'statut',
        'progression_pct', 'nb_lecons_completees', 'temps_total_minutes',
        'derniere_activite', 'complete_le', 'certificat_url',
    ];
    protected $casts = ['derniere_activite' => 'datetime', 'complete_le' => 'datetime'];

    public function cours()      { return $this->belongsTo(LmsCours::class, 'cours_id'); }
    public function eleve()      { return $this->belongsTo(Eleve::class, 'eleve_id'); }
    public function progressions(){ return $this->hasMany(LmsProgression::class, 'inscription_id'); }
}
