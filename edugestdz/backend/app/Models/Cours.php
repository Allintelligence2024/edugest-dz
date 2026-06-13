<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Cours extends BaseModel
{
    protected $table = 'cours';

    protected $fillable = [
        'tenant_id', 'enseignant_id', 'matiere_id', 'groupe_id', 'salle_id',
        'jour_semaine', 'heure_debut', 'heure_fin', 'type_cours',
        'recurrence', 'date_debut', 'date_fin', 'tarif_seance', 'statut',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin'   => 'date',
    ];

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }

    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class);
    }

    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class);
    }

    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class);
    }
}
