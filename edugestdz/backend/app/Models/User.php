<?php
namespace App\Models;

use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use App\Traits\BelongsToTenant;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, BelongsToTenant;

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

    protected $fillable = [
        'tenant_id', 'nom', 'prenom', 'email', 'telephone',
        'password', 'avatar_url', 'langue', 'theme',
        'role_id', 'statut',
        'two_factor_secret', 'two_factor_recovery_codes',
        'two_factor_confirmed_at', 'two_factor_type',
        'login_attempts', 'locked_until', 'two_factor_phone',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'derniere_connexion' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'locked_until' => 'datetime',
            'login_attempts' => 'integer',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'tenant_id' => $this->tenant_id,
            'role'      => $this->role_id,
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function role()
    {
        return $this->belongsTo(\App\Models\Role::class);
    }

    public function getNomCompletAttribute(): string
    {
        return strtoupper($this->nom) . ' ' . ucfirst($this->prenom);
    }
}
