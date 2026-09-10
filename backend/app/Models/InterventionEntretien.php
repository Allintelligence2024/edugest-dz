<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InterventionEntretien extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'interventions_entretien';

    protected $fillable = [
        'tenant_id', 'local_id', 'prestataire_id', 'titre', 'description',
        'type', 'priorite', 'statut', 'date_signalement',
        'date_debut_intervention', 'date_resolution', 'date_entretien_suivant',
        'cout_estime', 'cout_reel', 'depense_id',
        'photos_avant', 'photos_apres',
        'signale_par', 'assigne_a', 'rapport_intervention',
    ];

    protected $casts = [
        'date_signalement'         => 'date',
        'date_debut_intervention'  => 'date',
        'date_resolution'          => 'date',
        'date_entretien_suivant'   => 'date',
        'cout_estime'              => 'decimal:2',
        'cout_reel'                => 'decimal:2',
        'photos_avant'             => 'array',
        'photos_apres'             => 'array',
    ];

    public function local(): BelongsTo
    {
        return $this->belongsTo(LocalBatiment::class, 'local_id');
    }

    public function prestataire(): BelongsTo
    {
        return $this->belongsTo(PrestatireEntretien::class, 'prestataire_id');
    }

    public function depense(): BelongsTo
    {
        return $this->belongsTo(Depense::class, 'depense_id');
    }

    public function signalePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signale_par');
    }

    public function getPrioriteLabelAttribute(): string
    {
        return match ($this->priorite) {
            'urgente' => 'Urgente',
            'haute'   => 'Haute',
            'normale' => 'Normale',
            'basse'   => 'Basse',
            default   => $this->priorite,
        };
    }

    public function getStatutLabelAttribute(): string
    {
        return match ($this->statut) {
            'signale'    => 'Signalé',
            'en_cours'   => 'En cours',
            'en_attente' => 'En attente',
            'resolu'     => 'Résolu',
            'annule'     => 'Annulé',
            default      => ucfirst($this->statut),
        };
    }

    public function getDureeJoursAttribute(): ?int
    {
        if (!$this->date_signalement) return null;
        $fin = $this->date_resolution ?? today();
        return $this->date_signalement->diffInDays($fin);
    }

    public function scopeOuverts($query)
    {
        return $query->whereIn('statut', ['signale', 'en_cours', 'en_attente']);
    }

    public function scopePriorite($query, string $priorite)
    {
        return $query->where('priorite', $priorite);
    }
}
