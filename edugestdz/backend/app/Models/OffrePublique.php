<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class OffrePublique extends BaseModel
{
    protected $table = 'offres_publiques';

    protected $fillable = [
        'tenant_id', 'enseignant_id', 'type_offre', 'matiere_id',
        'niveau', 'tarif_seance', 'tarif_mensuel', 'type_cours',
        'wilaya_id', 'adresse', 'capacite_max', 'places_restantes',
        'description', 'statut',
    ];

    protected $casts = [
        'tarif_seance'   => 'decimal:2',
        'tarif_mensuel'  => 'decimal:2',
        'places_restantes' => 'integer',
        'capacite_max'   => 'integer',
    ];

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function wilaya(): BelongsTo
    {
        return $this->belongsTo(Wilaya::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'offre_id');
    }
}
