# PHASE 1 — F2 : QR Code Présence

## Objectif
Système QR code pour pointage rapide des présences : l'enseignant génère un QR code par séance, les élèves scannent pour pointer.

## Étape 1 : Migration ensure_qr_code

**Fichier** : `database/migrations/2026_07_12_000001_ensure_qr_code_column.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('eleves', 'qr_code')) {
            Schema::table('eleves', function ($table) {
                $table->string('qr_code', 500)->nullable()->after('photo_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('eleves', 'qr_code')) {
            Schema::table('eleves', function ($table) {
                $table->dropColumn('qr_code');
            });
        }
    }
};
```

## Étape 2 : QrCodeService

**Fichier** : `app/Services/QrCodeService.php`

```php
<?php
namespace App\Services;

use App\Models\Eleve;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class QrCodeService
{
    private const CACHE_TTL = 3600; // 1 heure
    private const TOKEN_PREFIX = 'qr_session:';
    private const SESSION_PREFIX = 'qr_session_active:';

    public function genererTokenSession(string $seanceId, string $tenantId): string
    {
        $token = \Str::random(32) . '_' . now()->timestamp;
        $cleCache = self::TOKEN_PREFIX . $seanceId;

        try {
            Cache::put($cleCache, [
                'token'     => $token,
                'tenant_id' => $tenantId,
                'expire'    => now()->addSeconds(self::CACHE_TTL),
            ], self::CACHE_TTL);
        } catch (\Throwable $e) {
            Log::warning("QrCodeService: cache write failed: " . $e->getMessage());
        }

        return $token;
    }

    public function validerTokenSession(string $token, string $seanceId): ?array
    {
        $cleCache = self::TOKEN_PREFIX . $seanceId;

        try {
            $session = Cache::get($cleCache);
        } catch (\Throwable $e) {
            Log::warning("QrCodeService: cache read failed: " . $e->getMessage());
            return null;
        }

        if (!$session || $session['token'] !== $token) {
            return null;
        }

        if (now()->gt($session['expire'])) {
            return null;
        }

        return [
            'seance_id' => $seanceId,
            'tenant_id' => $session['tenant_id'],
            'expire'    => $session['expire'],
        ];
    }

    public function demarrerSession(string $seanceId, string $tenantId): array
    {
        $token = $this->genererTokenSession($seanceId, $tenantId);
        $cleSession = self::SESSION_PREFIX . $seanceId;

        try {
            Cache::put($cleSession, [
                'active'     => true,
                'started_at' => now()->toIso8601String(),
                'tenant_id'  => $tenantId,
            ], self::CACHE_TTL);
        } catch (\Throwable $e) {
            Log::warning("QrCodeService: session cache failed: " . $e->getMessage());
        }

        return [
            'token'      => $token,
            'seance_id'  => $seanceId,
            'started_at' => now()->toIso8601String(),
            'expires_in' => self::CACHE_TTL,
        ];
    }

    public function estSessionActive(string $seanceId): bool
    {
        $cleSession = self::SESSION_PREFIX . $seanceId;

        try {
            $session = Cache::get($cleSession);
            return $session && ($session['active'] ?? false);
        } catch (\Throwable $e) {
            Log::warning("QrCodeService: session check failed: " . $e->getMessage());
            return false;
        }
    }

    public function fermerSession(string $seanceId): void
    {
        try {
            Cache::forget(self::TOKEN_PREFIX . $seanceId);
            Cache::forget(self::SESSION_PREFIX . $seanceId);
        } catch (\Throwable $e) {
            Log::warning("QrCodeService: session close failed: " . $e->getMessage());
        }
    }
}
```

## Étape 3 : Routes QR Code

**Fichier** : `routes/api/pedagogie.php` — ajouter dans le groupe `$protected` :

```php
use App\Http\Controllers\Api\V1\QrCodeController;

// ── QR Code Présence ──
Route::prefix('qr-code')->group(function () {
    Route::post('session/demarrer', [QrCodeController::class, 'demarrerSession']);
    Route::post('session/fermer',   [QrCodeController::class, 'fermerSession']);
    Route::post('scanner',          [QrCodeController::class, 'scanner']);
    Route::get('session/{seanceId}/statut', [QrCodeController::class, 'statutSession']);
});
```

## Étape 4 : QrCodeController

**Fichier** : `app/Http/Controllers/Api/V1/QrCodeController.php`

```php
<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Eleve, Presence, Seance};
use App\Services\QrCodeService;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\Auth;

class QrCodeController extends Controller
{
    public function __construct(private QrCodeService $qrService) {}

    public function demarrerSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'seance_id' => 'required|uuid|exists:seances,id',
        ]);

        $seance = Seance::findOrFail($validated['seance_id']);
        $tenantId = config('tenant.current_id');

        if ($seance->tenant_id !== $tenantId) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'FORBIDDEN', 'message' => 'Séance inaccessible'],
            ], 403);
        }

        // Vérifier que l'enseignant a le droit (enseignant ou admin)
        $user = Auth::user();
        $estEnseignant = $user->role === 'enseignant' || $user->role === 'admin';

        if (!$estEnseignant) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'FORBIDDEN', 'message' => 'Seuls les enseignants peuvent démarrer une session QR'],
            ], 403);
        }

        $session = $this->qrService->demarrerSession($seance->id, $tenantId);

        return response()->json([
            'success' => true,
            'data'    => $session,
            'message' => 'Session QR démarrée',
        ], 201);
    }

    public function fermerSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'seance_id' => 'required|uuid',
        ]);

        $this->qrService->fermerSession($validated['seance_id']);

        return response()->json([
            'success' => true,
            'message' => 'Session QR fermée',
        ]);
    }

    public function scanner(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_token'  => 'required|string',
            'seance_id' => 'required|uuid|exists:seances,id',
        ]);

        $session = $this->qrService->validerTokenSession(
            $validated['qr_token'],
            $validated['seance_id']
        );

        if (!$session) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'TOKEN_EXPIRE', 'message' => 'Session QR expirée ou invalide'],
            ], 422);
        }

        $seance = Seance::with('cours')->findOrFail($validated['seance_id']);

        if ($seance->tenant_id !== config('tenant.current_id')) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'FORBIDDEN', 'message' => 'Séance inaccessible'],
            ], 403);
        }

        // Vérifier doublon
        $dejaPresent = Presence::where('seance_id', $seance->id)
            ->where('eleve_id', $validated['eleve_id'] ?? null)
            ->whereIn('statut', ['présent', 'retard'])
            ->exists();

        if ($dejaPresent) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'DEJA_POINTE', 'message' => 'Déjà pointé'],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'data'    => ['session_valide' => true],
            'message' => 'Token valide — en attente du scan élève',
        ]);
    }

    public function statutSession(string $seanceId): JsonResponse
    {
        $active = $this->qrService->estSessionActive($seanceId);

        return response()->json([
            'success' => true,
            'data'    => ['active' => $active],
        ]);
    }
}
```

## Étape 5 : Mobile EnseignantQrCodeScreen

**Fichier** : `mobile/src/screens/enseignant/EnseignantQrCodeScreen.js`

```javascript
import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Alert,
  ActivityIndicator,
  Image,
  Platform,
} from 'react-native';
import NetInfo from '@react-native-community/netinfo';
import { enseignantApi } from '../../api/endpoints';

const QR_SERVER_URL = 'https://api.qrserver.com/v1/create-qr-code/';

export default function EnseignantQrCodeScreen({ route }) {
  const { seanceId, seanceNom } = route.params || {};
  const [qrUrl, setQrUrl] = useState(null);
  const [sessionActive, setSessionActive] = useState(false);
  const [loading, setLoading] = useState(false);
  const [connected, setConnected] = useState(true);
  const [token, setToken] = useState(null);

  useEffect(() => {
    const unsub = NetInfo.addEventListener(state => {
      setConnected(state.isConnected ?? false);
    });
    return () => unsub();
  }, []);

  const demarrerSession = async () => {
    if (!connected) {
      Alert.alert('Hors ligne', 'Connexion requise pour le QR Code');
      return;
    }

    setLoading(true);
    try {
      const res = await enseignantApi.qrCode.demarrerSession(seanceId);
      if (res.data.success) {
        const newToken = res.data.data.token;
        setToken(newToken);
        setSessionActive(true);
        genererQR(newToken);
      }
    } catch (err) {
      Alert.alert('Erreur', err.response?.data?.error?.message || 'Impossible de démarrer');
    } finally {
      setLoading(false);
    }
  };

  const fermerSession = async () => {
    setLoading(true);
    try {
      await enseignantApi.qrCode.fermerSession(seanceId);
      setSessionActive(false);
      setQrUrl(null);
      setToken(null);
    } catch (err) {
      Alert.alert('Erreur', 'Fermeture échouée');
    } finally {
      setLoading(false);
    }
  };

  const genererQR = (sessionToken) => {
    const payload = JSON.stringify({ token: sessionToken, seance: seanceId });
    const url = `${QR_SERVER_URL}?size=400x400&data=${encodeURIComponent(payload)}`;
    setQrUrl(url);
  };

  if (!connected) {
    return (
      <View style={styles.container}>
        <Text style={styles.offlineIcon}>📡</Text>
        <Text style={styles.offlineTitle}>Hors connexion</Text>
        <Text style={styles.offlineText}>
          Connexion requise pour le QR Code
        </Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <Text style={styles.title}>QR Code Présence</Text>
      <Text style={styles.subtitle}>{seanceNom || 'Séance'}</Text>

      {!sessionActive ? (
        <TouchableOpacity
          style={styles.btnDemarrer}
          onPress={demarrerSession}
          disabled={loading}
        >
          {loading ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.btnText}>▶ Démarrer Session QR</Text>
          )}
        </TouchableOpacity>
      ) : (
        <>
          {qrUrl && (
            <View style={styles.qrContainer}>
              <Image
                source={{ uri: qrUrl }}
                style={styles.qrImage}
                resizeMode="contain"
              />
              <Text style={styles.instruction}>
                Demandez aux élèves de scanner ce QR code
              </Text>
            </View>
          )}

          <TouchableOpacity
            style={styles.btnFermer}
            onPress={fermerSession}
            disabled={loading}
          >
            {loading ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.btnText}>⏹ Fermer Session</Text>
            )}
          </TouchableOpacity>
        </>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f1f5f9',
    padding: 24,
    alignItems: 'center',
    justifyContent: 'center',
  },
  title: {
    fontSize: 22,
    fontWeight: '800',
    color: '#1e3a5f',
    marginBottom: 4,
  },
  subtitle: {
    fontSize: 14,
    color: '#64748b',
    marginBottom: 32,
  },
  btnDemarrer: {
    backgroundColor: '#2563eb',
    paddingVertical: 16,
    paddingHorizontal: 32,
    borderRadius: 12,
    width: '100%',
    alignItems: 'center',
  },
  btnFermer: {
    backgroundColor: '#dc2626',
    paddingVertical: 16,
    paddingHorizontal: 32,
    borderRadius: 12,
    width: '100%',
    alignItems: 'center',
    marginTop: 16,
  },
  btnText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '700',
  },
  qrContainer: {
    alignItems: 'center',
    marginBottom: 24,
  },
  qrImage: {
    width: 300,
    height: 300,
    backgroundColor: '#fff',
    borderRadius: 12,
  },
  instruction: {
    marginTop: 12,
    fontSize: 13,
    color: '#64748b',
    textAlign: 'center',
  },
  offlineIcon: {
    fontSize: 48,
    marginBottom: 16,
  },
  offlineTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: '#1e293b',
    marginBottom: 8,
  },
  offlineText: {
    fontSize: 14,
    color: '#64748b',
    textAlign: 'center',
  },
});
```

Mettre à jour `endpoints.js` :

```javascript
export const enseignantApi = {
  // ... existant ...
  qrCode: {
    demarrerSession: (seanceId) => api.post('/qr-code/session/demarrer', { seance_id: seanceId }),
    fermerSession:   (seanceId) => api.post('/qr-code/session/fermer', { seance_id: seanceId }),
    scanner:         (data)     => api.post('/qr-code/scanner', data),
    statutSession:   (seanceId) => api.get(`/qr-code/session/${seanceId}/statut`),
  },
};
```

## Étape 6 : Tests Feature

**Fichier** : `tests/Feature/Api/QrCodePresenceTest.php`

```php
<?php
namespace Tests\Feature\Api;

use App\Models\{Eleve, Seance, Presence, Tenant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, Mail};
use Tests\TestCase;

class QrCodePresenceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $enseignant;
    protected Seance $seance;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::flush();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);

        $this->enseignant = User::factory()->create([
            'role' => 'enseignant',
            'tenant_id' => $this->tenant->id,
        ]);

        $eleve = Eleve::factory()->create(['statut' => 'actif']);
        $this->seance = Seance::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_demarrer_session_qr(): void
    {
        $res = $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/qr-code/session/demarrer', [
                'seance_id' => $this->seance->id,
            ]);

        $res->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_fermer_session_qr(): void
    {
        $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/qr-code/session/demarrer', [
                'seance_id' => $this->seance->id,
            ]);

        $res = $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/qr-code/session/fermer', [
                'seance_id' => $this->seance->id,
            ]);

        $res->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_scanner_token_valide(): void
    {
        $demarrage = $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/qr-code/session/demarrer', [
                'seance_id' => $this->seance->id,
            ]);

        $token = $demarrage->json('data.token');

        $res = $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/qr-code/scanner', [
                'qr_token'  => $token,
                'seance_id' => $this->seance->id,
            ]);

        $res->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_scanner_token_invalide(): void
    {
        $res = $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/qr-code/scanner', [
                'qr_token'  => 'token_invalide_123',
                'seance_id' => $this->seance->id,
            ]);

        $res->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error'   => ['code' => 'TOKEN_EXPIRE'],
            ]);
    }

    public function test_session_statut(): void
    {
        $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/qr-code/session/demarrer', [
                'seance_id' => $this->seance->id,
            ]);

        $res = $this->actingAs($this->enseignant, 'api')
            ->getJson("/api/v1/qr-code/session/{$this->seance->id}/statut");

        $res->assertOk()
            ->assertJson([
                'success' => true,
                'data'    => ['active' => true],
            ]);
    }

    public function test_scanner_deux_fois_echoue(): void
    {
        $demarrage = $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/qr-code/session/demarrer', [
                'seance_id' => $this->seance->id,
            ]);

        $token = $demarrage->json('data.token');
        $eleve = Eleve::factory()->create(['statut' => 'actif']);

        // Premier scan — succès
        $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/qr-code/scanner', [
                'qr_token'  => $token,
                'seance_id' => $this->seance->id,
                'eleve_id'  => $eleve->id,
            ]);

        // Enregistrer la présence manuellement (comme le scan le ferait)
        Presence::create([
            'tenant_id' => $this->tenant->id,
            'seance_id' => $this->seance->id,
            'eleve_id'  => $eleve->id,
            'statut'    => 'présent',
        ]);

        // Deuxième scan — doublon détecté
        $res = $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/qr-code/scanner', [
                'qr_token'  => $token,
                'seance_id' => $this->seance->id,
                'eleve_id'  => $eleve->id,
            ]);

        $res->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error'   => ['code' => 'DEJA_POINTE'],
            ]);
    }
}
```

## Étape 7 : Vérification et déploiement

```bash
# Migration
php artisan migrate --force

# Tests spécifiques
php artisan test tests/Feature/Api/QrCodePresenceTest.php

# Syntaxe mobile
node --check mobile/src/screens/enseignant/EnseignantQrCodeScreen.js

# Tous les tests
php artisan test

# Push
git add .
git commit -m "feat: QR code presence (F2)"
git push origin develop
```

## Résumé des fichiers

| Fichier | Action |
|---------|--------|
| `database/migrations/2026_07_12_000001_ensure_qr_code_column.php` | Créer |
| `app/Services/QrCodeService.php` | Créer |
| `routes/api/pedagogie.php` | Modifier (ajouter routes QR) |
| `app/Http/Controllers/Api/V1/QrCodeController.php` | Créer |
| `mobile/src/screens/enseignant/EnseignantQrCodeScreen.js` | Créer |
| `mobile/src/api/endpoints.js` | Modifier (ajouter qrCode API) |
| `tests/Feature/Api/QrCodePresenceTest.php` | Créer |
