<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCuisine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'stock_cuisine';

    protected $fillable = [
        'tenant_id', 'article', 'categorie', 'unite',
        'quantite_stock', 'seuil_alerte', 'prix_unitaire',
        'fournisseur', 'date_peremption', 'note',
    ];

    protected $casts = [
        'quantite_stock'  => 'decimal:3',
        'seuil_alerte'    => 'decimal:3',
        'prix_unitaire'   => 'decimal:2',
        'date_peremption' => 'date',
    ];

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementStockCuisine::class, 'article_id');
    }

    public function getEnAlertAttribute(): bool
    {
        return $this->quantite_stock <= $this->seuil_alerte;
    }

    public function getPerimeSoonAttribute(): bool
    {
        return $this->date_peremption && $this->date_peremption->diffInDays(now()) <= 7
            && !$this->date_peremption->isPast();
    }

    public function scopeEnAlerte($query)
    {
        return $query->whereColumn('quantite_stock', '<=', 'seuil_alerte');
    }
}
