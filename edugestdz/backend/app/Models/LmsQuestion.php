<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LmsQuestion extends Model
{
    use HasUuids;
    protected $table = 'lms_questions';
    protected $fillable = ['quiz_id', 'type', 'enonce', 'options', 'explication', 'points', 'ordre'];
    protected $casts = ['options' => 'array'];

    public function quiz() { return $this->belongsTo(LmsQuiz::class, 'quiz_id'); }

    public function verifierReponse(string $reponseId): bool
    {
        if ($this->type === 'qcm') {
            $option = collect($this->options)->firstWhere('id', $reponseId);
            return (bool) ($option['correct'] ?? false);
        }
        if ($this->type === 'vrai_faux') {
            return (string) $reponseId === (string) ($this->options['bonne_reponse'] ?? '');
        }
        return false;
    }
}
