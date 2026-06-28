<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    protected $table = 'tenants';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'nom_etablissement', 'slug', 'type_etablissement',
        'wilaya_id', 'commune_id', 'adresse', 'telephone', 'email',
        'site_web', 'logo_url', 'nif', 'nis', 'registre_commerce',
        'plan_abonnement', 'date_expiration', 'statut', 'settings',
    ];

    protected $casts = [
        'date_expiration' => 'date',
        'settings' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }
        });
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function eleves()
    {
        return $this->hasMany(Eleve::class);
    }
}
