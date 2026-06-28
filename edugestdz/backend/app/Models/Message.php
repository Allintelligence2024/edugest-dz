<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends BaseModel
{
    protected $table = 'messages';

    protected $fillable = [
        'conversation_id', 'expediteur_id',
        'message', 'type_message', 'fichier_url', 'fichier_nom',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function expediteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expediteur_id');
    }
}
