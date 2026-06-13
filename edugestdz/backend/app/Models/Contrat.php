<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contrat extends BaseModel
{
    protected $table = 'contrats';

    protected $fillable = [
        'tenant_id', 'enseignant_id',
        'type_contrat', 'date_debut', 'date_fin',
        'salaire', 'taux_irg', 'taux_cnas', 'taux_casnos',
        'fichier_url', 'statut',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin'   => 'date',
        'salaire'    => 'decimal:2',
    ];

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }
}
