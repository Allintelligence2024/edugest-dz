<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CircuitTransport extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'circuits_transport';

    protected $fillable = [
        'tenant_id', 'nom', 'description', 'chauffeur_id',
        'vehicule_immat', 'vehicule_marque', 'capacite',
        'tarif_mensuel', 'type_abonnement', 'actif', 'note',
        'date_controle_technique', 'date_expiration_assurance', 'date_vidange',
    ];

    protected $casts = [
        'tarif_mensuel'             => 'decimal:2',
        'actif'                     => 'boolean',
        'date_controle_technique'   => 'date',
        'date_expiration_assurance' => 'date',
        'date_vidange'              => 'date',
    ];

    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(PersonnelNonEnseignant::class, 'chauffeur_id');
    }

    public function arrets(): HasMany
    {
        return $this->hasMany(ArretBus::class, 'circuit_id')->orderBy('ordre');
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(TransportEleve::class, 'circuit_id');
    }

    public function inscriptionsActives(): HasMany
    {
        return $this->hasMany(TransportEleve::class, 'circuit_id')
            ->where('actif', true)
            ->where(fn($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>=', today()));
    }

    public function pointages(): HasMany
    {
        return $this->hasMany(PointageBus::class, 'circuit_id');
    }

    public function getAlertesMaintenanceAttribute(): array
    {
        $alertes = [];
        if ($this->date_controle_technique && $this->date_controle_technique->isPast()) {
            $alertes[] = "Controle technique expire le {$this->date_controle_technique->format('d/m/Y')}";
        }
        if ($this->date_expiration_assurance && $this->date_expiration_assurance->isPast()) {
            $alertes[] = "Assurance expiree le {$this->date_expiration_assurance->format('d/m/Y')}";
        }
        return $alertes;
    }

    public function getNbElevesActifsAttribute(): int
    {
        return $this->inscriptionsActives()->count();
    }

    public function getTauxRemplissageAttribute(): float
    {
        if ($this->capacite === 0) return 0;
        return round(($this->nb_eleves_actifs / $this->capacite) * 100, 1);
    }

    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }
}
