<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CongePersonnel extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'conges_personnel';

    protected $fillable = [
        'tenant_id', 'agent_id', 'date_debut', 'date_fin',
        'nb_jours', 'type', 'motif', 'document_url',
        'statut', 'approuve_par', 'approuve_at',
    ];

    protected $casts = [
        'date_debut'  => 'date',
        'date_fin'    => 'date',
        'approuve_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(PersonnelNonEnseignant::class, 'agent_id');
    }

    public function approbateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approuve_par');
    }
}
