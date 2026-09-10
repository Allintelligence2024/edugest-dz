<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inscription extends BaseModel
{
    protected $table = 'inscriptions';

    protected $fillable = [
        'tenant_id', 'eleve_id', 'groupe_id', 'annee_scolaire',
        'date_inscription', 'frais_inscription', 'frais_paye',
        'date_debut', 'date_fin', 'statut', 'motif_annulation', 'inscrit_par',
    ];

    protected $casts = [
        'date_inscription' => 'date',
        'date_debut' => 'date',
        'date_fin'   => 'date',
        'frais_inscription' => 'decimal:2',
        'frais_paye' => 'boolean',
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class);
    }
}
