<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Emprunt extends BaseModel
{
    protected $table = 'emprunts_bibliotheque';

    protected $fillable = [
        'tenant_id', 'livre_id', 'emprunteur_id', 'type_emprunteur',
        'nom_emprunteur', 'date_emprunt', 'date_retour_prevue',
        'date_retour_effective', 'statut', 'amende', 'note',
    ];

    protected $casts = [
        'date_emprunt'          => 'date',
        'date_retour_prevue'    => 'date',
        'date_retour_effective' => 'date',
        'amende'                => 'decimal:2',
    ];

    public function livre(): BelongsTo
    {
        return $this->belongsTo(Livre::class, 'livre_id');
    }

    public function estEnRetard(): bool
    {
        return $this->statut === 'en_cours'
            && $this->date_retour_prevue
            && $this->date_retour_prevue->isPast();
    }
}
