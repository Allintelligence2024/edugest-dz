<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LmsDevoir extends Model
{
    use HasUuids;
    protected $table = 'lms_devoirs';
    protected $fillable = [
        'lecon_id', 'eleve_id', 'inscription_id',
        'fichier_url', 'fichier_nom', 'commentaire_eleve',
        'statut', 'note', 'note_max', 'feedback_enseignant',
        'corrige_par', 'corrige_le', 'soumis_le',
    ];
    protected $casts = ['corrige_le' => 'datetime', 'soumis_le' => 'datetime'];

    public function lecon()  { return $this->belongsTo(LmsLecon::class, 'lecon_id'); }
    public function eleve()  { return $this->belongsTo(Eleve::class, 'eleve_id'); }
}
