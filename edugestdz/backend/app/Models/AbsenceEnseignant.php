<?php

namespace App\Models;

class AbsenceEnseignant extends BaseModel
{
    protected $table = 'absences_enseignants';

    protected $fillable = [
        'tenant_id', 'enseignant_user_id', 'date_absence',
        'motif', 'statut', 'remplacant_user_id',
        'eleves_notifies', 'parents_notifies', 'signale_le',
    ];

    protected $casts = [
        'date_absence'     => 'date',
        'signale_le'       => 'datetime',
        'eleves_notifies'  => 'boolean',
        'parents_notifies' => 'boolean',
    ];

    public function enseignant()
    {
        return $this->belongsTo(User::class, 'enseignant_user_id');
    }

    public function remplacant()
    {
        return $this->belongsTo(User::class, 'remplacant_user_id');
    }

    public function seancesAffectees()
    {
        return Seance::whereHas('cours', function ($q) {
            $q->whereHas('enseignant', fn($eq) => $eq->where('user_id', $this->enseignant_user_id));
        })->where('date_seance', $this->date_absence);
    }
}
