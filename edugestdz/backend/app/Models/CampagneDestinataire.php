<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CampagneDestinataire extends Model
{
    use HasFactory, SoftDeletes;

    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }
        });
    }

    protected $table = 'campagne_destinataires';

    protected $fillable = [
        'campagne_id', 'destinataire_id', 'canal', 'statut', 'erreur', 'envoye_le',
    ];

    protected $casts = [
        'envoye_le' => 'datetime',
    ];

    public function campagne()
    {
        return $this->belongsTo(Campagne::class);
    }
}
