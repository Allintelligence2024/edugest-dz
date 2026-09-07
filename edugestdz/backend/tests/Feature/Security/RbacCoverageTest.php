<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sprint 2 — Couverture RBAC.
 *
 * Ce test est le filet de sécurité du sprint : il inspecte la table de routage
 * et échoue dès qu'une route sensible perd son contrôle d'accès par rôle.
 *
 * Contexte : avant le Sprint 2, 75 contrôleurs n'étaient protégés que par
 * `auth:api` + `resolve.tenant`. L'isolation multi-tenant empêchait bien
 * l'école A de voir l'école B, mais À L'INTÉRIEUR d'une école n'importe quel
 * compte authentifié (y compris un parent) pouvait appeler /paies, /finance,
 * /audit-logs ou /personnel. Ce test empêche la régression.
 */
class RbacCoverageTest extends TestCase
{
    /**
     * Préfixes d'URI dont TOUTE route doit porter un contrôle de rôle
     * (`role:`, `permission:` ou `super_admin`).
     */
    private const PREFIXES_SENSIBLES = [
        'api/v1/paies',
        'api/v1/factures',
        'api/v1/finance',
        'api/v1/budget',
        'api/v1/tarifs',
        'api/v1/plans-fractionnement',
        'api/v1/personnel',
        'api/v1/contrats',
        'api/v1/audit-logs',
        'api/v1/rgpd',
        'api/v1/evaluations',
        'api/v1/entretien',
        'api/v1/pointage',
        'api/v1/surveillance',
        'api/v1/absences-enseignants',
        'api/v1/super-admin',
    ];

    /**
     * Routes publiques par conception : authentification, santé, webhooks de
     * prestataires (signature vérifiée dans le contrôleur), vitrine
     * marketplace et déclencheurs cron (protégés par CRON_SECRET).
     */
    private const PUBLIQUES_ATTENDUES = [
        'api/v1/auth',
        'api/v1/health',
        'api/v1/cron',
        'api/v1/marketplace',
        'api/v1/whatsapp/webhook',
        'api/v1/google/classroom/callback',
        'api/v1/surveillance/webhook',
        'api/v1/paiements/online/callback',
        'api/v1/paiements/online/retour',
    ];

    /** @return list<array{uri:string,methods:string,middleware:list<string>}> */
    private function routesApi(): array
    {
        $resultat = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (!Str::startsWith($uri, 'api/')) {
                continue;
            }

            $resultat[] = [
                'uri'        => $uri,
                'methods'    => implode('|', array_diff($route->methods(), ['HEAD'])),
                'middleware' => $route->gatherMiddleware(),
            ];
        }

        return $resultat;
    }

    private function aControleDeRole(array $middleware): bool
    {
        foreach ($middleware as $m) {
            if (!is_string($m)) {
                continue;
            }

            // `gatherMiddleware()` renvoie l'alias tel qu'écrit sur la route
            // (« super_admin »), pas la classe résolue : chercher uniquement
            // « SuperAdmin » ne matchait jamais et signalait les routes
            // super-admin comme non protégées.
            if (Str::startsWith($m, ['role:', 'permission:'])
                || $m === 'super_admin'
                || Str::contains($m, ['RoleCheck', 'PermissionCheck', 'SuperAdmin'])) {
                return true;
            }
        }

        return false;
    }

    private function estAuthentifiee(array $middleware): bool
    {
        foreach ($middleware as $m) {
            if (is_string($m) && (Str::startsWith($m, 'auth:') || Str::contains($m, 'Authenticate'))) {
                return true;
            }
        }

        return false;
    }

    /** Les alias `role` et `permission` doivent être enregistrés. */
    public function test_les_middlewares_rbac_sont_enregistres(): void
    {
        $aliases = app(\Illuminate\Foundation\Http\Kernel::class)->getRouteMiddleware();

        $this->assertArrayHasKey('role', $aliases);
        $this->assertArrayHasKey('permission', $aliases);
        $this->assertSame(\App\Http\Middleware\RoleCheck::class, $aliases['role']);
        $this->assertSame(\App\Http\Middleware\PermissionCheck::class, $aliases['permission']);
    }

    /** Aucune route sensible ne doit se contenter de `auth:api`. */
    public function test_toutes_les_routes_sensibles_ont_un_controle_de_role(): void
    {
        $manquantes = [];

        foreach ($this->routesApi() as $route) {
            $sensible = Str::startsWith($route['uri'], self::PREFIXES_SENSIBLES);

            if ($sensible && !$this->aControleDeRole($route['middleware'])) {
                $manquantes[] = "{$route['methods']} /{$route['uri']}";
            }
        }

        $this->assertSame([], $manquantes, sprintf(
            "%d route(s) sensible(s) sans contrôle de rôle :\n  - %s",
            count($manquantes),
            implode("\n  - ", $manquantes)
        ));
    }

    /** Aucune route non déclarée publique ne doit être ouverte sans authentification. */
    public function test_aucune_route_non_authentifiee_inattendue(): void
    {
        $ouvertes = [];

        foreach ($this->routesApi() as $route) {
            if ($this->estAuthentifiee($route['middleware'])) {
                continue;
            }

            if (Str::startsWith($route['uri'], self::PUBLIQUES_ATTENDUES)) {
                continue;
            }

            // Les honeypots sont volontairement exposés (pièges à scanners).
            if (Str::contains(implode(',', array_filter($route['middleware'], 'is_string')), 'Honeypot')) {
                continue;
            }

            $ouvertes[] = "{$route['methods']} /{$route['uri']}";
        }

        $this->assertSame([], $ouvertes, sprintf(
            "%d route(s) accessible(s) SANS authentification :\n  - %s",
            count($ouvertes),
            implode("\n  - ", $ouvertes)
        ));
    }

    /** Toute route authentifiée doit aussi résoudre un tenant (sauf exceptions). */
    public function test_les_routes_authentifiees_resolvent_un_tenant(): void
    {
        $sansTenant = [];

        $exemptes = [
            'api/v1/auth',        // logout / me / refresh
            'api/v1/modules',     // catalogue des modules
            'api/v1/super-admin', // plateforme, hors tenant
            'api/v1/2fa',
        ];

        foreach ($this->routesApi() as $route) {
            if (!$this->estAuthentifiee($route['middleware'])) {
                continue;
            }

            if (Str::startsWith($route['uri'], $exemptes)) {
                continue;
            }

            $chaine = implode(',', array_filter($route['middleware'], 'is_string'));

            if (!Str::contains($chaine, ['ResolveTenant', 'resolve.tenant'])) {
                $sansTenant[] = "{$route['methods']} /{$route['uri']}";
            }
        }

        $this->assertSame([], $sansTenant, sprintf(
            "%d route(s) authentifiée(s) sans résolution de tenant :\n  - %s",
            count($sansTenant),
            implode("\n  - ", $sansTenant)
        ));
    }
}
