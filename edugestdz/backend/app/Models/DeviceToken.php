<?php
namespace App\Models;

class DeviceToken extends BaseModel
{
    protected $table = 'device_tokens';

    protected $fillable = [
        'tenant_id', 'user_id', 'token', 'platform',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
