<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends BaseModel
{
    protected $table = 'notes';

    protected $fillable = [
        'tenant_id', 'evaluation_id', 'eleve_id',
        'note', 'absent', 'appreciation', 'commentaire', 'saisie_par',
    ];

    protected $casts = [
        'note' => 'decimal:2',
        'absent' => 'boolean',
    ];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }
}
