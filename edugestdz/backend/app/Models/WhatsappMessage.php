<?php
namespace App\Models;

class WhatsappMessage extends BaseModel
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'tenant_id', 'message_id', 'from_number', 'to_number',
        'direction', 'type', 'content', 'template_name',
        'status', 'meta',
    ];

    protected $casts = [
        'meta' => 'json',
    ];
}
