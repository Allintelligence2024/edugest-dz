<?php
namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class AbsenceJournaliere extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'absences_journalieres';

    protected $fillable = [
        'tenant_id','eleve_id','date_absence','statut',
        'heure_arrivee','signale_par','sms_parent_envoye',
        'sms_envoye_at','motif',
    ];

    protected $casts = [
        'date_absence'      => 'date',
        'sms_parent_envoye' => 'boolean',
        'sms_envoye_at'     => 'datetime',
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function justificatif(): HasOne
    {
        return $this->hasOne(JustificatifAbsence::class, 'absence_id');
    }
}
