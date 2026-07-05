<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DiagnosticEleve extends Model
{
    use HasUuids;

    protected $table = 'diagnostics_eleves';

    protected $fillable = [
        'tenant_id', 'eleve_id', 'niveau_global', 'score_risque',
        'moyenne_generale', 'moyenne_trimestre_precedent', 'tendance',
        'nb_notes_sous_5', 'nb_notes_sous_10', 'nb_notes_consecutives_sous_5',
        'matieres_en_danger', 'matieres_excellentes',
        'nb_absences_mois', 'nb_retards_mois', 'nb_billets_mois',
        'rattrapage_requis', 'convocation_requise', 'sms_alerte_envoye', 'mention_excellence',
        'derniere_analyse', 'prochaine_analyse',
    ];

    protected $casts = [
        'matieres_en_danger'            => 'array',
        'matieres_excellentes'          => 'array',
        'rattrapage_requis'             => 'boolean',
        'convocation_requise'           => 'boolean',
        'sms_alerte_envoye'             => 'boolean',
        'mention_excellence'            => 'boolean',
        'score_risque'                  => 'decimal:2',
        'moyenne_generale'              => 'decimal:2',
        'moyenne_trimestre_precedent'   => 'decimal:2',
        'tendance'                      => 'decimal:2',
        'derniere_analyse'              => 'datetime',
        'prochaine_analyse'             => 'datetime',
    ];

    public const NIVEAUX = [
        'excellent'  => ['label' => '⭐ Excellent',  'color' => '#4ade80', 'score_max' => 10],
        'normal'     => ['label' => '✅ Normal',      'color' => '#60a5fa', 'score_max' => 30],
        'vigilance'  => ['label' => '⚠️ Vigilance',  'color' => '#fb923c', 'score_max' => 55],
        'danger'     => ['label' => '🔴 Danger',      'color' => '#f87171', 'score_max' => 75],
        'critique'   => ['label' => '🚨 Critique',   'color' => '#ef4444', 'score_max' => 100],
    ];

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function historique()
    {
        return $this->hasMany(HistoriqueDiagnostic::class, 'eleve_id', 'eleve_id')
            ->orderByDesc('analyse_le');
    }

    public function plans()
    {
        return $this->hasMany(PlanRattrapage::class, 'eleve_id', 'eleve_id');
    }

    public function convocations()
    {
        return $this->hasMany(ConvocationParent::class, 'eleve_id', 'eleve_id');
    }

    public function scopeNiveau($query, string $niveau)
    {
        return $query->where('niveau_global', $niveau);
    }

    public function scopeRequiertAction($query)
    {
        return $query->where(function ($q) {
            $q->where('rattrapage_requis', true)
              ->orWhere('convocation_requise', true);
        });
    }

    public function getNiveauInfoAttribute(): array
    {
        return self::NIVEAUX[$this->niveau_global] ?? self::NIVEAUX['normal'];
    }
}
