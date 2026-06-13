<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'tenant_id', 'nom', 'label_fr', 'label_ar', 'description', 'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];
}
