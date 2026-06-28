<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends BaseModel
{
    protected $table = 'conversations';

    protected $fillable = [
        'tenant_id', 'sujet', 'participants', 'lu_par', 'last_message_at',
    ];

    protected $casts = [
        'participants'    => 'array',
        'lu_par'          => 'array',
        'last_message_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function dernierMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}
