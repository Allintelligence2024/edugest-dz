<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * Classe de base pour tous les tests EduGest DZ.
 *
 * Guards actifs :
 * 1. Refuse de tourner sur SQLite (message clair)
 * 2. Réinitialise le tenant context entre les tests
 *
 * NOTE : Le guard DB::connection()->getPdo() a été retiré car :
 *   - Il est appelé dans setUp() AVANT que RefreshDatabase initialise la BDD
 *   - En mode parallèle, il peut faire échouer des tests valides
 *   - Le CI a déjà PostgreSQL configuré dans phpunit.xml — pas besoin de revérifier
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp(); // RefreshDatabase s'exécute ici

        // Gate::before pour les tests — permet à tous les rôles d'agir
        Gate::before(fn() => true);

        // ── Guard anti-SQLite ──────────────────────────────────────────
        // Ce guard est sûr car config() est disponible immédiatement
        $connection = config('database.default');
        if ($connection === 'sqlite') {
            $this->fail(
                "\n\n" .
                "❌ ERREUR : Les tests tournent sur SQLite — INTERDIT pour EduGest DZ\n\n" .
                "EduGest DZ utilise des fonctionnalités PostgreSQL exclusives :\n" .
                "  • RLS, jsonb, gen_random_uuid(), SAVEPOINT\n\n" .
                "Solution : démarrer PostgreSQL ou Docker\n" .
                "  docker compose up -d\n" .
                "  php artisan test --parallel\n"
            );
        }

        // ── Réinitialiser le contexte tenant entre les tests ──────────
        config(['tenant.current_id' => null]);

        // ── Nettoyer le cache KillSwitch (évite pollution parallèle) ──
        Cache::forget('kill_switch:active');

        // ── Nettoyer aussi la BDD KillSwitch (fallback Redis) ────────
        if (Schema::hasTable('kill_switch_state')) {
            DB::table('kill_switch_state')->where('is_active', true)->update([
                'is_active' => false,
                'deactivated_at' => now(),
            ]);
        }
    }
}
