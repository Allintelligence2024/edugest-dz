<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleSyncLog extends BaseModel
{
    protected $table = 'google_sync_logs';

    protected $fillable = [
        'tenant_id', 'liaison_id', 'action',
        'status', 'message', 'meta',
    ];

    protected $casts = [
        'meta' => 'json',
    ];

    public function liaison(): BelongsTo
    {
        return $this->belongsTo(GoogleCourseLiaison::class, 'liaison_id');
    }
}
