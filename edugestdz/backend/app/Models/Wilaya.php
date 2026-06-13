<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wilaya extends Model
{
    protected $table = 'wilayas';
    public $timestamps = false;

    protected $fillable = ['id', 'code', 'nom_fr', 'nom_ar'];

    public function communes()
    {
        return $this->hasMany(Commune::class);
    }
}
