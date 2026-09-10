# 🔧 MISSION DEEPSEEK — Fix CI PR #37 (step:9 "Run migrations" exit code 1)
## EduGest DZ · Branche : develop · 9 Juillet 2026
## Run #210 — Total duration 48s — Failure à step:9 migrations

---

## DIAGNOSTIC EXACT (lu dans GitHub)

### Cause unique trouvée

La migration `2026_07_09_100000_add_kill_switch_fallback_to_kill_switch_votes.php`
fait `Schema::table('kill_switch_votes', ...)` MAIS la table `kill_switch_votes`
**n'a pas de migration de création dans le repo**.

La CI repart d'une base vide → `kill_switch_votes` n'existe pas →
`Schema::table()` sur une table inexistante → **erreur PostgreSQL → exit code 1**.

```
Preuve : Tous les fichiers de migration listés dans le repo commencent par 0001, 0002...
jusqu'à 2026_07_08_900000. La table kill_switch_votes vient d'une migration
qui existait sur main mais la liste des fichiers montre qu'il n'y a
PAS de migration create_kill_switch_votes dans les fichiers visibles.
→ Schema::table() sur table inexistante = CRASH garanti en CI (base vide)
```

---

## FIX UNIQUE — Réécrire la migration pour qu'elle soit auto-suffisante

**Remplacer entièrement** :
`edugestdz/backend/database/migrations/2026_07_09_100000_add_kill_switch_fallback_to_kill_switch_votes.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Table kill_switch_votes ────────────────────────────────────────
        // Créer si n'existe pas (auto-suffisant en CI qui repart d'une base vide)
        if (!Schema::hasTable('kill_switch_votes')) {
            Schema::create('kill_switch_votes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('initiator_id');
                $table->uuid('approver_id')->nullable();
                $table->string('action');
                $table->json('payload')->nullable();
                $table->string('status')->default('pending');
                // Colonnes fallback BDD pour persistance sans Redis
                $table->timestamp('active_since')->nullable();
                $table->timestamp('deactivated_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'expires_at'], 'idx_ksv_status');
                $table->index(['initiator_id', 'action'], 'idx_ksv_initiator');
            });
        } else {
            // Table existe déjà → ajouter seulement les colonnes manquantes
            Schema::table('kill_switch_votes', function (Blueprint $table) {
                if (!Schema::hasColumn('kill_switch_votes', 'active_since')) {
                    $table->timestamp('active_since')->nullable()->after('status');
                }
                if (!Schema::hasColumn('kill_switch_votes', 'deactivated_at')) {
                    $table->timestamp('deactivated_at')->nullable()->after('active_since');
                }
            });
        }

        // ── Table kill_switch_state ────────────────────────────────────────
        // Table d'état global du KillSwitch (fallback BDD si Redis down)
        if (!Schema::hasTable('kill_switch_state')) {
            Schema::create('kill_switch_state', function (Blueprint $table) {
                $table->id();
                $table->boolean('is_active')->default(false);
                $table->string('reason')->nullable();
                $table->string('activated_by')->nullable();
                $table->string('approved_by')->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('deactivated_at')->nullable();
                $table->timestamps();
            });

            // État initial : KillSwitch inactif
            \DB::table('kill_switch_state')->insert([
                'is_active'  => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kill_switch_state');
        Schema::dropIfExists('kill_switch_votes');
    }
};
```

---

## VÉRIFICATION + COMMIT

```bash
cd edugestdz/backend

# Vérifier la syntaxe
php -l database/migrations/2026_07_09_100000_add_kill_switch_fallback_to_kill_switch_votes.php

# Tester les migrations depuis zéro (simule le CI)
php artisan migrate:fresh --force
# → Doit passer sans erreur

# Tests complets
php artisan test --parallel
# → 724+ ✅  0 failures

git add database/migrations/2026_07_09_100000_add_kill_switch_fallback_to_kill_switch_votes.php

git commit -m "fix(ci): migration kill_switch auto-suffisante — créer kill_switch_votes si absent

La migration précédente faisait Schema::table('kill_switch_votes')
mais la table n'existe pas en CI (base vide).
Fix : Schema::hasTable() → créer si absent, ALTER TABLE si existante.
Idempotente dans les deux cas."

git push origin develop
# → CI doit passer ✅ → Merger PR #37
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin develop

PROBLÈME : La migration 2026_07_09_100000_add_kill_switch_fallback_to_kill_switch_votes.php
fait Schema::table('kill_switch_votes') mais la table n'existe pas en CI.

FIX : Remplacer ENTIÈREMENT ce fichier migration avec le contenu fourni.
Le nouveau contenu utilise Schema::hasTable() pour créer si absent,
ou Schema::hasColumn() pour ajouter si la table existe déjà.

php -l database/migrations/2026_07_09_100000_...php  # vérifier syntaxe
php artisan migrate:fresh --force                     # tester depuis zéro
php artisan test --parallel                           # 724+ verts
git add . && git commit -m "fix(ci): migration kill_switch auto-suffisante"
git push origin develop
```
