<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LmsProgression extends Model
{
    use HasUuids;
    protected $table = 'lms_progression';
    protected $fillable = [
        'inscription_id', 'lecon_id', 'eleve_id',
        'completee', 'temps_passe_secondes', 'nb_vues', 'completee_le',
    ];
    protected $casts = ['completee' => 'boolean', 'completee_le' => 'datetime'];

    public function lecon()       { return $this->belongsTo(LmsLecon::class, 'lecon_id'); }
    public function inscription() { return $this->belongsTo(LmsInscription::class, 'inscription_id'); }
}
