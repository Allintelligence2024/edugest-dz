<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\{HasMany, BelongsTo};

class Enseignant extends BaseModel
{
    protected $table = 'enseignants';

    protected $fillable = [
        'tenant_id', 'user_id', 'matricule',
        'nom', 'prenom', 'nom_ar', 'prenom_ar',
        'date_naissance', 'lieu_naissance', 'sexe',
        'telephone', 'email', 'adresse', 'wilaya_id',
        'photo_url', 'diplome', 'specialite', 'experience_annees',
        'type_contrat', 'date_embauche', 'salaire_base', 'taux_horaire',
        'num_securite_sociale', 'num_cnas', 'rib_bancaire', 'banque',
        'statut', 'note_interne',
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'date_embauche'  => 'date',
        'salaire_base'   => 'decimal:2',
        'taux_horaire'   => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cours(): HasMany
    {
        return $this->hasMany(Cours::class);
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class);
    }

    public function paies(): HasMany
    {
        return $this->hasMany(Paie::class);
    }
}
