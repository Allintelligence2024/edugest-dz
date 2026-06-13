<?php
// backend/app/Models/BaseModel.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use Illuminate\Support\Str;

abstract class BaseModel extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    // ── UUID comme clé primaire ──
    public $incrementing = false;
    protected $keyType   = 'string';

    protected static function boot(): void
    {
        parent::boot();

        // Générer UUID automatiquement
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }
        });
    }

    // ── Format de réponse standardisé ──
    public function toApiArray(): array
    {
        return $this->toArray();
    }
}
