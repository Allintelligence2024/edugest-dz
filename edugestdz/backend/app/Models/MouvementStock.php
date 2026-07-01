<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementStock extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'mouvements_stock';

    protected $fillable = [
        'tenant_id', 'article_id', 'type', 'quantite',
        'quantite_avant', 'quantite_apres', 'motif',
        'reference_doc', 'saisie_par', 'date_mouvement',
    ];

    protected $casts = [
        'date_mouvement' => 'date',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(ArticleStock::class, 'article_id');
    }

    public function saisie(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisie_par');
    }
}
