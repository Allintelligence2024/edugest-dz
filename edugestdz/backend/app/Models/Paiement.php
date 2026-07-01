<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paiement extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'paiements';

    protected $fillable = [
        'tenant_id', 'facture_id', 'eleve_id',
        'montant', 'mode_paiement', 'date_paiement', 'notes',
        'statut', 'recu_par',
        // Paiement en ligne
        'reference_trans', 'order_id', 'raw_payload',
        'mode', 'type_paiement',
        // Remboursement
        'rembourse_le', 'motif_remboursement',
    ];

    protected $casts = [
        'date_paiement'  => 'date',
        'montant'        => 'decimal:2',
        'raw_payload'    => 'array',
        'rembourse_le'   => 'datetime',
    ];

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function recuPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recu_par');
    }

    public function getEstEnLigneAttribute(): bool
    {
        return $this->mode === 'en_ligne';
    }

    public function getEstRembourseAttribute(): bool
    {
        return $this->statut === 'remboursé';
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type_paiement ?? $this->mode_paiement) {
            'cib'       => 'CIB (Carte Interbancaire)',
            'dahabia'   => 'Dahabia (Algérie Poste)',
            'baridimob' => 'BaridiMob',
            'cash'      => 'Espèces',
            'virement'  => 'Virement bancaire',
            'cheque'    => 'Chèque',
            default     => ucfirst($this->mode_paiement ?? ''),
        };
    }

    public function scopeEnLigne($query)
    {
        return $query->where('mode', 'en_ligne');
    }

    public function scopeConfirmes($query)
    {
        return $query->where('statut', 'confirmé');
    }
}
