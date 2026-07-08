<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LmsChapitre extends Model
{
    use HasUuids;
    protected $table = 'lms_chapitres';
    protected $fillable = ['cours_id', 'titre', 'description', 'ordre', 'publie'];
    protected $casts = ['publie' => 'boolean'];

    public function cours()  { return $this->belongsTo(LmsCours::class, 'cours_id'); }
    public function lecons() { return $this->hasMany(LmsLecon::class, 'chapitre_id')->orderBy('ordre'); }
}
