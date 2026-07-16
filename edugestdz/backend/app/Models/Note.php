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

    public function scopeValides(Builder $query): Builder
    {
        return $query->whereNotNull('note');
    }

    public function scopePourEleve(Builder $query, string $eleveId): Builder
    {
        return $query->where('eleve_id', $eleveId);
    }

    public function scopeDuTrimestre(Builder $query, string $trimestre): Builder
    {
        return $query->whereHas('evaluation', fn($q) => $q->where('trimestre', $trimestre));
    }

    public function scopeAuDessus(Builder $query, float $seuil = 10.0): Builder
    {
        return $query->where('note', '>=', $seuil);
    }

    public function scopeEnDifficulte(Builder $query, float $seuil = 10.0): Builder
    {
        return $query->where('note', '<', $seuil)->whereNotNull('note');
    }

    /**
     * Calculer la moyenne pondérée d'un élève (optionnel: pour un trimestre donné).
     */
    public static function moyenneEleve(string $eleveId, ?string $trimestre = null): float
    {
        $query = static::where('eleve_id', $eleveId)
            ->whereNotNull('note')
            ->where('absent', false)
            ->with('evaluation');

        if ($trimestre) {
            $query->whereHas('evaluation', fn($q) => $q->where('trimestre', $trimestre));
        }

        $notes = $query->get();

        if ($notes->isEmpty()) return 0.0;

        $totalPondere = $notes->sum(fn($n) => $n->note * ($n->evaluation->coefficient ?? 1));
        $totalCoeff   = $notes->sum(fn($n) => $n->evaluation->coefficient ?? 1);

        return $totalCoeff > 0 ? round($totalPondere / $totalCoeff, 2) : 0.0;
    }
}
