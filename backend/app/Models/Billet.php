<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Billet extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'eleve_id', 'type', 'date_billet',
        'heure', 'motif', 'parent_prevenu', 'etabli_par',
        'fichier_url', 'note',
    ];

    protected $casts = [
        'date_billet'    => 'date',
        'parent_prevenu' => 'boolean',
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function etabliPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'etabli_par');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'retard'                => 'Billet de Retard',
            'sortie_autorisee'      => 'Autorisation de Sortie',
            'convocation'           => 'Convocation Parent',
            'entree_exceptionnelle' => 'Entrée Exceptionnelle',
            default                 => 'Billet',
        };
    }
}
