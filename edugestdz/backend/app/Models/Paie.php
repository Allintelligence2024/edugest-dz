<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paie extends BaseModel
{
    protected $table = 'paies';

    protected $fillable = [
        'tenant_id', 'enseignant_id', 'mois', 'annee',
        'salaire_base', 'heures_travaillees', 'taux_horaire',
        'primes', 'retenues_absences', 'irg', 'cnas', 'casnos',
        'salaire_net', 'statut', 'date_paiement', 'bulletin_url',
    ];

    protected $casts = [
        'date_paiement' => 'date',
    ];

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }
}
