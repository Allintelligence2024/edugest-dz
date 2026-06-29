<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepasJournalier extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'repas_journaliers';

    protected $fillable = [
        'tenant_id', 'eleve_id', 'menu_id', 'date_repas',
        'type_repas', 'present', 'facture', 'prix_applique', 'signale_par',
    ];

    protected $casts = [
        'date_repas'    => 'date',
        'present'       => 'boolean',
        'facture'       => 'boolean',
        'prix_applique' => 'decimal:2',
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(MenuCantine::class, 'menu_id');
    }
}
