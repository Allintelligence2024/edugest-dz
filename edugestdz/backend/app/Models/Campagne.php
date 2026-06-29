<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Campagne extends BaseModel
{
    protected $table = 'campagnes';

    protected $fillable = [
        'tenant_id', 'titre', 'message', 'canaux', 'filtres',
        'destinataires', 'nb_destinataires', 'nb_envoyes', 'nb_echecs',
        'statut', 'programmee_le', 'envoyee_le', 'cree_par',
    ];

    protected $casts = [
        'canaux'         => 'array',
        'filtres'        => 'array',
        'destinataires'  => 'array',
        'programmee_le'  => 'datetime',
        'envoyee_le'     => 'datetime',
    ];

    public function destinataires(): HasMany
    {
        return $this->hasMany(CampagneDestinataire::class);
    }

    public function lignesDestinataires(): HasMany
    {
        return $this->hasMany(CampagneDestinataire::class);
    }
}
