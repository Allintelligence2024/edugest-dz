<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonnelNonEnseignant extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'personnel_non_enseignant';

    protected $fillable = [
        'tenant_id', 'nom', 'prenom', 'telephone', 'email',
        'adresse', 'photo_url', 'date_naissance', 'poste',
        'poste_libelle', 'type_contrat', 'date_embauche',
        'date_fin_contrat', 'salaire_base', 'frequence_paie',
        'statut', 'matricule', 'num_ss', 'num_cnas',
    ];

    protected $casts = [
        'date_naissance'   => 'date',
        'date_embauche'    => 'date',
        'date_fin_contrat' => 'date',
        'salaire_base'     => 'decimal:2',
    ];

    public function getNomCompletAttribute(): string
    {
        return strtoupper($this->nom) . ' ' . ucfirst($this->prenom);
    }

    public function getPosteAfficheAttribute(): string
    {
        if ($this->poste === 'autre' && $this->poste_libelle) {
            return $this->poste_libelle;
        }
        return match ($this->poste) {
            'femme_menage'     => 'Femme de ménage',
            'surveillant'      => 'Surveillant(e)',
            'chauffeur'        => 'Chauffeur',
            'proviseur'        => 'Proviseur',
            'directeur_adjoint'=> 'Directeur adjoint',
            'secretaire'       => 'Secrétaire',
            'technicien'       => 'Technicien',
            'agent_securite'   => 'Agent de sécurité',
            default            => ucfirst($this->poste),
        };
    }

    public function getAncienneteAnsAttribute(): int
    {
        return $this->date_embauche
            ? (int) $this->date_embauche->diffInYears(now())
            : 0;
    }

    public function pointages(): HasMany
    {
        return $this->hasMany(PointagePersonnel::class, 'agent_id');
    }

    public function pointageAujourdhui(): HasOne
    {
        return $this->hasOne(PointagePersonnel::class, 'agent_id')
            ->whereDate('date', today());
    }

    public function conges(): HasMany
    {
        return $this->hasMany(CongePersonnel::class, 'agent_id');
    }

    public function scopeActifs($query)
    {
        return $query->where('statut', 'actif');
    }

    public function scopePoste($query, string $poste)
    {
        return $query->where('poste', $poste);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('nom', 'ILIKE', "%{$search}%")
              ->orWhere('prenom', 'ILIKE', "%{$search}%")
              ->orWhere('matricule', 'ILIKE', "%{$search}%");
        });
    }

    public function isPresent(): bool
    {
        return $this->pointageAujourdhui()
            ->whereNotNull('heure_arrivee')
            ->exists();
    }

    public function soldeCongesRestants(int $annee = null): int
    {
        $annee ??= now()->year;
        $droit  = 30;
        $pris   = $this->conges()
            ->where('type', 'conge_annuel')
            ->where('statut', 'approuve')
            ->whereYear('date_debut', $annee)
            ->sum('nb_jours');
        return max(0, $droit - $pris);
    }
}
