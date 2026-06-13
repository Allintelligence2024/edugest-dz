<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presence extends BaseModel
{
    protected $table = 'presences';

    protected $fillable = [
        'tenant_id', 'seance_id', 'eleve_id',
        'statut', 'motif', 'heure_arrivee', 'saisi_par',
    ];

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }
}
