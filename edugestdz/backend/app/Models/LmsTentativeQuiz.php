<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LmsTentativeQuiz extends Model
{
    use HasUuids;
    protected $table = 'lms_tentatives_quiz';
    protected $fillable = [
        'quiz_id', 'eleve_id', 'inscription_id',
        'score', 'score_max', 'pourcentage', 'reussi',
        'duree_secondes', 'reponses', 'numero_tentative', 'debut_le', 'fin_le',
    ];
    protected $casts = [
        'reponses' => 'array', 'reussi' => 'boolean',
        'debut_le' => 'datetime', 'fin_le' => 'datetime',
    ];

    public function quiz()  { return $this->belongsTo(LmsQuiz::class, 'quiz_id'); }
    public function eleve() { return $this->belongsTo(Eleve::class, 'eleve_id'); }
}
