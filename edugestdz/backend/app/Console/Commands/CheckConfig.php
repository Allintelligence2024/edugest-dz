<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class CheckConfig extends Command
{
    protected $signature = 'edugest:check-config
                            {--secrets-only : Vérifier uniquement les clés secrètes}';

    protected $description = 'Vérifie la configuration critique d\'EduGest DZ (clés, services, BDD)';

    private array $results = [];

    public function handle(): int
    {
        $this->newLine();
        $this->components->twoColumnDetail('<fg=yellow>╔═══ EduGest DZ — Rapport de Configuration</>', '');
        $this->newLine();

        $this->checkSecret('APP_KEY', 'Chiffrement Laravel');
        $this->checkSecret('JWT_SECRET', 'Signature JWT');
        $this->checkDatabase();
        $this->checkRedis();
        $this->checkTimezone();
        $this->checkCacheDriver();
        $this->checkDebugMode();
        $this->checkMailFrom();
        $this->checkQueue();

        if (!$this->option('secrets-only')) {
            $this->checkOptional('SATIM_TERMINAL_ID', 'Satim (paiement CIB)');
            $this->checkOptional('TWILIO_SID', 'Twilio (SMS)');
            $this->checkOptional('FIREBASE_PROJECT_ID', 'Firebase (push)');
            $this->checkOptional('WHATSAPP_API_TOKEN', 'WhatsApp Business');
        }

        $this->newLine();
        $this->renderSummary();

        return $this->hasFails() ? Command::FAILURE : Command::SUCCESS;
    }

    // ─────────────────────────────────────────────────────

    private function checkSecret(string $key, string $label): void
    {
        $value = env($key);
        if (empty($value)) {
            $this->addFail($label, "$key non définie — exécutez la commande de génération");
        } elseif (strlen($value) < 16) {
            $this->addWarn($label, "$key semble trop courte (< 16 caractères)");
        } else {
            $this->addPass($label, "$key définie ✓");
        }
    }

    private function checkDatabase(): void
    {
        $driver = config('database.default');
        $host = config("database.connections.$driver.host");
        $port = config("database.connections.$driver.port");

        if ($driver !== 'pgsql') {
            $this->addWarn('Driver BDD', "Driver = $driver (attendu: pgsql)");
        } elseif (empty(env('DB_DATABASE'))) {
            $this->addFail('Base de données', 'DB_DATABASE non définie');
        } elseif (empty(env('DB_USERNAME'))) {
            $this->addFail('Base de données', 'DB_USERNAME non définie');
        } else {
            $this->addPass('Base de données', "$driver://$host:$port / " . env('DB_DATABASE', '?'));
        }
    }

    private function checkRedis(): void
    {
        $host = config('database.redis.default.host');
        $port = config('database.redis.default.port');

        if (empty($host)) {
            $this->addWarn('Redis', 'Hôte non configuré');
        } else {
            $this->addPass('Redis', "redis://$host:$port");

            $defaultDriver = config('cache.default');
            if ($defaultDriver !== 'redis') {
                $this->addWarn('Cache', "CACHE_STORE = $defaultDriver (attendu: redis)");
            } else {
                $this->addPass('Cache', 'CACHE_STORE = redis');
            }
        }
    }

    private function checkTimezone(): void
    {
        $tz = config('app.timezone');
        if ($tz !== 'Africa/Algiers') {
            $this->addWarn('Fuseau horaire', "APP_TIMEZONE = $tz (recommandé: Africa/Algiers)");
        } else {
            $this->addPass('Fuseau horaire', $tz);
        }
    }

    private function checkCacheDriver(): void
    {
        $store = env('CACHE_STORE');
        $legacy = env('CACHE_DRIVER');

        if ($store && $legacy) {
            $this->addWarn('Cache', "CACHE_STORE=$store et CACHE_DRIVER=$legacy — utiliser uniquement CACHE_STORE");
        } elseif (empty($store) && $legacy) {
            $this->addWarn('Cache', "Utiliser CACHE_STORE au lieu de CACHE_DRIVER (déprécié)");
        } elseif (empty($store)) {
            $this->addWarn('Cache', 'CACHE_STORE non défini (sera redirigé vers CACHE_DRIVER si présent)');
        } else {
            $this->addPass('Cache', "CACHE_STORE = $store");
        }
    }

    private function checkDebugMode(): void
    {
        $debug = env('APP_DEBUG', false);
        $env = env('APP_ENV', 'production');

        if ($env === 'production' && $debug === true) {
            $this->addFail('Sécurité', 'APP_DEBUG=true en production — désactiver');
        } elseif ($env === 'production') {
            $this->addPass('Sécurité', 'APP_DEBUG désactivé');
        } else {
            $this->addPass('Sécurité', "APP_DEBUG=true (env $env)");
        }
    }

    private function checkMailFrom(): void
    {
        $from = env('MAIL_FROM_ADDRESS');
        if (empty($from)) {
            $this->addWarn('Email', 'MAIL_FROM_ADDRESS non définie');
        } else {
            $this->addPass('Email', "De: $from");
        }
    }

    private function checkQueue(): void
    {
        $conn = config('queue.default');
        $this->addPass('Queue', "default = $conn");
    }

    private function checkOptional(string $key, string $label): void
    {
        $value = env($key);
        if (empty($value)) {
            $this->skip($label, "$key non configurée (optionnel)");
        } else {
            $this->addPass($label, "$key définie ✓");
        }
    }

    // ─────────────────────────────────────────────────────

    private function addPass(string $label, string $message): void
    {
        $this->results[] = ['status' => 'pass', 'label' => $label, 'message' => $message];
        $this->components->twoColumnDetail(
            "  <fg=green>✓</> $label",
            "<fg=green>$message</>"
        );
    }

    private function addWarn(string $label, string $message): void
    {
        $this->results[] = ['status' => 'warn', 'label' => $label, 'message' => $message];
        $this->components->twoColumnDetail(
            "  <fg=yellow>⚠</> $label",
            "<fg=yellow>$message</>"
        );
    }

    private function addFail(string $label, string $message): void
    {
        $this->results[] = ['status' => 'fail', 'label' => $label, 'message' => $message];
        $this->components->twoColumnDetail(
            "  <fg=red>✗</> $label",
            "<fg=red>$message</>"
        );
    }

    private function skip(string $label, string $message): void
    {
        $this->results[] = ['status' => 'skip', 'label' => $label, 'message' => $message];
        $this->components->twoColumnDetail(
            "  <fg=gray>−</> $label",
            "<fg=gray>$message</>"
        );
    }

    private function hasFails(): bool
    {
        return collect($this->results)->contains('status', 'fail');
    }

    private function renderSummary(): void
    {
        $counts = collect($this->results)->groupBy('status')->map->count();
        $pass = $counts->get('pass', 0);
        $warn = $counts->get('warn', 0);
        $fail = $counts->get('fail', 0);
        $skip = $counts->get('skip', 0);

        $this->newLine();
        $this->components->twoColumnDetail(
            '<fg=cyan>═══ Résumé</>',
            "<fg=green>$pass ok</> · <fg=yellow>$warn warnings</> · <fg=red>$fail erreurs</> · <fg=gray>$skip optionnels</>"
        );

        if ($fail > 0) {
            $this->components->error('Corrigez les erreurs avant de démarrer l\'application.');
        } elseif ($warn > 0) {
            $this->components->warn('Des avertissements sont présents — vérifiez les points jaunes.');
        } else {
            $this->components->success('Configuration valide. EduGest DZ est prêt.');
        }
    }
}
