<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commune extends Model
{
    protected $table = 'communes';
    public $timestamps = false;

    protected $fillable = ['id', 'wilaya_id', 'code_postal', 'nom_fr', 'nom_ar'];

    public function wilaya()
    {
        return $this->belongsTo(Wilaya::class);
    }
}
