<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('parametres')) {
            Schema::create('parametres', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
                $table->uuid('tenant_id')->unique();
                $table->string('nom_ecole', 200)->nullable();
                $table->string('logo_chemin', 500)->nullable();
                $table->string('couleur_principale', 7)->default('#2563eb');
                $table->string('couleur_secondaire', 7)->default('#1e293b');
                $table->string('telephone', 20)->nullable();
                $table->string('email_contact', 200)->nullable();
                $table->string('adresse', 500)->nullable();
                $table->string('ville', 100)->nullable();
                $table->integer('wilaya_id')->nullable();
                $table->jsonb('horaires_ouverture')->nullable();
                $table->jsonb('niveaux_scolaires_custom')->nullable();
                $table->jsonb('tarifs_defaut')->nullable();
                $table->jsonb('smtp_config')->nullable();
                $table->string('devise', 10)->default('DA');
                $table->string('langue_defaut', 5)->default('fr');
                $table->string('fuseau_horaire', 50)->default('Africa/Algiers');
                $table->text('mentions_legales')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('parametres', function (Blueprint $table) {
                $cols = [
                    'couleur_principale'      => fn() => $table->string('couleur_principale', 7)->default('#2563eb')->nullable(),
                    'couleur_secondaire'      => fn() => $table->string('couleur_secondaire', 7)->default('#1e293b')->nullable(),
                    'horaires_ouverture'      => fn() => $table->jsonb('horaires_ouverture')->nullable(),
                    'niveaux_scolaires_custom'=> fn() => $table->jsonb('niveaux_scolaires_custom')->nullable(),
                    'tarifs_defaut'           => fn() => $table->jsonb('tarifs_defaut')->nullable(),
                    'smtp_config'             => fn() => $table->jsonb('smtp_config')->nullable(),
                    'devise'                  => fn() => $table->string('devise', 10)->default('DA')->nullable(),
                    'fuseau_horaire'          => fn() => $table->string('fuseau_horaire', 50)->default('Africa/Algiers')->nullable(),
                    'mentions_legales'        => fn() => $table->text('mentions_legales')->nullable(),
                ];
                foreach ($cols as $col => $fn) {
                    if (!Schema::hasColumn('parametres', $col)) $fn();
                }
            });
        }
    }

    public function down(): void {}
};
