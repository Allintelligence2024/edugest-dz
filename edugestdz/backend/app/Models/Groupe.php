<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, BelongsToMany};

class Groupe extends BaseModel
{
    protected $table = 'groupes';

    protected $fillable = [
        'tenant_id', 'matiere_id', 'enseignant_id', 'nom', 'niveau_scolaire',
        'capacite_max', 'statut', 'description',
    ];

    // ── Relations ──

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
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

    // ── Scopes ──

    public function scopeParNiveau(Builder $query, string $niveau): Builder
    {
        return $query->where('niveau_scolaire', $niveau);
    }

    public function scopeActifs(Builder $query): Builder
    {
        return $query->where('statut', 'actif');
    }

    public function scopeParEnseignant(Builder $query, string $enseignantId): Builder
    {
        return $query->where('enseignant_id', $enseignantId);
    }

    public function scopeParMatiere(Builder $query, string $matiereId): Builder
    {
        return $query->where('matiere_id', $matiereId);
    }
}
