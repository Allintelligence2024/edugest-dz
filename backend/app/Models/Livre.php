<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Livre extends BaseModel
{
    protected $table = 'livres_bibliotheque';

    protected $fillable = [
        'tenant_id', 'titre', 'auteur', 'isbn', 'editeur',
        'annee_edition', 'categorie', 'description', 'photo_url',
        'nb_exemplaires', 'nb_disponibles', 'code_barre',
        'emplacement', 'statut',
    ];

    protected $casts = [
        'annee_edition'  => 'integer',
        'nb_exemplaires' => 'integer',
        'nb_disponibles' => 'integer',
    ];

    public function emprunts(): HasMany
    {
        return $this->hasMany(Emprunt::class, 'livre_id');
    }

    public function estDisponible(): bool
    {
        return $this->nb_disponibles > 0 && $this->statut === 'actif';
    }

    public function getEstDisponibleAttribute(): bool
    {
        return $this->estDisponible();
    }
}
