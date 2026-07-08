<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LmsLecon extends Model
{
    use HasUuids;
    protected $table = 'lms_lecons';
    protected $fillable = [
        'chapitre_id', 'titre', 'contenu', 'type',
        'ressource_url', 'ressource_nom', 'duree_minutes',
        'ordre', 'gratuite', 'publiee',
    ];
    protected $casts = ['gratuite' => 'boolean', 'publiee' => 'boolean'];

    public const TYPES = [
        'texte'  => ['label' => 'Texte / Cours',    'icon' => '📄'],
        'video'  => ['label' => 'Vidéo',            'icon' => '🎥'],
        'pdf'    => ['label' => 'Document PDF',     'icon' => '📑'],
        'audio'  => ['label' => 'Audio',            'icon' => '🎵'],
        'lien'   => ['label' => 'Lien externe',     'icon' => '🔗'],
        'quiz'   => ['label' => 'Quiz / Exercices', 'icon' => '✏️'],
        'devoir' => ['label' => 'Devoir à rendre',  'icon' => '📝'],
    ];

    public function chapitre()    { return $this->belongsTo(LmsChapitre::class, 'chapitre_id'); }
    public function quiz()        { return $this->hasOne(LmsQuiz::class, 'lecon_id'); }
    public function progressions(){ return $this->hasMany(LmsProgression::class, 'lecon_id'); }
    public function devoirs()     { return $this->hasMany(LmsDevoir::class, 'lecon_id'); }
}
