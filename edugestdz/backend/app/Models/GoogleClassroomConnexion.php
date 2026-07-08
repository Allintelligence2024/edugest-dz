<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleClassroomConnexion extends BaseModel
{
    protected $table = 'google_classroom_connexions';

    protected $fillable = [
        'tenant_id', 'user_id', 'email', 'token',
        'expires_at', 'google_user_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected $hidden = ['token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
