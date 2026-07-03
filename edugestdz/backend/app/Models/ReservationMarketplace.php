<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReservationMarketplace extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'reservations_marketplace';

    protected $fillable = [
        'offre_id', 'parent_id', 'eleve_id', 'tenant_id',
        'date_souhaitee', 'duree_minutes', 'type', 'statut',
        'montant', 'statut_paiement', 'paiement_id',
        'message_parent', 'reponse_centre', 'confirme_le',
    ];

    protected $casts = [
        'date_souhaitee' => 'datetime',
        'confirme_le'    => 'datetime',
        'montant'        => 'decimal:2',
    ];

    public function offre()
    {
        return $this->belongsTo(OffreCours::class, 'offre_id');
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function eleve()
    {
        return $this->belongsTo(Eleve::class, 'eleve_id');
    }

    public function avis()
    {
        return $this->hasOne(AvisMarketplace::class, 'reservation_id');
    }

    public function peutEtreAnnule(): bool
    {
        return in_array($this->statut, ['en_attente', 'confirmee'])
            && $this->date_souhaitee->isFuture();
    }
}
