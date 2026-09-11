<?php

namespace App\Models;

use App\Traits\BelongsToTenant;

class BudgetPrevisionnel extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'budget_previsionnel';

    protected $fillable = [
        'tenant_id', 'annee', 'mois',
        'categorie', 'montant_prevu', 'note',
    ];

    protected $casts = [
        'montant_prevu' => 'decimal:2',
    ];

    public static function getPrevision(string $categorie, int $annee, ?int $mois = null): float
    {
        return (float) static::where('categorie', $categorie)
            ->where('annee', $annee)
            ->where('mois', $mois)
            ->value('montant_prevu') ?? 0.0;
    }
}
