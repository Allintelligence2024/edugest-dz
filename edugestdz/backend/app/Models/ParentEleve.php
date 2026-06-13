<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ParentEleve extends BaseModel
{
    protected $table = 'parents';

    protected $fillable = [
        'tenant_id', 'user_id',
        'nom', 'prenom', 'lien',
        'telephone_1', 'telephone_2', 'email',
        'profession', 'lieu_travail', 'est_urgence',
    ];

    protected $hidden = ['deleted_at'];

    public function eleves(): BelongsToMany
    {
        return $this->belongsToMany(Eleve::class, 'eleve_parent')
                    ->withPivot('est_principal');
    }
}
