<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CameraConfig extends Model
{
    use HasUuids;

    protected $table = 'cameras_config';

    protected $fillable = [
        'tenant_id', 'nom', 'serial_no', 'ip_locale', 'canal',
        'type', 'localisation', 'webhook_secret',
        'heure_ouverture', 'heure_fermeture', 'actif',
    ];

    protected $hidden = ['webhook_secret'];

    protected $casts = [
        'actif'            => 'boolean',
        'canal'            => 'integer',
        'heure_ouverture'  => 'string',
        'heure_fermeture'  => 'string',
    ];

    public const TYPES = [
        'entree'   => 'Entrée principale',
        'couloir'  => 'Couloir',
        'classe'   => 'Salle de classe',
        'parking'  => 'Parking / Extérieur',
        'cantine'  => 'Cantine',
        'bus'      => 'Bus scolaire',
        'autre'    => 'Autre',
    ];

    public function alertes()
    {
        return $this->hasMany(AlerteSurveillance::class, 'camera_id');
    }

    public function estHorsHoraires(): bool
    {
        $now    = now()->format('H:i');
        $ouv    = $this->heure_ouverture ?? '07:00';
        $ferm   = $this->heure_fermeture ?? '20:00';
        return $now < $ouv || $now > $ferm;
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
