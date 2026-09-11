<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OffreCours extends Model
{
    use HasUuids, SoftDeletes, HasFactory;

    protected $table = 'offres_cours';

    protected $fillable = [
        'tenant_id', 'enseignant_id', 'titre', 'description',
        'matiere', 'niveaux', 'type', 'tarif_heure', 'duree_seance',
        'nb_places_max', 'essai_gratuit', 'active',
    ];

    protected $casts = [
        'niveaux'      => 'array',
        'tarif_heure'  => 'decimal:2',
        'essai_gratuit'=> 'boolean',
        'active'       => 'boolean',
    ];

    public function reservations()
    {
        return $this->hasMany(ReservationMarketplace::class, 'offre_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
