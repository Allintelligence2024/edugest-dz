<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Seance extends BaseModel
{
    protected $table = 'seances';

    protected $fillable = [
        'tenant_id', 'cours_id', 'date_seance',
        'heure_debut', 'heure_fin',
        'statut', 'motif_annulation',
    ];

    protected $casts = [
        'date_seance' => 'date',
    ];

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class);
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }
}
