<?php

namespace App\Models;

class CampagneDestinataire extends BaseModel
{
    protected $table = 'campagne_destinataires';

    protected $fillable = [
        'campagne_id', 'destinataire_id', 'canal', 'statut', 'erreur', 'envoye_le',
    ];

    protected $casts = [
        'envoye_le' => 'datetime',
    ];

    public function campagne()
    {
        return $this->belongsTo(Campagne::class);
    }
}
