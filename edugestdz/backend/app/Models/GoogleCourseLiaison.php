<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleCourseLiaison extends BaseModel
{
    protected $table = 'google_course_liaisons';

    protected $fillable = [
        'tenant_id', 'evaluation_id', 'gc_course_id',
        'gc_coursework_id', 'gc_course_name',
        'sync_enabled', 'last_sync_at',
    ];

    protected $casts = [
        'sync_enabled' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(GoogleSyncLog::class, 'liaison_id');
    }
}
