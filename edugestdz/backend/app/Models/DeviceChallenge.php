<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceChallenge extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'challenge_hash',
        'attempts',
        'max_attempts',
        'expires_at',
        'invalidated_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'expires_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValide(): bool
    {
        return $this->invalidated_at === null && $this->expires_at->isFuture();
    }

    public function tropDeTentatives(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }

    public function invalider(): void
    {
        $this->invalidated_at = now();
        $this->save();
    }
}
