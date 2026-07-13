<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends BaseModel
{
    protected $table = 'notes';

    protected $fillable = [
        'tenant_id', 'evaluation_id', 'eleve_id',
        'note', 'absent', 'appreciation', 'commentaire', 'saisie_par',
    ];

    protected $casts = [
        'note' => 'decimal:2',
        'absent' => 'boolean',
    ];

    // ── Relations ──

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    // ── Scopes ──

    public function scopeParEleve(Builder $query, string $eleveId): Builder
    {
        return $query->where('eleve_id', $eleveId);
    }

    public function scopeParEvaluation(Builder $query, string $evaluationId): Builder
    {
        return $query->where('evaluation_id', $evaluationId);
    }

    public function scopeAbsents(Builder $query): Builder
    {
        return $query->where('absent', true);
    }

    public function scopeAvecNote(Builder $query): Builder
    {
        return $query->where('absent', false)->whereNotNull('note');
    }

    public function scopeParTrimestre(Builder $query, string $trimestre): Builder
    {
        return $query->whereHas('evaluation', fn($q) => $q->where('trimestre', $trimestre));
    }

    public function scopeParMatiere(Builder $query, string $matiereId): Builder
    {
        return $query->whereHas('evaluation', fn($q) => $q->where('matiere_id', $matiereId));
    }

    public function scopeMoyenne(Builder $query): Builder
    {
        return $query->where('absent', false)->whereNotNull('note');
    }
}
