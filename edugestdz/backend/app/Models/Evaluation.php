<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Evaluation extends BaseModel
{
    protected $table = 'evaluations';

    protected $fillable = [
        'tenant_id', 'groupe_id', 'titre', 'type_eval',
        'date_evaluation', 'note_sur', 'coefficient',
        'trimestre', 'description', 'created_by',
    ];

    protected $casts = [
        'date_evaluation' => 'date',
        'note_sur'   => 'decimal:2',
        'coefficient' => 'decimal:2',
    ];

    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }
}
