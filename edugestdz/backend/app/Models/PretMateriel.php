<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PretMateriel extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'prets_materiel';

    protected $fillable = [
        'tenant_id', 'article_id', 'emprunteur_id', 'type_emprunteur',
        'nom_emprunteur', 'quantite', 'date_pret',
        'date_retour_prevue', 'date_retour_effective', 'statut', 'note',
    ];

    protected $casts = [
        'date_pret'              => 'date',
        'date_retour_prevue'     => 'date',
        'date_retour_effective'  => 'date',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(ArticleStock::class, 'article_id');
    }

    public function getEnRetardAttribute(): bool
    {
        return $this->statut === 'en_cours'
            && $this->date_retour_prevue
            && $this->date_retour_prevue->isPast();
    }
}
