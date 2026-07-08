<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'eleves', 'users', 'groupes', 'cours', 'seances',
            'presences', 'evaluations', 'notes', 'bulletins',
            'factures', 'paiements', 'absences_journalieres', 'billets',
            'enseignants', 'contrats', 'personnel_non_enseignant', 'paies',
            'circuits_transport', 'transport_eleves', 'pointage_bus',
            'menus_cantine', 'inscriptions_cantine', 'repas_journaliers',
            'articles_stock', 'mouvements_stock', 'prets_stock',
            'bons_commande', 'depenses', 'budgets_previsionnels',
            'locaux', 'interventions_entretien', 'entretiens_preventifs',
            'cameras_config', 'alertes_surveillance',
            'lms_cours', 'lms_inscriptions',
            'tenant_modules', 'whatsapp_messages',
            'diagnostics_eleves', 'plans_rattrapage', 'convocations_parents',
            'signalements_comportement', 'notifications_parent',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            if (!Schema::hasColumn($table, 'tenant_id') && !$this->isPivotOrCore($table)) {
                Schema::table($table, function (Blueprint $t) {
                    $t->uuid('tenant_id')->nullable()->index();
                });
            }

            if (!Schema::hasColumn($table, 'created_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->timestamps();
                });
            }

            if (!Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->softDeletes();
                });
            }
        }

        if (Schema::hasTable('users')) {
            $userColumns = [
                'two_factor_secret' => function (Blueprint $t) { $t->text('two_factor_secret')->nullable(); },
                'two_factor_recovery_codes' => function (Blueprint $t) { $t->text('two_factor_recovery_codes')->nullable(); },
                'two_factor_confirmed_at' => function (Blueprint $t) { $t->timestamp('two_factor_confirmed_at')->nullable(); },
                'two_factor_type' => function (Blueprint $t) { $t->string('two_factor_type', 10)->nullable(); },
                'login_attempts' => function (Blueprint $t) { $t->unsignedTinyInteger('login_attempts')->default(0); },
                'locked_until' => function (Blueprint $t) { $t->timestamp('locked_until')->nullable(); },
                'two_factor_phone' => function (Blueprint $t) { $t->string('two_factor_phone', 20)->nullable(); },
                'telephone' => function (Blueprint $t) { $t->string('telephone', 20)->nullable(); },
                'avatar_url' => function (Blueprint $t) { $t->string('avatar_url', 500)->nullable(); },
                'theme' => function (Blueprint $t) { $t->string('theme')->default('light'); },
                'derniere_connexion' => function (Blueprint $t) { $t->dateTime('derniere_connexion')->nullable(); },
                'email_verified_at' => function (Blueprint $t) { $t->dateTime('email_verified_at')->nullable(); },
            ];

            foreach ($userColumns as $column => $callback) {
                if (!Schema::hasColumn('users', $column)) {
                    Schema::table('users', function (Blueprint $t) use ($callback) {
                        $callback($t);
                    });
                }
            }
        }

        if (Schema::hasTable('audit_logs')) {
            $auditColumns = [
                'log_name', 'description', 'subject_type', 'subject_id',
                'causer_type', 'causer_id', 'properties',
            ];
            foreach ($auditColumns as $col) {
                if (!Schema::hasColumn('audit_logs', $col)) {
                    Schema::table('audit_logs', function (Blueprint $t) use ($col) {
                        if (in_array($col, ['properties'])) {
                            $t->json($col)->nullable();
                        } elseif ($col === 'causer_id') {
                            $t->string($col, 36)->nullable();
                        } else {
                            $t->string($col)->nullable();
                        }
                    });
                }
            }
        }
    }

    private function isPivotOrCore(string $table): bool
    {
        $core = [
            'cache', 'jobs', 'job_batches', 'failed_jobs',
            'sessions', 'password_reset_tokens', 'personal_access_tokens',
            'role_permissions', 'user_permissions',
            'telescope_entries', 'telescope_entries_tags', 'telescope_monitoring',
        ];
        return in_array($table, $core);
    }

    public function down(): void
    {
    }
};
