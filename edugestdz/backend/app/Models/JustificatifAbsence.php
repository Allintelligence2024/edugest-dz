<?php
namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JustificatifAbsence extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'justificatifs_absence';

    protected $fillable = [
        'tenant_id','absence_id','motif','document_url',
        'statut','valide_par','valide_at',
    ];

    protected $casts = [
        'valide_at' => 'datetime',
    ];

    public function absence(): BelongsTo
    {
        return $this->belongsTo(AbsenceJournaliere::class, 'absence_id');
    }
}
