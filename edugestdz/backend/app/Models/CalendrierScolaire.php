<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendrierScolaire extends Model
{
    protected $table = 'calendrier_scolaire';

    protected $fillable = [
        'annee_scolaire', 'evenement', 'type',
        'date_debut', 'date_fin', 'wilaya_id',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin'   => 'date',
    ];
}
