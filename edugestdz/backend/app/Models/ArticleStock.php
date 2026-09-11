<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ArticleStock extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'articles_stock';

    protected $fillable = [
        'tenant_id', 'nom', 'reference', 'qr_code', 'categorie',
        'unite', 'salle_id', 'localisation', 'quantite_stock',
        'quantite_minimum', 'etat', 'valeur_unitaire', 'date_acquisition',
        'fournisseur', 'numero_serie', 'est_immobilise', 'actif', 'note',
    ];

    protected $casts = [
        'date_acquisition' => 'date',
        'valeur_unitaire'  => 'decimal:2',
        'est_immobilise'   => 'boolean',
        'actif'            => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (!$model->qr_code) {
                $model->qr_code = 'ART-' . strtoupper(Str::random(8));
            }
            if (!$model->reference) {
                $prefix = match ($model->categorie) {
                    'mobilier'                => 'MOB',
                    'equipement_pedagogique'  => 'PED',
                    'fourniture_bureau'       => 'FBU',
                    'fourniture_pedagogique'  => 'FPE',
                    'equipement_informatique' => 'INF',
                    default                   => 'ART',
                };
                $model->reference = $prefix . '-' . now()->year . '-' . str_pad(
                    static::withoutGlobalScope('tenant')->count() + 1, 4, '0', STR_PAD_LEFT
                );
            }
        });
    }

    public function getEnAlerteAttribute(): bool
    {
        return $this->quantite_stock <= $this->quantite_minimum;
    }

    public function getEtatLabelAttribute(): string
    {
        return match ($this->etat) {
            'bon'           => 'Bon état',
            'use'           => 'Usé',
            'hors_service'  => 'Hors service',
            'en_reparation' => 'En réparation',
            default         => ucfirst($this->etat),
        };
    }

    public function getCategorieLabelAttribute(): string
    {
        return match ($this->categorie) {
            'mobilier'                => 'Mobilier',
            'equipement_pedagogique'  => 'Équipement pédagogique',
            'fourniture_bureau'       => 'Fournitures bureau',
            'fourniture_pedagogique'  => 'Fournitures pédagogiques',
            'equipement_sportif'      => 'Équipement sportif',
            'materiel_entretien'      => 'Matériel entretien',
            'equipement_informatique' => 'Équipement informatique',
            default                   => 'Autre',
        };
    }

    public function getValeurTotaleAttribute(): float
    {
        return (float) ($this->valeur_unitaire ?? 0) * $this->quantite_stock;
    }

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementStock::class, 'article_id');
    }

    public function prets(): HasMany
    {
        return $this->hasMany(PretMateriel::class, 'article_id');
    }

    public function pretsEnCours(): HasMany
    {
        return $this->hasMany(PretMateriel::class, 'article_id')
            ->where('statut', 'en_cours');
    }

    public function scopeEnAlerte($query)
    {
        return $query->whereColumn('quantite_stock', '<=', 'quantite_minimum');
    }

    public function scopeImmobilises($query)
    {
        return $query->where('est_immobilise', true);
    }

    public function scopeCategorie($query, string $cat)
    {
        return $query->where('categorie', $cat);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('nom', 'ILIKE', "%{$search}%")
              ->orWhere('reference', 'ILIKE', "%{$search}%")
              ->orWhere('qr_code', 'ILIKE', "%{$search}%");
        });
    }
}
