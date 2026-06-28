<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends Model
{
    use HasFactory;
    protected $fillable = [
        'tenant_id', 'nom', 'label_fr', 'label_ar', 'description', 'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];
}
