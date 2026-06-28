<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasOne};

class Reservation extends BaseModel
{
    protected $table = 'reservations';

    protected $fillable = [
        'tenant_id', 'offre_id', 'eleve_id', 'statut',
        'montant', 'commission', 'mode_paiement',
        'paiement_en_ligne_id', 'message', 'date_debut',
        'date_fin', 'lien_visio',
    ];

    protected $casts = [
        'montant'    => 'decimal:2',
        'commission' => 'decimal:2',
        'date_debut' => 'date',
        'date_fin'   => 'date',
    ];

    public function offre(): BelongsTo
    {
        return $this->belongsTo(OffrePublique::class, 'offre_id');
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function paiementEnLigne(): BelongsTo
    {
        return $this->belongsTo(Paiement::class, 'paiement_en_ligne_id');
    }

    public function avis(): HasOne
    {
        return $this->hasOne(Avis::class);
    }
}
