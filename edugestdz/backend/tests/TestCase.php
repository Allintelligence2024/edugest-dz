<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn() => true);

        // ── GUARD ANTI-SQLite ──────────────────────────────────────────
        // Bloquer immédiatement si on tourne sur SQLite en test.
        // EduGest DZ utilise des features PostgreSQL-spécifiques :
        // RLS, jsonb, gen_random_uuid(), SAVEPOINT, SHA3.
        // Tourner sur SQLite = faux sentiment de sécurité.
        $connection = config('database.default');
        if ($connection === 'sqlite') {
            $this->fail(
                "\n\n" .
                "❌ ERREUR : Les tests tournent sur SQLite !\n" .
                "EduGest DZ nécessite PostgreSQL 16.\n\n" .
                "Solution :\n" .
                "  1. Vérifier que PostgreSQL tourne localement\n" .
                "  2. Créer la base : createdb edugestdz_test\n" .
                "  3. Créer l'utilisateur : createuser edugest_user\n" .
                "  4. Relancer : php artisan test\n\n" .
                "Ou utiliser Docker : docker compose up -d\n"
            );
        }

        // ── Vérifier la connexion PostgreSQL avant chaque test ─────────
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $this->fail(
                "\n\n" .
                "❌ Impossible de se connecter à PostgreSQL.\n" .
                "Host: " . config('database.connections.pgsql.host') . "\n" .
                "DB: "   . config('database.connections.pgsql.database') . "\n" .
                "Erreur: " . $e->getMessage() . "\n"
            );
        }

        // ── GUARD 3 : Réinitialiser le contexte tenant entre les tests ──
        config(['tenant.current_id' => null]);
    }
}
