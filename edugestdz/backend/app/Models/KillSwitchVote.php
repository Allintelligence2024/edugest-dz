<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class KillSwitchVote extends Model
{
    use HasUuids;

    protected $fillable = [
        'initiator_id',
        'approver_id',
        'action',
        'payload',
        'expires_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function estExpire(): bool
    {
        return $this->expires_at->isPast();
    }
}
