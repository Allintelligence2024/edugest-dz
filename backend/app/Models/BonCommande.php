<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BonCommande extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'bons_commande';

    protected $fillable = [
        'tenant_id', 'numero', 'fournisseur', 'fournisseur_contact',
        'date_commande', 'date_livraison_prevue', 'montant_total',
        'statut', 'note', 'fichier_url',
    ];

    protected $casts = [
        'date_commande'         => 'date',
        'date_livraison_prevue' => 'date',
        'montant_total'         => 'decimal:2',
    ];

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneBonCommande::class, 'bon_commande_id');
    }

    public static function genererNumero(): string
    {
        $annee = now()->year;
        $last  = static::withoutGlobalScope('tenant')
            ->where('numero', 'LIKE', "BC-{$annee}-%")
            ->orderByDesc('numero')->value('numero');
        $seq = $last ? ((int) substr($last, -3)) + 1 : 1;
        return sprintf('BC-%d-%03d', $annee, $seq);
    }
}
