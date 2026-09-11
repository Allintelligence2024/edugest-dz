<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Sprint 3 — Refresh tokens avec rotation et détection de réutilisation.
 *
 * Modèle de sécurité :
 *   - le jeton est aléatoire (256 bits), stocké uniquement en SHA-256 ;
 *   - il voyage dans un cookie httpOnly : inaccessible au JavaScript, donc
 *     insensible au vol par XSS (contrairement à localStorage) ;
 *   - chaque usage le consomme et en émet un nouveau (rotation) ;
 *   - présenter un jeton déjà consommé révoque TOUTE la lignée : c'est la
 *     signature d'un vol de jeton rejoué.
 */
class RefreshTokenService
{
    public const COOKIE = 'edugest_refresh';

    /** Émet un nouveau refresh token et retourne [clair, cookie]. */
    public function emettre(User $user, Request $request, ?string $familyId = null): array
    {
        $clair = Str::random(64);

        DB::table('refresh_tokens')->insert([
            'id'         => Str::uuid()->toString(),
            'user_id'    => $user->id,
            'tenant_id'  => $user->tenant_id,
            'token_hash' => hash('sha256', $clair),
            'family_id'  => $familyId ?: Str::uuid()->toString(),
            'expires_at' => now()->addDays($this->dureeJours()),
            'ip'         => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$clair, $this->cookie($clair)];
    }

    /**
     * Consomme un refresh token et en émet un nouveau (rotation).
     *
     * @return array{0:User,1:string,2:Cookie}|null  null si invalide
     */
    public function faireTourner(string $clair, Request $request): ?array
    {
        $hash = hash('sha256', $clair);

        $ligne = DB::table('refresh_tokens')->where('token_hash', $hash)->first();

        if (!$ligne) {
            return null;
        }

        // ── Détection de réutilisation ──
        // Le jeton existe mais a déjà été consommé : quelqu'un rejoue un
        // ancien jeton. On considère la lignée entière comme compromise.
        if ($ligne->revoked_at !== null) {
            $this->revoquerLignee($ligne->family_id, 'reuse_detected');

            Log::critical('RefreshToken: réutilisation détectée — lignée révoquée', [
                'user_id'   => $ligne->user_id,
                'family_id' => $ligne->family_id,
                'ip'        => $request->ip(),
            ]);

            return null;
        }

        if (now()->greaterThan($ligne->expires_at)) {
            return null;
        }

        // /auth/refresh est une route PUBLIQUE : aucun middleware n'y résout
        // de tenant. Le scope global fail-closed de BelongsToTenant filtrerait
        // donc `1 = 0` et User::find() renverrait null pour un utilisateur
        // parfaitement valide. Le refresh token porte lui-même l'identité et
        // le tenant : on court-circuite le scope, puis on rétablit le contexte
        // à partir de la ligne en base.
        $user = User::withoutGlobalScope('tenant')->find($ligne->user_id);

        if (!$user || $user->statut !== 'actif') {
            return null;
        }

        // Rétablir le contexte tenant pour la suite de la requête : sans lui,
        // le JWT serait émis hors périmètre.
        if ($user->tenant_id) {
            config(['tenant.current_id' => $user->tenant_id]);
        }

        // Consommation du jeton présenté.
        DB::table('refresh_tokens')
            ->where('id', $ligne->id)
            ->update([
                'revoked_at'     => now(),
                'revoked_reason' => 'rotated',
                'updated_at'     => now(),
            ]);

        // Nouveau jeton dans la même lignée.
        [$nouveauClair, $cookie] = $this->emettre($user, $request, $ligne->family_id);

        return [$user, $nouveauClair, $cookie];
    }

    /** Révoque tous les jetons d'une lignée. */
    public function revoquerLignee(string $familyId, string $raison): void
    {
        DB::table('refresh_tokens')
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at'     => now(),
                'revoked_reason' => $raison,
                'updated_at'     => now(),
            ]);
    }

    /** Révoque toutes les sessions d'un utilisateur (logout global). */
    public function revoquerUtilisateur(string $userId, string $raison = 'logout'): int
    {
        return DB::table('refresh_tokens')
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at'     => now(),
                'revoked_reason' => $raison,
                'updated_at'     => now(),
            ]);
    }

    /** Révoque le jeton présenté (déconnexion simple). */
    public function revoquerJeton(string $clair, string $raison = 'logout'): void
    {
        DB::table('refresh_tokens')
            ->where('token_hash', hash('sha256', $clair))
            ->whereNull('revoked_at')
            ->update([
                'revoked_at'     => now(),
                'revoked_reason' => $raison,
                'updated_at'     => now(),
            ]);
    }

    /** Purge des jetons expirés depuis plus de 30 jours. */
    public function purger(): int
    {
        return DB::table('refresh_tokens')
            ->where('expires_at', '<', now()->subDays(30))
            ->delete();
    }

    /** Cookie httpOnly transportant le refresh token. */
    public function cookie(string $valeur): Cookie
    {
        return Cookie::create(self::COOKIE)
            ->withValue($valeur)
            ->withExpires(now()->addDays($this->dureeJours())->getTimestamp())
            ->withPath('/api/v1/auth')      // envoyé uniquement aux routes d'auth
            ->withDomain(config('session.domain'))
            ->withSecure($this->secure())
            ->withHttpOnly(true)            // invisible au JavaScript => anti-XSS
            ->withSameSite(config('session.same_site', 'lax'));
    }

    /** Cookie d'effacement, à renvoyer au logout. */
    public function cookieEfface(): Cookie
    {
        return Cookie::create(self::COOKIE)
            ->withValue('')
            ->withExpires(1)
            ->withPath('/api/v1/auth')
            ->withDomain(config('session.domain'))
            ->withSecure($this->secure())
            ->withHttpOnly(true)
            ->withSameSite(config('session.same_site', 'lax'));
    }

    private function secure(): bool
    {
        // Jamais de cookie en clair hors développement local.
        return (bool) (config('session.secure') ?? app()->environment('production'));
    }

    private function dureeJours(): int
    {
        return (int) config('security.auth.refresh_ttl_days', 14);
    }
}
