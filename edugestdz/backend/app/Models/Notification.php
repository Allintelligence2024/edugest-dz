<?php
namespace App\Models;

class Notification extends BaseModel
{
    protected $table = 'notifications';

    protected $fillable = [
        'tenant_id', 'user_id', 'type', 'titre',
        'message', 'lien', 'est_lu', 'envoye_par',
    ];

    protected $casts = [
        'est_lu' => 'boolean',
    ];
}
