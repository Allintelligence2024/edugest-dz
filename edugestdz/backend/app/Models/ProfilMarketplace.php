<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ProfilMarketplace extends Model
{
    use HasUuids;

    protected $table = 'profils_marketplace';

    protected $fillable = [
        'tenant_id', 'nom_etablissement', 'description', 'adresse',
        'wilaya', 'commune', 'telephone', 'email', 'site_web',
        'logo_url', 'photos_urls', 'matieres_enseignees', 'niveaux_couverts',
        'horaires', 'tarif_heure_min', 'tarif_heure_max',
        'accepte_essai_gratuit', 'visible', 'verifie',
        'nb_eleves_actifs', 'annees_experience', 'note_moyenne', 'nb_avis',
    ];

    protected $casts = [
        'photos_urls'         => 'array',
        'matieres_enseignees' => 'array',
        'niveaux_couverts'    => 'array',
        'horaires'            => 'array',
        'tarif_heure_min'     => 'decimal:2',
        'tarif_heure_max'     => 'decimal:2',
        'note_moyenne'        => 'decimal:2',
        'visible'             => 'boolean',
        'verifie'             => 'boolean',
        'accepte_essai_gratuit' => 'boolean',
    ];

    public function offres()
    {
        return $this->hasMany(OffreCours::class, 'tenant_id', 'tenant_id');
    }

    public function avis()
    {
        return $this->hasMany(AvisMarketplace::class, 'tenant_id', 'tenant_id')
            ->where('visible', true)
            ->orderByDesc('created_at');
    }

    public function reservations()
    {
        return $this->hasMany(ReservationMarketplace::class, 'tenant_id', 'tenant_id');
    }

    public function scopeVisible($query)
    {
        return $query->where('visible', true);
    }

    public function scopeWilaya($query, string $wilaya)
    {
        return $query->where('wilaya', $wilaya);
    }

    public function scopeMatiere($query, string $matiere)
    {
        return $query->whereJsonContains('matieres_enseignees', $matiere);
    }

    public function scopeNiveau($query, string $niveau)
    {
        return $query->whereJsonContains('niveaux_couverts', $niveau);
    }

    public function recalculerNote(): void
    {
        $stats = AvisMarketplace::where('tenant_id', $this->tenant_id)
            ->where('visible', true)
            ->selectRaw('AVG(note) as moyenne, COUNT(*) as total')
            ->first();

        $this->update([
            'note_moyenne' => round((float) $stats->moyenne, 2),
            'nb_avis'      => (int) $stats->total,
        ]);
    }
}
