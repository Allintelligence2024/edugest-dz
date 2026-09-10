<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Depense extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'categorie', 'libelle', 'montant',
        'date_depense', 'mois', 'annee', 'fournisseur',
        'numero_facture_ext', 'justificatif_url', 'mode_paiement',
        'statut', 'saisie_par', 'validee_par', 'note',
    ];

    protected $casts = [
        'date_depense' => 'date',
        'montant'      => 'decimal:2',
    ];

    public static function categorieLibelle(string $cat): string
    {
        return match ($cat) {
            'salaires_enseignants'    => 'Salaires enseignants',
            'salaires_personnel'      => 'Salaires personnel',
            'loyer'                   => 'Loyer',
            'electricite_gaz'         => 'Électricité & Gaz',
            'eau'                     => 'Eau',
            'telephone_internet'      => 'Téléphone & Internet',
            'fournitures_bureau'      => 'Fournitures bureau',
            'fournitures_pedagogiques'=> 'Fournitures pédagogiques',
            'maintenance_reparation'  => 'Maintenance & Réparation',
            'assurance'               => 'Assurance',
            'publicite_marketing'     => 'Publicité & Marketing',
            'transport'               => 'Transport',
            'cantine_restauration'    => 'Cantine & Restauration',
            'taxes_impots'            => 'Taxes & Impôts',
            default                   => 'Autres',
        };
    }

    public function saisiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisie_par');
    }

    public function valideePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validee_par');
    }

    public function scopePeriode($query, int $mois, int $annee)
    {
        return $query->where('mois', $mois)->where('annee', $annee);
    }

    public function scopeAnnee($query, int $annee)
    {
        return $query->where('annee', $annee);
    }

    public function scopeValidees($query)
    {
        return $query->where('statut', 'validee');
    }
}
