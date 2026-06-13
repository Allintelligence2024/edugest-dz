<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Facture extends BaseModel
{
    protected $table = 'factures';

    protected $fillable = [
        'tenant_id', 'numero_facture', 'eleve_id',
        'mois', 'annee', 'date_emission', 'date_echeance',
        'sous_total', 'remise_pct', 'remise_montant',
        'total_ttc', 'fichier_url', 'notes', 'statut', 'created_by',
    ];

    protected $casts = [
        'date_emission' => 'date',
        'date_echeance' => 'date',
        'sous_total'    => 'decimal:2',
        'remise_montant'=> 'decimal:2',
        'total_ttc'     => 'decimal:2',
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneFacture::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }
}
