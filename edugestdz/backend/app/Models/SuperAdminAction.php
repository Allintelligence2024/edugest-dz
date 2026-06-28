<?php

namespace App\Models;

class SuperAdminAction extends BaseModel
{
    protected $table = 'super_admin_actions';

    protected $fillable = [
        'super_admin_id', 'tenant_id', 'action', 'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];
}
