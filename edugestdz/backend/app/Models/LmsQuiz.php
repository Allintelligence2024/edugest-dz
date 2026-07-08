<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LmsQuiz extends Model
{
    use HasUuids;
    protected $table = 'lms_quiz';
    protected $fillable = [
        'lecon_id', 'titre', 'nb_questions', 'duree_minutes',
        'seuil_reussite', 'nb_tentatives_max', 'correction_immediate', 'ordre_aleatoire',
    ];
    protected $casts = ['correction_immediate' => 'boolean', 'ordre_aleatoire' => 'boolean'];

    public function lecon()     { return $this->belongsTo(LmsLecon::class, 'lecon_id'); }
    public function questions() { return $this->hasMany(LmsQuestion::class, 'quiz_id')->orderBy('ordre'); }
    public function tentatives(){ return $this->hasMany(LmsTentativeQuiz::class, 'quiz_id'); }
}
