<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AvisMarketplace extends Model
{
    use HasUuids;

    protected $table = 'avis_marketplace';

    protected $fillable = [
        'tenant_id', 'parent_id', 'reservation_id',
        'note', 'titre', 'commentaire', 'visible', 'verifie', 'publie_le',
    ];

    protected $casts = [
        'note'      => 'integer',
        'visible'   => 'boolean',
        'verifie'   => 'boolean',
        'publie_le' => 'datetime',
    ];

    public function reservation()
    {
        return $this->belongsTo(ReservationMarketplace::class, 'reservation_id');
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    protected static function booted(): void
    {
        static::saved(function (AvisMarketplace $avis) {
            optional(ProfilMarketplace::where('tenant_id', $avis->tenant_id)->first())
                ->recalculerNote();
        });
    }
}
