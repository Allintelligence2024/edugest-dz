<?php
// backend/app/Models/Eleve.php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\{
    BelongsToMany, HasMany, BelongsTo
};

class Eleve extends BaseModel
{
    protected $table = 'eleves';

    protected $fillable = [
        'tenant_id', 'user_id', 'numero_inscription',
        'nom', 'prenom', 'nom_ar', 'prenom_ar',
        'date_naissance', 'lieu_naissance', 'sexe',
        'nationalite', 'wilaya_id', 'commune_id',
        'adresse', 'photo_url', 'ecole_origine',
        'niveau_scolaire', 'statut', 'notes_internes', 'qr_code'
    ];

    protected $hidden  = ['deleted_at'];

    protected $casts   = [
        'date_naissance' => 'date',
    ];

    // ── Appender les attributs calculés ──
    protected $appends = ['nom_complet', 'age', 'photo_url_full'];

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // ACCESSEURS
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━

    public function getNomCompletAttribute(): string
    {
        return strtoupper($this->nom) . ' ' . ucfirst($this->prenom);
    }

    public function getAgeAttribute(): int
    {
        return $this->date_naissance?->age ?? 0;
    }

    public function getPhotoUrlFullAttribute(): string
    {
        return $this->photo_url
            ? asset('storage/' . $this->photo_url)
            : asset('images/default-avatar.png');
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // RELATIONS
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wilaya(): BelongsTo
    {
        return $this->belongsTo(Wilaya::class);
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(ParentEleve::class, 'eleve_parent', 'eleve_id', 'parent_id')
                    ->withPivot('est_principal');
    }

    public function parentsPrincipaux(): BelongsToMany
    {
        return $this->belongsToMany(ParentEleve::class, 'eleve_parent', 'eleve_id', 'parent_id')
                    ->withPivot('est_principal')
                    ->wherePivot('est_principal', true);
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    public function groupes(): BelongsToMany
    {
        return $this->belongsToMany(Groupe::class, 'inscriptions')
                    ->withPivot('date_inscription', 'statut')
                    ->wherePivot('statut', 'validée');
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }

    public function bulletins(): HasMany
    {
        return $this->hasMany(Bulletin::class);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // SCOPES
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━

    public function scopeActifs($query)
    {
        return $query->where('statut', 'actif');
    }

    public function scopeNiveau($query, string $niveau)
    {
        return $query->where('niveau_scolaire', $niveau);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('nom', 'ILIKE', "%{$search}%")
              ->orWhere('prenom', 'ILIKE', "%{$search}%")
              ->orWhere('numero_inscription', 'ILIKE', "%{$search}%");
        });
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // MÉTHODES MÉTIER
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━

    public function getMoyenneGenerale(string $trimestre = null): float
    {
        $query = $this->notes()
            ->with('evaluation')
            ->whereNotNull('note');

        if ($trimestre) {
            $query->whereHas('evaluation', fn($q) =>
                $q->where('trimestre', $trimestre)
            );
        }

        $notes = $query->get();

        if ($notes->isEmpty()) return 0;

        $totalPondere = $notes->sum(fn($n) =>
            $n->note * $n->evaluation->coefficient
        );
        $totalCoeff = $notes->sum(fn($n) =>
            $n->evaluation->coefficient
        );

        return $totalCoeff > 0
            ? round($totalPondere / $totalCoeff, 2)
            : 0;
    }

    public function getTauxPresence(): float
    {
        $total = $this->presences()->count();
        if ($total === 0) {
            return 100.0;
        }

        $presences = $this->presences()
                          ->whereIn('statut', ['présent', 'retard'])
                          ->count();

        return round(($presences / $total) * 100, 2);
    }
}
