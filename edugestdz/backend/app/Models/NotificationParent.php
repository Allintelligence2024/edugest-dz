<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class NotificationParent extends Model
{
    use HasUuids;

    protected $table = 'notifications_parent';

    protected $fillable = [
        'tenant_id', 'parent_id', 'eleve_id', 'type',
        'titre', 'corps', 'meta', 'lu', 'lu_le',
        'push_envoye', 'sms_envoye',
    ];

    protected $casts = [
        'meta'        => 'array',
        'lu'          => 'boolean',
        'push_envoye' => 'boolean',
        'sms_envoye'  => 'boolean',
        'lu_le'       => 'datetime',
    ];

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function scopeNonLues($query)
    {
        return $query->where('lu', false);
    }
}
