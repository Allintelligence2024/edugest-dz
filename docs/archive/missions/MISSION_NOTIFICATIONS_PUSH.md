# 🤖 MISSION DEEPSEEK — Notifications Push Firebase + WhatsApp entrant + QR Code
## EduGest DZ · Branche : develop · 3 Juillet 2026
## Tests actuels : 423+ ✅ · Objectif : ≥ 435 ✅ · 0 régression

---

## CONTEXTE

3 fonctionnalités backend manquantes identifiées dans l'audit :
1. **Push Firebase** : envoi automatique quand absence, note, bulletin, réservation confirmée
2. **WhatsApp webhook** : traitement des messages entrants (parent répond "OUI" → justifie absence)
3. **QR Code** : génération + scan pour pointage élèves en séance

### RÈGLES
1. PostgreSQL uniquement — jamais SQLite
2. 0 régression — tous les tests existants restent verts
3. Ne pas modifier les contrôleurs existants — ajouter des Observers et Events

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## ÉTAPE 1 — Installer les packages

```bash
cd edugestdz/backend
composer require kreait/firebase-php:^7.0
composer require simplesoftwareio/simple-qrcode:^4.0
```

---

## ÉTAPE 2 — FirebaseService complet

**Créer :** `edugestdz/backend/app/Services/FirebaseService.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    private string $serverKey;
    private string $fcmUrl = 'https://fcm.googleapis.com/fcm/send';

    public function __construct()
    {
        $this->serverKey = config('services.firebase.server_key', '');
    }

    /**
     * Envoyer une notification push à un ou plusieurs tokens.
     */
    public function sendNotification(
        string|array $tokens,
        string $title,
        string $body,
        array $data = []
    ): bool {
        if (empty($this->serverKey)) {
            Log::warning('FirebaseService: FIREBASE_SERVER_KEY manquant');
            return false;
        }

        $tokens = (array) $tokens;
        if (empty($tokens)) return false;

        $payload = [
            'registration_ids' => $tokens,
            'notification' => [
                'title' => $title,
                'body'  => $body,
                'sound' => 'default',
                'badge' => 1,
            ],
            'data'     => array_merge($data, ['click_action' => 'FLUTTER_NOTIFICATION_CLICK']),
            'priority' => 'high',
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->serverKey,
                'Content-Type'  => 'application/json',
            ])->post($this->fcmUrl, $payload);

            $result = $response->json();
            $success = ($result['success'] ?? 0) > 0;

            if (!$success) {
                Log::warning('Firebase push échoué', $result);
            }

            return $success;
        } catch (\Throwable $e) {
            Log::error('FirebaseService exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoyer à tous les tokens d'un utilisateur.
     */
    public function notifyUser(int|string $userId, string $title, string $body, array $data = []): bool
    {
        $tokens = \App\Models\DeviceToken::where('user_id', $userId)
            ->where('actif', true)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) return false;
        return $this->sendNotification($tokens, $title, $body, $data);
    }

    /**
     * Envoyer aux parents d'un élève.
     */
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

## ÉTAPE 3 — Observer : absences → push parent

**Créer :** `edugestdz/backend/app/Observers/AbsenceJournaliereObserver.php`

```php
<?php

namespace App\Observers;

use App\Models\AbsenceJournaliere;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Log;

class AbsenceJournaliereObserver
{
    public function __construct(private FirebaseService $firebase) {}

    public function created(AbsenceJournaliere $absence): void
    {
        $eleve = $absence->eleve;
        if (!$eleve) return;

        $this->firebase->notifyParentsEleve(
            $eleve->id,
            '⚠️ Absence signalée',
            "{$eleve->prenom} {$eleve->nom} est absent(e) le {$absence->date_absence}.",
            ['type' => 'absence', 'eleve_id' => $eleve->id, 'absence_id' => $absence->id]
        );

        Log::info("Push absence envoyé pour élève {$eleve->id}");
    }
}
```

---

## ÉTAPE 4 — Observer : notes publiées → push parent

**Créer :** `edugestdz/backend/app/Observers/NoteObserver.php`

```php
<?php

namespace App\Observers;

use App\Models\Note;
use App\Services\FirebaseService;

class NoteObserver
{
    public function __construct(private FirebaseService $firebase) {}

    public function created(Note $note): void
    {
        if (!$note->note) return; // note vide, pas encore saisie

        $eleve = $note->eleve;
        if (!$eleve) return;

        $matiere = $note->evaluation?->matiere?->nom_fr ?? 'une matière';

        $this->firebase->notifyParentsEleve(
            $eleve->id,
            '📝 Nouvelle note',
            "{$eleve->prenom} a obtenu {$note->note}/20 en {$matiere}.",
            ['type' => 'note', 'eleve_id' => $eleve->id]
        );
    }
}
```

---

## ÉTAPE 5 — Observer : bulletin généré → push parent

**Créer :** `edugestdz/backend/app/Observers/BulletinObserver.php`

```php
<?php

namespace App\Observers;

use App\Models\Bulletin;
use App\Services\FirebaseService;

class BulletinObserver
{
    public function __construct(private FirebaseService $firebase) {}

    public function created(Bulletin $bulletin): void
    {
        $eleve = $bulletin->eleve;
        if (!$eleve) return;

        $this->firebase->notifyParentsEleve(
            $eleve->id,
            '📄 Bulletin disponible',
            "Le bulletin de {$eleve->prenom} ({$bulletin->trimestre}) est prêt. Moyenne : {$bulletin->moyenne_generale}/20.",
            ['type' => 'bulletin', 'eleve_id' => $eleve->id, 'bulletin_id' => $bulletin->id]
        );
    }
}
```

---

## ÉTAPE 6 — Observer : réservation marketplace confirmée → push parent

**Créer :** `edugestdz/backend/app/Observers/ReservationMarketplaceObserver.php`

```php
<?php

namespace App\Observers;

use App\Models\ReservationMarketplace;
use App\Services\FirebaseService;

class ReservationMarketplaceObserver
{
    public function __construct(private FirebaseService $firebase) {}

    public function updated(ReservationMarketplace $reservation): void
    {
        // Notifier seulement quand le statut passe à "confirmee"
        if ($reservation->statut !== 'confirmee') return;
        if ($reservation->getOriginal('statut') === 'confirmee') return;

        $offre = $reservation->offre;
        $date  = \Carbon\Carbon::parse($reservation->date_souhaitee)
            ->format('d/m/Y à H:i');

        $this->firebase->notifyUser(
            $reservation->parent_id,
            '✅ Réservation confirmée !',
            "Votre réservation pour « {$offre?->titre} » le {$date} est confirmée.",
            ['type' => 'reservation', 'reservation_id' => $reservation->id]
        );
    }
}
```

---

## ÉTAPE 7 — Enregistrer tous les observers dans AppServiceProvider

**Modifier :** `edugestdz/backend/app/Providers/AppServiceProvider.php`

Dans la méthode `boot()`, ajouter :

```php
// Observers push notifications
\App\Models\AbsenceJournaliere::observe(\App\Observers\AbsenceJournaliereObserver::class);
\App\Models\Note::observe(\App\Observers\NoteObserver::class);
\App\Models\Bulletin::observe(\App\Observers\BulletinObserver::class);

// ReservationMarketplace — seulement si la table existe (migration PR #15)
if (\Illuminate\Support\Facades\Schema::hasTable('reservations_marketplace')) {
    \App\Models\ReservationMarketplace::observe(\App\Observers\ReservationMarketplaceObserver::class);
}

// Observer EleveObserver déjà enregistré dans la mission perf — ne pas dupliquer
```

---

## ÉTAPE 8 — Config Firebase

**Modifier :** `edugestdz/backend/config/services.php`

Ajouter dans le tableau `return [...]` :

```php
'firebase' => [
    'server_key'      => env('FIREBASE_SERVER_KEY', ''),
    'project_id'      => env('FIREBASE_PROJECT_ID', ''),
    'credentials_file'=> env('FIREBASE_CREDENTIALS', storage_path('app/firebase-credentials.json')),
],
```

**Modifier :** `edugestdz/backend/.env.example`

Ajouter :
```dotenv
FIREBASE_SERVER_KEY=your_firebase_server_key_here
FIREBASE_PROJECT_ID=your_project_id_here
```

**Modifier :** `edugestdz/backend/.env.production.example`

Ajouter :
```dotenv
FIREBASE_SERVER_KEY=
FIREBASE_PROJECT_ID=
```

---

## ÉTAPE 9 — WhatsApp Webhook : traitement entrant

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/WhatsAppController.php`

Remplacer ou compléter la méthode `webhook()` :

```php
/**
 * Traiter les messages WhatsApp entrants (Twilio webhook).
 * Parent répond "OUI" → justifie automatiquement l'absence.
 * Parent répond "INFO" → reçoit le résumé de son enfant.
 */
public function webhook(Request $request): \Illuminate\Http\Response
{
    $from    = $request->input('From', '');     // Ex: "whatsapp:+213XXXXXXXXX"
    $body    = trim(strtoupper($request->input('Body', '')));
    $phone   = str_replace(['whatsapp:', '+213', '+'], ['', '0', ''], $from);

    \Illuminate\Support\Facades\Log::info("WhatsApp entrant: {$from} → {$body}");

    // Trouver le parent par numéro de téléphone
    $parent = \App\Models\User::where('telephone', 'LIKE', '%' . substr($phone, -9) . '%')
        ->where('role', 'parent')
        ->first();

    if (!$parent) {
        return $this->twimlResponse("Numéro non reconnu dans EduGest DZ. Contactez l'établissement.");
    }

    // Traiter la commande
    $reponse = match (true) {
        in_array($body, ['OUI', 'YES', 'JUSTIFIER', '1']) => $this->justifierDerniereAbsence($parent),
        in_array($body, ['INFO', '2'])                    => $this->getInfoEleve($parent),
        in_array($body, ['AIDE', 'HELP', '?'])            => $this->getAide(),
        default => "Commandes disponibles:\n✅ OUI — Justifier l'absence\nℹ️ INFO — Infos élève\n❓ AIDE — Aide",
    };

    return $this->twimlResponse($reponse);
}

private function justifierDerniereAbsence(\App\Models\User $parent): string
{
    // Trouver les enfants du parent
    $enfants = \App\Models\Eleve::whereHas('parents', fn($q) => $q->where('users.id', $parent->id))->get();

    if ($enfants->isEmpty()) {
        return "Aucun élève associé à votre compte.";
    }

    $eleve = $enfants->first();

    // Dernière absence non justifiée
    $absence = \App\Models\AbsenceJournaliere::where('eleve_id', $eleve->id)
        ->where('statut', 'non_justifiée')
        ->latest('date_absence')
        ->first();

    if (!$absence) {
        return "Aucune absence non justifiée pour {$eleve->prenom}.";
    }

    $absence->update([
        'statut'      => 'justifiée',
        'motif'       => 'Justifiée par parent via WhatsApp',
        'justifie_le' => now(),
    ]);

    return "✅ L'absence de {$eleve->prenom} du {$absence->date_absence} a été justifiée. Merci.";
}

private function getInfoEleve(\App\Models\User $parent): string
{
    $enfants = \App\Models\Eleve::whereHas('parents', fn($q) => $q->where('users.id', $parent->id))
        ->withCount([
            'presences as nb_absences' => fn($q) => $q->where('statut', 'absent'),
        ])
        ->get();

    if ($enfants->isEmpty()) return "Aucun élève associé.";

    $msgs = $enfants->map(fn($e) =>
        "👤 {$e->prenom} {$e->nom}\n"
        . "  Niveau: {$e->niveau_scolaire}\n"
        . "  Absences: {$e->nb_absences}"
    );

    return "Résumé EduGest:\n" . $msgs->implode("\n\n");
}

private function getAide(): string
{
    return "EduGest DZ — Commandes WhatsApp:\n"
        . "OUI → Justifier dernière absence\n"
        . "INFO → Résumé de votre enfant\n"
        . "AIDE → Ce message\n\n"
        . "Support: support@edugest.dz";
}

private function twimlResponse(string $message): \Illuminate\Http\Response
{
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>"
         . "<Response><Message>{$message}</Message></Response>";

    return response($xml, 200, ['Content-Type' => 'text/xml']);
}
```

---

## ÉTAPE 10 — QR Code : génération et scan présences

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/EleveController.php`

Ajouter la méthode `qrCode()` :

```php
/**
 * @OA\Get(
 *     path="/api/v1/eleves/{id}/qr-code",
 *     summary="Générer le QR code de pointage d'un élève",
 *     tags={"Eleves"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(response=200, description="Image QR code PNG",
 *         @OA\MediaType(mediaType="image/png"))
 * )
 */
public function qrCode(string $id): \Illuminate\Http\Response
{
    $eleve = \App\Models\Eleve::findOrFail($id);

    // Payload signé — tenant_id + eleve_id + timestamp (empêche la falsification)
    $payload = base64_encode(json_encode([
        'eleve_id'  => $eleve->id,
        'tenant_id' => $eleve->tenant_id,
        'ts'        => now()->timestamp,
        'sig'       => hash_hmac('sha256', $eleve->id . $eleve->tenant_id, config('app.key')),
    ]));

    $qr = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
        ->size(300)
        ->generate($payload);

    return response($qr, 200, [
        'Content-Type'        => 'image/png',
        'Content-Disposition' => "inline; filename=\"qr-{$eleve->id}.png\"",
    ]);
}
```

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/PresenceController.php`

Ajouter la méthode `scanQR()` :

```php
/**
 * @OA\Post(
 *     path="/api/v1/presences/qr-scan",
 *     summary="Scanner un QR code pour pointer une présence",
 *     tags={"Presences"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"payload","seance_id"},
 *         @OA\Property(property="payload",   type="string", description="Contenu du QR code scanné"),
 *         @OA\Property(property="seance_id", type="string", format="uuid"),
 *         @OA\Property(property="statut",    type="string", enum={"présent","retard"}, default="présent")
 *     )),
 *     @OA\Response(response=201, description="Présence enregistrée"),
 *     @OA\Response(response=422, description="QR code invalide ou expiré")
 * )
 */
public function scanQR(Request $request): \Illuminate\Http\JsonResponse
{
    $validated = $request->validate([
        'payload'   => 'required|string',
        'seance_id' => 'required|uuid|exists:seances,id',
        'statut'    => 'in:présent,retard',
    ]);

    // Décoder et vérifier le payload
    $data = json_decode(base64_decode($validated['payload']), true);

    if (!$data || !isset($data['eleve_id'], $data['sig'], $data['ts'])) {
        return response()->json(['success' => false, 'message' => 'QR code invalide.'], 422);
    }

    // Vérifier la signature
    $expectedSig = hash_hmac('sha256', $data['eleve_id'] . $data['tenant_id'], config('app.key'));
    if (!hash_equals($expectedSig, $data['sig'])) {
        return response()->json(['success' => false, 'message' => 'QR code falsifié.'], 422);
    }

    // QR code valide 24h max
    if (now()->timestamp - $data['ts'] > 86400) {
        return response()->json(['success' => false, 'message' => 'QR code expiré (valide 24h).'], 422);
    }

    // Vérifier isolation tenant
    if ($data['tenant_id'] !== config('tenant.current_id')) {
        return response()->json(['success' => false, 'message' => 'QR code d\'un autre établissement.'], 422);
    }

    // Enregistrer la présence (updateOrCreate évite les doublons)
    $presence = \App\Models\Presence::updateOrCreate(
        ['eleve_id' => $data['eleve_id'], 'seance_id' => $validated['seance_id']],
        ['statut' => $validated['statut'] ?? 'présent', 'pointe_le' => now()]
    );

    $eleve = \App\Models\Eleve::find($data['eleve_id']);

    return response()->json([
        'success' => true,
        'data'    => ['presence' => $presence, 'eleve' => $eleve?->nom_complet],
        'message' => "✅ {$eleve?->prenom} {$eleve?->nom} — présence enregistrée",
    ], 201);
}
```

**Ajouter les routes :**

```php
// Dans le groupe auth:api + tenant
Route::get('/eleves/{id}/qr-code',  [EleveController::class, 'qrCode']);
Route::post('/presences/qr-scan',   [PresenceController::class, 'scanQR']);
```

---

## ÉTAPE 11 — Tests

**Créer :** `edugestdz/backend/tests/Feature/PushNotificationsTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Eleve;
use App\Models\AbsenceJournaliere;
use App\Services\FirebaseService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class PushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_firebase_service_retourne_false_sans_cle(): void
    {
        config(['services.firebase.server_key' => '']);
        $service = new FirebaseService();
        $result  = $service->sendNotification('fake-token', 'Test', 'Corps');
        $this->assertFalse($result);
    }

    public function test_firebase_notify_user_sans_tokens_retourne_false(): void
    {
        $service = new FirebaseService();
        $result  = $service->notifyUser(999999, 'Test', 'Corps');
        $this->assertFalse($result);
    }

    public function test_whatsapp_webhook_numero_inconnu(): void
    {
        $this->postJson('/api/v1/whatsapp/webhook', [
            'From' => 'whatsapp:+213000000000',
            'Body' => 'OUI',
        ])->assertStatus(200);
    }

    public function test_whatsapp_aide_commande(): void
    {
        $this->postJson('/api/v1/whatsapp/webhook', [
            'From' => 'whatsapp:+213555000000',
            'Body' => 'AIDE',
        ])->assertStatus(200);
    }

    public function test_qr_code_eleve_genere_png(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $eleve = Eleve::factory()->create();

        $this->actingAs($admin, 'api')
            ->get("/api/v1/eleves/{$eleve->id}/qr-code")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_scan_qr_payload_invalide(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seance = \App\Models\Seance::factory()->create();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/presences/qr-scan', [
                'payload'   => base64_encode('payload_invalide'),
                'seance_id' => $seance->id,
            ])
            ->assertStatus(422);
    }
}
```

---

## ORDRE D'EXÉCUTION

```bash
git checkout develop && git pull origin main
cd edugestdz/backend

# 1. Packages
composer require kreait/firebase-php:^7.0
composer require simplesoftwareio/simple-qrcode:^4.0

# 2-7. Créer/modifier les fichiers listés ci-dessus

# 8. Tests
php artisan test --parallel
# → 0 régression + 5 nouveaux tests

# 9. Commit
git add .
git commit -m "feat: Push Firebase (absence+note+bulletin+réservation) + WhatsApp entrant + QR Code pointage"
git push origin develop
# → PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_NOTIFICATIONS_PUSH.md — 11 étapes.

RÈGLES :
1. PostgreSQL uniquement.
2. 0 régression — tous les tests existants restent verts.
3. Si ReservationMarketplace n'existe pas encore → commenter l'observer correspondant.
4. Si simplesoftwareio/simple-qrcode a conflit → utiliser endroid/qr-code à la place.

php artisan test --parallel → verts → git push → PR develop → main.
```
