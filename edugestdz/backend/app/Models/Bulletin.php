<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bulletin extends BaseModel
{
    protected $table = 'bulletins';

    protected $fillable = [
        'tenant_id', 'eleve_id', 'groupe_id',
        'trimestre', 'annee_scolaire',
        'moyenne_generale', 'rang', 'effectif_classe',
        'appreciation_gen', 'fichier_url',
        'genere_le', 'genere_par',
    ];

    protected $casts = [
        'moyenne_generale' => 'decimal:2',
        'genere_le' => 'datetime',
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
