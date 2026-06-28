<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Avis extends BaseModel
{
    protected $table = 'avis';

    protected $fillable = [
        'tenant_id', 'reservation_id', 'eleve_id',
        'enseignant_id', 'note', 'commentaire',
    ];

    protected $casts = [
        'note' => 'integer',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }
}
