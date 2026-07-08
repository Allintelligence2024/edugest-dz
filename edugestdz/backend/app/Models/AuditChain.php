<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AuditChain extends Model
{
    use HasUuids;

    protected $table = 'audit_chain';

    protected $fillable = [
        'bloc_numero',
        'previous_hash',
        'data_hash',
        'signature',
        'payload',
        'causer_id',
        'causer_type',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'bloc_numero' => 'integer',
            'payload' => 'array',
            'logged_at' => 'datetime',
        ];
    }
}
