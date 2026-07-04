<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AlerteSurveillance extends Model
{
    use HasUuids;

    protected $table = 'alertes_surveillance';

    protected $fillable = [
        'tenant_id', 'camera_id', 'serial_no', 'type_alerte',
        'niveau', 'canal', 'payload', 'survenu_le',
        'traite', 'traite_par', 'traite_le', 'note_admin',
        'sms_envoye', 'push_envoye',
    ];

    protected $casts = [
        'payload'     => 'array',
        'traite'      => 'boolean',
        'sms_envoye'  => 'boolean',
        'push_envoye' => 'boolean',
        'survenu_le'  => 'datetime',
        'traite_le'   => 'datetime',
    ];

    public const TYPES = [
        'VideoMotion'        => 'Détection de mouvement',
        'AlarmLocal'         => 'Alarme locale',
        'CrossLineDetection' => 'Franchissement de ligne',
        'IntrusionDetection' => 'Détection d\'intrusion',
        'FaceDetection'      => 'Détection de visage',
        'VideoLoss'          => 'Perte signal vidéo',
        'VideoBlind'         => 'Sabotage caméra',
        'DiskFull'           => 'Disque DVR plein',
        'DiskError'          => 'Erreur disque DVR',
        'StorageNotExist'    => 'Stockage absent',
        'StorageLowSpace'    => 'Stockage faible',
        'NetworkAbort'       => 'Perte réseau',
        'temperature'        => 'Température anormale',
        'autre'              => 'Événement divers',
    ];

    public const NIVEAUX_PAR_TYPE = [
        'VideoMotion'        => 'warning',
        'AlarmLocal'         => 'critical',
        'CrossLineDetection' => 'critical',
        'IntrusionDetection' => 'critical',
        'FaceDetection'      => 'warning',
        'VideoLoss'          => 'critical',
        'VideoBlind'         => 'critical',
        'DiskFull'           => 'warning',
        'DiskError'          => 'critical',
        'StorageNotExist'    => 'warning',
        'StorageLowSpace'    => 'info',
        'NetworkAbort'       => 'warning',
    ];

    public function camera()
    {
        return $this->belongsTo(CameraConfig::class, 'camera_id');
    }

    public function traitePar()
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    public function getLibelleTypeAttribute(): string
    {
        return self::TYPES[$this->type_alerte] ?? $this->type_alerte;
    }

    public function scopeNonTraitees($query)
    {
        return $query->where('traite', false);
    }

    public function scopeCritiques($query)
    {
        return $query->where('niveau', 'critical');
    }
}
