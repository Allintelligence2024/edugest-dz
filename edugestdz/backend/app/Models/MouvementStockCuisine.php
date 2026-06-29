<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementStockCuisine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'mouvements_stock_cuisine';

    protected $fillable = [
        'tenant_id', 'article_id', 'type', 'quantite',
        'motif', 'saisie_par', 'date_mouvement',
    ];

    protected $casts = [
        'date_mouvement' => 'date',
        'quantite'       => 'decimal:3',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(StockCuisine::class, 'article_id');
    }
}
