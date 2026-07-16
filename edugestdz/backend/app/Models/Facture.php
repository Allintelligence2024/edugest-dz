<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Facture extends BaseModel
{
    protected $table = 'factures';

    protected $fillable = [
        'tenant_id', 'numero_facture', 'eleve_id',
        'mois', 'annee', 'date_emission', 'date_echeance',
        'sous_total', 'remise_pct', 'remise_montant',
        'total_ttc', 'fichier_url', 'notes', 'statut', 'created_by',
    ];

    protected $casts = [
        'date_emission' => 'date',
        'date_echeance' => 'date',
        'sous_total'    => 'decimal:2',
        'remise_montant'=> 'decimal:2',
        'total_ttc'     => 'decimal:2',
    ];

    // ── Relations ──

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneFacture::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    // ── Scopes ──

    public function scopeParEleve(Builder $query, string $eleveId): Builder
    {
        return $query->where('eleve_id', $eleveId);
    }

    public function scopeParAnnee(Builder $query, int $annee): Builder
    {
        return $query->where('annee', $annee);
    }

    public function scopeParMois(Builder $query, int $mois): Builder
    {
        return $query->where('mois', $mois);
    }

    public function scopeEnCours(Builder $query): Builder
    {
        return $query->whereIn('statut', ['émise', 'envoyée']);
    }

    public function scopePayees(Builder $query): Builder
    {
        return $query->where('statut', 'payée');
    }

    public function scopeImpayees(Builder $query): Builder
    {
        return $query->where('statut', 'impayée');
    }

    public function scopeEcheanceProche(Builder $query, int $jours = 7): Builder
    {
        return $query->where('date_echeance', '<=', now()->addDays($jours))
                     ->where('statut', '!=', 'payée');
    }

    public function scopeImpayes(Builder $query): Builder
    {
        return $query->whereIn('statut', ['émise', 'en_retard', 'partiellement_payée']);
    }

    public function scopeEnRetard(Builder $query): Builder
    {
        return $query->where('statut', 'en_retard');
    }

    public function scopeDuMois(Builder $query, int $mois, int $annee): Builder
    {
        return $query->where('mois', $mois)->where('annee', $annee);
    }

    public function scopeEcheanceDepassee(Builder $query): Builder
    {
        return $query->where('date_echeance', '<', today())
                     ->where('statut', '!=', 'payée');
    }

    // ── Accessors / Méthodes métier ──

    public function estImpayee(): bool
    {
        return in_array($this->statut, ['émise', 'en_retard', 'partiellement_payée']);
    }

    public function soldeDu(): float
    {
        $totalPaye = $this->paiements()
            ->where('statut', 'confirmé')
            ->sum('montant');

        return (float) $this->total_ttc - (float) $totalPaye;
    }

    public function joursRetard(): int
    {
        if (!$this->date_echeance || $this->statut === 'payée') {
            return 0;
        }

        return max(0, today()->diffInDays($this->date_echeance));
    }
}
