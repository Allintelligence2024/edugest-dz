<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paiement extends BaseModel
{
    protected $table = 'paiements';

    protected $fillable = [
        'tenant_id', 'facture_id', 'eleve_id',
        'montant', 'mode_paiement', 'date_paiement',
        'reference_trans', 'recu_url', 'notes',
        'recu_par', 'statut',
    ];

    protected $casts = [
        'date_paiement' => 'date',
        'montant' => 'decimal:2',
    ];

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }
}
