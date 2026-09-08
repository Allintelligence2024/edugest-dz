# 🚨 FIX CI — Surveillance Dahua : push failing 1/2
## EduGest DZ · 4 Juillet 2026 · URGENT

---

## SYMPTÔME

PR #17 :
- ✅ CI pull_request : 2/2 (vert)
- ❌ CI push : 1/2 (rouge) — dernier commit "feat(surveillance)"

Le check "push" déclenché sur `develop` échoue.
Le check "pull_request" passe.

Différence : le push CI ne bénéficie pas du même contexte env que le pull_request CI.

---

## ÉTAPE 1 — Identifier l'erreur exacte

```bash
cd edugestdz/backend
php artisan test --parallel 2>&1 | grep -A 5 "FAIL\|Error\|Exception"
```

Copier les lignes d'erreur. Les causes les plus probables :

---

## CAS A — Table 'cameras_config' inexistante en CI push

Le CI push tourne sur `develop` sans forcément avoir toutes les migrations.

**Fix :**
```bash
php artisan migrate:fresh --seed --force
php artisan test --parallel
```

Si ça passe → le problème est le cache de migration CI.

**Fix permanent dans le test :**

Dans `SurveillanceControllerTest.php`, vérifier que `use RefreshDatabase;` est présent.
Si oui, vérifier que la migration `2026_07_03_100000_create_surveillance_tables.php` existe bien.

```bash
ls edugestdz/backend/database/migrations/ | grep surveillance
```

Si le fichier n'existe pas → le créer depuis `MISSION_SURVEILLANCE_DAHUA.md` étape 1.

---

## CAS B — FirebaseService non résolu (class not found)

`DahuaWebhookService` injecte `FirebaseService` — si la classe n'existe pas encore :

```bash
grep -r "FirebaseService" edugestdz/backend/app/Services/
```

Si absent → créer le stub minimal :

**Créer :** `edugestdz/backend/app/Services/FirebaseService.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FirebaseService
{
    public function sendNotification(string|array $tokens, string $title, string $body, array $data = []): bool
    {
        Log::info("Firebase push (stub): {$title} — {$body}");
        return false; // Retourne false tant que FIREBASE_SERVER_KEY n'est pas configuré
    }

    public function notifyUser(int|string $userId, string $title, string $body, array $data = []): bool
    {
        $tokens = \App\Models\DeviceToken::where('user_id', $userId)
            ->where('actif', true)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) return false;
        return $this->sendNotification($tokens, $title, $body, $data);
    }

    public function notifyParentsEleve(string $eleveId, string $title, string $body, array $data = []): void
    {
        $eleve = \App\Models\Eleve::with('parents:id')->find($eleveId);
        if (!$eleve) return;

        foreach ($eleve->parents as $parent) {
            $this->notifyUser($parent->id, $title, $body, $data);
        }
    }
}
```

---

## CAS C — SmsService non résolu

```bash
grep -r "class SmsService" edugestdz/backend/app/Services/
```

Si absent → créer le stub :

**Créer :** `edugestdz/backend/app/Services/SmsService.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send(string $telephone, string $message): bool
    {
        Log::info("SMS (stub): {$telephone} → {$message}");
        // En production : utiliser Twilio
        // $twilio = new \Twilio\Rest\Client(config('services.twilio.sid'), config('services.twilio.token'));
        // $twilio->messages->create($telephone, ['from' => config('services.twilio.from'), 'body' => $message]);
        return true;
    }
}
```

---

## CAS D — DeviceToken model absent

```bash
grep -r "class DeviceToken" edugestdz/backend/app/Models/
```

Si absent → créer le stub :

**Créer :** `edugestdz/backend/app/Models/DeviceToken.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DeviceToken extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'token', 'platform', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

Et créer la migration si la table n'existe pas :

**Créer :** `edugestdz/backend/database/migrations/2026_07_04_000001_create_device_tokens_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('device_tokens')) return;

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('user_id');
            $table->string('token');
            $table->string('platform')->default('android'); // android | ios
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'actif'], 'idx_device_tokens_user');
            $table->unique('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
```

---

## CAS E — Test SurveillanceControllerTest : config tenant null

Le test `test_traiter_alerte` utilise `config('tenant.current_id', Str::uuid())`.
En CI push le tenant_id peut être null et causer une violation de contrainte.

**Fix dans le test :**

```php
// Remplacer dans test_traiter_alerte() :
$tenantId = Str::uuid()->toString();
config(['tenant.current_id' => $tenantId]);

$camera = CameraConfig::create([
    'tenant_id' => $tenantId,
    'nom' => 'Test', 'serial_no' => 'S1', 'type' => 'entree', 'actif' => true,
]);
```

---

## CAS F — Rate limiter "webhook" non défini

Dans `routes/api.php` on a `->middleware('throttle:webhook')` mais le rate limiter
`webhook` n'est peut-être pas défini dans `AppServiceProvider`.

**Fix : remplacer `throttle:webhook` par `throttle:60,1`** dans routes/api.php :

```php
// Avant :
Route::post('/v1/surveillance/webhook', [SurveillanceController::class, 'recevoir'])
    ->middleware('throttle:webhook');

// Après (plus safe) :
Route::post('/v1/surveillance/webhook', [SurveillanceController::class, 'recevoir'])
    ->middleware('throttle:60,1'); // 60 req/min
```

---

## PROCÉDURE COMPLÈTE

```bash
cd edugestdz/backend

# 1. Voir l'erreur exacte
php artisan test --parallel 2>&1 | tail -60

# 2. Nettoyer
php artisan optimize:clear
composer dump-autoload -o

# 3. Remettre la base propre
php artisan migrate:fresh --seed --force

# 4. Relancer les tests
php artisan test --parallel

# 5. Si toujours rouge → corriger le test/fichier qui échoue
# 6. Quand tout est vert :
git add .
git commit -m "fix: CI push — résoudre erreurs Surveillance (FirebaseService stub + migrations + throttle)"
git push origin develop
```

---

## CE QUE TU DIS À DEEPSEEK

```
Le CI push échoue sur PR #17 (develop → main).
Le CI pull_request passe ✅.

Actions à faire dans l'ordre :

1. cd edugestdz/backend
2. php artisan test --parallel 2>&1 | tail -80
   → Copier TOUTES les lignes d'erreur ici

3. Corriger chaque erreur :
   - Si "class not found FirebaseService" → créer le stub (voir MISSION_FIX_CI_SURVEILLANCE.md)
   - Si "class not found SmsService" → créer le stub
   - Si "table not found" → php artisan migrate:fresh --seed --force
   - Si "throttle:webhook undefined" → remplacer par throttle:60,1 dans routes/api.php
   - Si "tenant.current_id null" → corriger le test SurveillanceControllerTest

4. php artisan test --parallel → 0 failed obligatoire

5. git add . && git commit -m "fix: CI push Surveillance" && git push origin develop

RÈGLE : PostgreSQL uniquement, 0 régression.
```
