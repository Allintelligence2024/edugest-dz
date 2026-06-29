<?php

namespace App\Models;

use App\Traits\BelongsToTenant;

class Badge extends BaseModel
{
    use BelongsToTenant;

    public static function bootSoftDeletes()
    {
    }

    protected $fillable = [
        'tenant_id',
        'badge_uid',
        'proprietaire_id',
        'type_proprietaire',
        'actif',
        'date_emission',
    ];

    protected $casts = [
        'actif'         => 'boolean',
        'date_emission' => 'date',
    ];

    public function proprietaire()
    {
        return match ($this->type_proprietaire) {
            'eleve'       => $this->belongsTo(Eleve::class,       'proprietaire_id'),
            'enseignant'  => $this->belongsTo(Enseignant::class,  'proprietaire_id'),
            default       => null,
        };
    }

    public static function trouverParUid(string $uid): ?self
    {
        return static::where('badge_uid', $uid)
            ->where('actif', true)
            ->first();
    }
}
