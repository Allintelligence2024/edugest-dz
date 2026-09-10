<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class FavoriMarketplace extends Model
{
    use HasUuids;

    protected $table = 'favoris_marketplace';

    protected $fillable = ['parent_id', 'tenant_id'];
}
