<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsCours extends Model
{
    use HasUuids, SoftDeletes;
    protected $table = 'lms_cours';
    protected $fillable = [
        'tenant_id', 'enseignant_id', 'titre', 'description', 'matiere',
        'niveaux_cibles', 'langue', 'duree_estimee', 'image_url',
        'publie', 'certificat_actif', 'seuil_completion',
        'nb_chapitres', 'nb_lecons', 'nb_inscrits', 'note_moyenne',
    ];
    protected $casts = [
        'niveaux_cibles'   => 'array',
        'publie'           => 'boolean',
        'certificat_actif' => 'boolean',
    ];

    public function enseignant()    { return $this->belongsTo(User::class, 'enseignant_id'); }
    public function chapitres()     { return $this->hasMany(LmsChapitre::class, 'cours_id')->orderBy('ordre'); }
    public function inscriptions()  { return $this->hasMany(LmsInscription::class, 'cours_id'); }

    public function scopePublie($q) { return $q->where('publie', true); }

    public function getNbLeconsRealAttribute(): int
    {
        return $this->chapitres()->withCount('lecons')->get()->sum('lecons_count');
    }
}
