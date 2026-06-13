<?php
namespace App\Models;

class Tenant extends BaseModel
{
    protected $table = 'tenants';

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

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function eleves()
    {
        return $this->hasMany(Eleve::class);
    }
}
