<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointageBus extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'pointage_bus';

    protected $fillable = [
        'tenant_id', 'circuit_id', 'eleve_id', 'arret_id',
        'date', 'trajet', 'statut', 'heure_montee',
        'sms_parent_envoye', 'sms_envoye_at', 'signale_par',
    ];

    protected $casts = [
        'date'              => 'date',
        'sms_parent_envoye' => 'boolean',
        'sms_envoye_at'     => 'datetime',
    ];

    public function circuit(): BelongsTo
    {
        return $this->belongsTo(CircuitTransport::class, 'circuit_id');
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function arret(): BelongsTo
    {
        return $this->belongsTo(ArretBus::class, 'arret_id');
    }
}
