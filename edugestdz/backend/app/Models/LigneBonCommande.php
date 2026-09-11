<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneBonCommande extends BaseModel
{
    protected $table = 'lignes_bon_commande';

    protected $fillable = [
        'bon_commande_id', 'article_id', 'designation',
        'quantite', 'prix_unitaire', 'total',
    ];

    protected $casts = [
        'prix_unitaire' => 'decimal:2',
        'total'         => 'decimal:2',
    ];

    public function bonCommande(): BelongsTo
    {
        return $this->belongsTo(BonCommande::class, 'bon_commande_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(ArticleStock::class, 'article_id');
    }
}
