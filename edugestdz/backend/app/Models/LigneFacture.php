<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneFacture extends BaseModel
{
    protected $table = 'lignes_facture';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'facture_id', 'description', 'quantite',
        'prix_unitaire', 'total', 'type_ligne',
    ];

    protected $casts = [
        'quantite'      => 'decimal:2',
        'prix_unitaire' => 'decimal:2',
        'total'         => 'decimal:2',
    ];

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }
}
