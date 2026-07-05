<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ConvocationParent extends Model
{
    use HasUuids;

    protected $table = 'convocations_parents';

    protected $fillable = [
        'tenant_id', 'eleve_id', 'motif', 'message', 'canal',
        'statut', 'envoyee_le', 'rendez_vous_le', 'compte_rendu', 'cree_par',
    ];

    protected $casts = [
        'envoyee_le'     => 'datetime',
        'rendez_vous_le' => 'datetime',
    ];

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function createur()
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}
