<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuCantine extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'menus_cantine';

    protected $fillable = [
        'tenant_id', 'date_repas', 'type_repas',
        'plat_principal', 'accompagnement', 'dessert',
        'boisson', 'prix_unitaire', 'nb_couverts_prevus',
        'allergenes', 'note', 'publie',
    ];

    protected $casts = [
        'date_repas'    => 'date',
        'prix_unitaire' => 'decimal:2',
        'publie'        => 'boolean',
    ];

    public function repasJournaliers(): HasMany
    {
        return $this->hasMany(RepasJournalier::class, 'menu_id');
    }

    public function getNbPresentsAttribute(): int
    {
        return $this->repasJournaliers()->where('present', true)->count();
    }

    public function scopePublies($query)
    {
        return $query->where('publie', true);
    }

    public function scopeSemaine($query, string $dateDebut, string $dateFin)
    {
        return $query->whereBetween('date_repas', [$dateDebut, $dateFin]);
    }
}
