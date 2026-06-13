<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, BelongsToMany};

class Groupe extends BaseModel
{
    protected $table = 'groupes';

    protected $fillable = [
        'tenant_id', 'matiere_id', 'nom', 'niveau_scolaire',
        'capacite_max', 'statut', 'description',
    ];

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function cours(): HasMany
    {
        return $this->hasMany(Cours::class);
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    public function eleves(): BelongsToMany
    {
        return $this->belongsToMany(Eleve::class, 'inscriptions')
                    ->withPivot('date_inscription', 'statut');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }
}
