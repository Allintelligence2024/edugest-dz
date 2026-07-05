<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SignalementComportement extends Model
{
    use HasUuids;

    protected $table = 'signalements_comportement';

    protected $fillable = [
        'tenant_id', 'eleve_id', 'signale_par', 'role_auteur',
        'type', 'gravite', 'description', 'lieu',
        'date_incident', 'heure_incident',
        'notifie_parent', 'vu_par_parent', 'vu_le',
        'traite', 'suite_donnee', 'traite_par',
    ];

    protected $casts = [
        'date_incident'  => 'date',
        'notifie_parent' => 'boolean',
        'vu_par_parent'  => 'boolean',
        'traite'         => 'boolean',
        'vu_le'          => 'datetime',
    ];

    public const TYPES = [
        'perturbation'   => ['label' => 'Perturbation en classe',    'emoji' => '😤', 'positif' => false],
        'retard_répété'  => ['label' => 'Retards répétés',           'emoji' => '⏰', 'positif' => false],
        'violence'       => ['label' => 'Violence / Bagarre',        'emoji' => '⚠️', 'positif' => false],
        'tricherie'      => ['label' => 'Tricherie / Fraude',        'emoji' => '📋', 'positif' => false],
        'insolence'      => ['label' => 'Insolence / Irrespect',     'emoji' => '🗣️', 'positif' => false],
        'absentéisme'    => ['label' => 'Absentéisme répété',        'emoji' => '📵', 'positif' => false],
        'félicitation'   => ['label' => 'Félicitation / Bon travail','emoji' => '⭐', 'positif' => true],
        'encouragement'  => ['label' => 'Encouragement / Progrès',   'emoji' => '📈', 'positif' => true],
        'autre'          => ['label' => 'Autre',                     'emoji' => '📝', 'positif' => false],
    ];

    public const GRAVITES = [
        'info'        => ['label' => 'Information',  'color' => '#60a5fa'],
        'normale'     => ['label' => 'Normale',      'color' => '#fb923c'],
        'grave'       => ['label' => 'Grave',        'color' => '#f87171'],
        'très_grave'  => ['label' => 'Très grave',   'color' => '#ef4444'],
    ];

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function auteur()
    {
        return $this->belongsTo(User::class, 'signale_par');
    }

    public function getTypeInfoAttribute(): array
    {
        return self::TYPES[$this->type] ?? ['label' => $this->type, 'emoji' => '📝', 'positif' => false];
    }

    public function estPositif(): bool
    {
        return self::TYPES[$this->type]['positif'] ?? false;
    }

    public function scopeNonTraites($query)
    {
        return $query->where('traite', false);
    }

    public function scopeGraves($query)
    {
        return $query->whereIn('gravite', ['grave', 'très_grave']);
    }
}
