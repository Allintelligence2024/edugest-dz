<?php
namespace App\Models;

use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

/**
 * Compte utilisateur.
 *
 * ── Pourquoi ce modèle n'utilise PAS BelongsToTenant ──────────────────────
 *
 * L'authentification cherche un utilisateur par e-mail AVANT qu'un tenant
 * soit résolu : à la connexion, c'est justement le compte trouvé qui
 * détermine le tenant. Un scope global fail-closed viderait cette requête
 * et rendrait toute connexion impossible. La console super-admin, qui
 * agit par définition en travers des établissements, a la même contrainte.
 *
 * Conséquence à ne pas perdre de vue : l'isolation des utilisateurs repose
 * entièrement sur les `where('tenant_id', …)` explicites des contrôleurs,
 * services et commandes. Ce ne sont pas des redondances défensives — ce
 * sont les seuls filtres. Les supprimer exposerait les comptes d'un
 * établissement à un autre.
 *
 * Le trait était auparavant importé ici sans jamais être appliqué dans le
 * corps de la classe. L'import a été retiré : il laissait croire, à la
 * lecture comme à l'analyse statique, que le modèle était scopé.
 */
class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

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

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // AUTORISATION (RBAC)
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /** Nom du rôle courant ('admin', 'parent', ...) ou null. */
    public function nomRole(): ?string
    {
        return $this->relationLoaded('role')
            ? $this->role?->nom
            : $this->role()->value('nom');
    }

    /** L'utilisateur possède-t-il l'un des rôles donnés ? */
    public function hasRole(string ...$roles): bool
    {
        $courant = $this->nomRole();

        return $courant !== null && in_array($courant, $roles, true);
    }

    /** Raccourci : super administrateur plateforme. */
    public function isSuperAdmin(): bool
    {
        return $this->nomRole() === 'super_admin';
    }

    /**
     * Permissions effectives du rôle, au format "module.action".
     * Mises en cache 5 min par rôle : évite une jointure à chaque requête.
     */
    public function permissions(): array
    {
        $roleId = $this->role_id;

        if (!$roleId) {
            return [];
        }

        return \Illuminate\Support\Facades\Cache::remember(
            "rbac.role.{$roleId}.permissions",
            now()->addMinutes(5),
            fn () => \Illuminate\Support\Facades\DB::table('role_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('role_permissions.role_id', $roleId)
                ->pluck('permissions.nom')
                ->all()
        );
    }

    /**
     * L'utilisateur détient-il la permission "module.action" ?
     * Le super_admin court-circuite systématiquement la vérification.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($permission, $this->permissions(), true);
    }

    public function getNomCompletAttribute(): string
    {
        return strtoupper($this->nom) . ' ' . ucfirst($this->prenom);
    }
}
