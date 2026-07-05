<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class HistoriqueDiagnostic extends Model
{
    use HasUuids;

    protected $table = 'historique_diagnostics';
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'eleve_id', 'niveau_global', 'score_risque',
        'moyenne_generale', 'tendance', 'details', 'analyse_le',
    ];

    protected $casts = [
        'details'      => 'array',
        'analyse_le'   => 'datetime',
        'score_risque' => 'decimal:2',
    ];
}
