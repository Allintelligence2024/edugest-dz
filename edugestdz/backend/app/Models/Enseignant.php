<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\{HasMany, HasManyThrough, BelongsTo, BelongsToMany};

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
        'statut', 'note_interne', 'disponibilites',
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'date_embauche'  => 'date',
        'salaire_base'   => 'decimal:2',
        'taux_horaire'   => 'decimal:2',
        'disponibilites' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wilaya(): BelongsTo
    {
        return $this->belongsTo(Wilaya::class);
    }

    public function matieres(): BelongsToMany
    {
        return $this->belongsToMany(Matiere::class, 'enseignant_matiere')
                    ->withPivot('niveau_scolaire', 'est_principal')
                    ->withTimestamps();
    }

    public function cours(): HasMany
    {
        return $this->hasMany(Cours::class);
    }

    public function seances(): HasManyThrough
    {
        return $this->hasManyThrough(Seance::class, Cours::class);
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class);
    }

    public function contratsActifs(): HasMany
    {
        return $this->hasMany(Contrat::class)->where('statut', 'actif');
    }

    public function paies(): HasMany
    {
        return $this->hasMany(Paie::class);
    }
}
