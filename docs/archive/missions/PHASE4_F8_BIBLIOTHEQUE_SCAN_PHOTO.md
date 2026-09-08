# PHASE 4 — F8 : Bibliothèque Scolaire + Scan Photo (Google Vision API)

## Objectif
Implémenter le module **Bibliothèque Scolaire** complet (catalogue, emprunts, retours) avec une fonctionnalité de **scan photo** utilisant Google Cloud Vision API pour identifier les livres par leur couverture (OCR → titre/auteur/isbn).

## Architecture

```
Photo couverture → ScanLivreService → Google Vision API → Résultat OCR
                                                              ↓
                                    BibliothequeController::scanner() → Recherche catalogue
                                                              ↓
                                              Livre trouvé → Détails livre
                                              Livre inconnu → Proposition d'ajout
```

---

## Étape 1 — Migration `livres_bibliotheque` + `emprunts_bibliotheque`

**Fichier :** `database/migrations/2026_07_13_900000_create_bibliotheque_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('livres_bibliotheque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('titre');
            $table->string('auteur')->nullable();
            $table->string('isbn', 20)->nullable();
            $table->string('editeur')->nullable();
            $table->integer('annee_edition')->nullable();
            $table->string('categorie')->nullable(); //Roman, Scientifique, etc.
            $table->text('description')->nullable();
            $table->string('photo_url')->nullable(); //URL de la couverture scannée
            $table->integer('nb_exemplaires')->default(1);
            $table->integer('nb_disponibles')->default(1);
            $table->string('code_barre', 50)->nullable()->unique();
            $table->string('emplacement')->nullable(); //Étagère A3, B1, etc.
            $table->string('statut')->default('actif'); //actif, retire, perdu
            $table->timestamps();

            $table->index(['tenant_id', 'titre']);
            $table->index(['tenant_id', 'isbn']);
            $table->index(['tenant_id', 'code_barre']);
        });

        Schema::create('emprunts_bibliotheque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('livre_id')->constrained('livres_bibliotheque')->cascadeOnDelete();
            $table->foreignId('emprunteur_id'); //eleve ou enseignant
            $table->string('type_emprunteur')->default('eleve'); //eleve, enseignant
            $table->string('nom_emprunteur');
            $table->date('date_emprunt');
            $table->date('date_retour_prevue');
            $table->date('date_retour_effective')->nullable();
            $table->string('statut')->default('en_cours'); //en_cours, retourne, en_retard
            $table->decimal('amende', 8, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'statut']);
            $table->index(['tenant_id', 'emprunteur_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emprunts_bibliotheque');
        Schema::dropIfExists('livres_bibliotheque');
    }
};
```

---

## Étape 2 — Models `Livre` + `Emprunt`

### `app/Models/Livre.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Livre extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'livres_bibliotheque';

    protected $fillable = [
        'tenant_id', 'titre', 'auteur', 'isbn', 'editeur',
        'annee_edition', 'categorie', 'description', 'photo_url',
        'nb_exemplaires', 'nb_disponibles', 'code_barre',
        'emplacement', 'statut',
    ];

    protected $casts = [
        'annee_edition'   => 'integer',
        'nb_exemplaires'  => 'integer',
        'nb_disponibles'  => 'integer',
    ];

    public function emprunts(): HasMany
    {
        return $this->hasMany(Emprunt::class, 'livre_id');
    }

    public function estDisponible(): bool
    {
        return $this->nb_disponibles > 0 && $this->statut === 'actif';
    }

    public function getEstDisponibleAttribute(): bool
    {
        return $this->estDisponible();
    }
}
```

### `app/Models/Emprunt.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Emprunt extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'emprunts_bibliotheque';

    protected $fillable = [
        'tenant_id', 'livre_id', 'emprunteur_id', 'type_emprunteur',
        'nom_emprunteur', 'date_emprunt', 'date_retour_prevue',
        'date_retour_effective', 'statut', 'amende', 'note',
    ];

    protected $casts = [
        'date_emprunt'          => 'date',
        'date_retour_prevue'    => 'date',
        'date_retour_effective' => 'date',
        'amende'                => 'decimal:2',
    ];

    public function livre(): BelongsTo
    {
        return $this->belongsTo(Livre::class, 'livre_id');
    }

    public function estEnRetard(): bool
    {
        return $this->statut === 'en_cours'
            && $this->date_retour_prevue
            && $this->date_retour_prevue->isPast();
    }
}
```

---

## Étape 3 — ScanLivreService (Google Vision API)

**Fichier :** `app/Services/ScanLivreService.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScanLivreService
{
    private string $apiKey;
    private string $apiUrl = 'https://vision.googleapis.com/v1/images:annotate';

    public function __construct()
    {
        $this->apiKey = config('services.google.vision_api_key', '');
    }

    public function estConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Analyser une image (base64 ou URL) et extraire texte via Vision API.
     */
    public function analyserImage(string $imageData): array
    {
        if (!$this->estConfigured()) {
            return [
                'success' => false,
                'error'   => 'GOOGLE_VISION_API_KEY non configuré',
            ];
        }

        $payload = $this->buildPayload($imageData);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->apiUrl}?key={$this->apiKey}", $payload);

            if ($response->failed()) {
                Log::error('Google Vision API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error'   => "Erreur Vision API: {$response->status()}",
                ];
            }

            return $this->extraireResultats($response->json());
        } catch (\Exception $e) {
            Log::error('Vision API exception', ['message' => $e->getMessage()]);

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Construire le payload pour Vision API (TEXT_DETECTION).
     */
    private function buildPayload(string $imageData): array
    {
        // Si c'est une URL
        if (filter_var($imageData, FILTER_VALIDATE_URL)) {
            $image = ['source' => ['gcsImageUri' => $imageData]];
        } else {
            // Base64
            $image = ['content' => $imageData];
        }

        return [
            'requests' => [
                [
                    'image'    => $image,
                    'features' => [
                        ['type' => 'TEXT_DETECTION', 'maxResults' => 10],
                    ],
                ],
            ],
        ];
    }

    /**
     * Extraire et structurer les résultats OCR.
     */
    private function extraireResultats(array $json): array
    {
        $annotations = $json['responses'][0]['fullTextAnnotation'] ?? null;

        if (!$annotations) {
            return [
                'success'  => true,
                'texte'    => '',
                'titre'    => null,
                'auteur'   => null,
                'isbn'     => null,
                'confiance' => 0,
            ];
        }

        $texte = $annotations['text'] ?? '';
        $confiance = $annotations['pages'][0]['property']['confidence'] ?? 0;

        // Extraction heuristic du titre/auteur/isbn
        $titre  = $this->extraireTitre($texte);
        $auteur = $this->extraireAuteur($texte);
        $isbn   = $this->extraireIsbn($texte);

        return [
            'success'   => true,
            'texte'     => $texte,
            'titre'     => $titre,
            'auteur'    => $auteur,
            'isbn'      => $isbn,
            'confiance' => round($confiance * 100, 1),
        ];
    }

    /**
     * Extraire le titre du texte OCR (première ligne significative).
     */
    private function extraireTitre(string $texte): ?string
    {
        $lignes = array_filter(array_map('trim', explode("\n", $texte)));

        foreach ($lignes as $ligne) {
            // Ignorer lignes trop courtes ou ressemblant à un ISBN/auteur
            if (strlen($ligne) < 3) continue;
            if (preg_match('/^\d{10,13}$/', $ligne)) continue; // ISBN
            if (preg_match('/^(auteur|author|par|by)/i', $ligne)) continue;

            return $ligne;
        }

        return null;
    }

    /**
     * Extraire l'auteur du texte OCR.
     */
    private function extraireAuteur(string $texte): ?string
    {
        // Chercher pattern "Auteur: XXX" ou "par XXX"
        if (preg_match('/(?:auteur|author|par|by)\s*:\s*(.+)/i', $texte, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Extraire l'ISBN du texte OCR.
     */
    private function extraireIsbn(string $texte): ?string
    {
        // ISBN-10 ou ISBN-13
        if (preg_match('/(?:ISBN[\s:-]*)?(\d[\d\s-]{9,16}\d)/i', $texte, $m)) {
            $isbn = preg_replace('/[\s-]/', '', $m[1]);
            if (strlen($isbn) === 10 || strlen($isbn) === 13) {
                return $isbn;
            }
        }

        return null;
    }
}
```

**Config à ajouter dans `config/services.php` :**
```php
'google' => [
    'vision_api_key' => env('GOOGLE_VISION_API_KEY'),
],
```

**Variable à ajouter dans `.env.example` :**
```
# ── Google Cloud Vision API (Scan Bibliothèque) ──────────
GOOGLE_VISION_API_KEY=
```

---

## Étape 4 — BibliothequeController + méthode `scanner()`

**Fichier :** `app/Http/Controllers/Api/V1/BibliothequeController.php`

> **Règle** : AJOUTER ce controller. La méthode `scanner()` est intégrée dans la classe.

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Livre;
use App\Models\Emprunt;
use App\Services\ScanLivreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BibliothequeController extends Controller
{
    // ═══════════════════════════════════════════════════════
    // CATALOGUE — CRUD
    // ═══════════════════════════════════════════════════════

    public function index(Request $request): JsonResponse
    {
        $query = Livre::query()
            ->where('tenant_id', config('tenant.current_id'))
            ->where('statut', 'actif');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'ilike', "%{$search}%")
                  ->orWhere('auteur', 'ilike', "%{$search}%")
                  ->orWhere('isbn', 'like', "%{$search}%");
            });
        }

        if ($categorie = $request->input('categorie')) {
            $query->where('categorie', $categorie);
        }

        $livres = $query->orderBy('titre')->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $livres,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'titre'           => 'required|string|max:255',
            'auteur'          => 'nullable|string|max:255',
            'isbn'            => 'nullable|string|max:20',
            'editeur'         => 'nullable|string|max:255',
            'annee_edition'   => 'nullable|integer|min:1900|max:' . date('Y'),
            'categorie'       => 'nullable|string|max:100',
            'description'     => 'nullable|string',
            'nb_exemplaires'  => 'nullable|integer|min:1',
            'emplacement'     => 'nullable|string|max:50',
        ]);

        $validated['tenant_id']      = config('tenant.current_id');
        $validated['nb_exemplaires'] = $validated['nb_exemplaires'] ?? 1;
        $validated['nb_disponibles'] = $validated['nb_exemplaires'];

        $livre = Livre::create($validated);

        return response()->json([
            'success' => true,
            'data'    => $livre,
        ], 201);
    }

    public function show(Livre $livre): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $livre->load('emprunts' => fn($q) => $q->where('statut', 'en_cours')),
        ]);
    }

    // ═══════════════════════════════════════════════════════
    // EMPRUNTS
    // ═══════════════════════════════════════════════════════

    public function emprunter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'livre_id'        => 'required|exists:livres_bibliotheque,id',
            'emprunteur_id'   => 'required|integer',
            'type_emprunteur' => 'nullable|in:eleve,enseignant',
            'nom_emprunteur'  => 'required|string|max:255',
            'duree_jours'     => 'nullable|integer|min:1|max:30',
        ]);

        $livre = Livre::findOrFail($validated['livre_id']);

        if (!$livre->estDisponible()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce livre n\'est pas disponible pour emprunt.',
            ], 422);
        }

        $emprunt = DB::transaction(function () use ($validated, $livre) {
            $emprunt = Emprunt::create([
                'tenant_id'         => config('tenant.current_id'),
                'livre_id'          => $livre->id,
                'emprunteur_id'     => $validated['emprunteur_id'],
                'type_emprunteur'   => $validated['type_emprunteur'] ?? 'eleve',
                'nom_emprunteur'    => $validated['nom_emprunteur'],
                'date_emprunt'      => now()->toDateString(),
                'date_retour_prevue' => now()->addDays($validated['duree_jours'] ?? 14)->toDateString(),
                'statut'            => 'en_cours',
            ]);

            $livre->decrement('nb_disponibles');

            return $emprunt;
        });

        return response()->json([
            'success' => true,
            'data'    => $emprunt->load('livre'),
        ], 201);
    }

    public function retourner(Emprunt $emprunt): JsonResponse
    {
        if ($emprunt->statut !== 'en_cours') {
            return response()->json([
                'success' => false,
                'message' => 'Cet emprunt n\'est pas actif.',
            ], 422);
        }

        DB::transaction(function () use ($emprunt) {
            $emprunt->update([
                'date_retour_effective' => now()->toDateString(),
                'statut'                => 'retourne',
            ]);

            $emprunt->livre->increment('nb_disponibles');
        });

        return response()->json([
            'success' => true,
            'data'    => $emprunt->fresh('livre'),
        ]);
    }

    public function mesEmprunts(Request $request): JsonResponse
    {
        $emprunts = Emprunt::where('tenant_id', config('tenant.current_id'))
            ->where('emprunteur_id', $request->user()->id)
            ->with('livre')
            ->orderByDesc('date_emprunt')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $emprunts,
        ]);
    }

    // ═══════════════════════════════════════════════════════
    // SCAN PHOTO — Google Vision API
    // ═══════════════════════════════════════════════════════

    /**
     * Scanner une photo de couverture de livre.
     *
     * POST /api/v1/bibliotheque/scan
     * Body: { "image": "base64..." } ou { "image_url": "https://..." }
     *
     * Réponse:
     * - Si livre trouvé dans le catalogue → détails + disponibilité
     * - Si livre inconnu → texte OCR extrait + propositions de création
     */
    public function scanner(Request $request, ScanLivreService $scanService): JsonResponse
    {
        if (!$scanService->estConfigured()) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'VISION_API_NOT_CONFIGURED',
                    'message' => 'Le service de scan n\'est pas configuré. Contactez l\'administrateur.',
                ],
            ], 503);
        }

        $validated = $request->validate([
            'image'     => 'required_without:image_url|string|max:10485760', //10 MB max
            'image_url' => 'required_without:image|url',
        ]);

        $imageData = $validated['image'] ?? $validated['image_url'];

        // Appel Vision API
        $resultat = $scanService->analyserImage($imageData);

        if (!$resultat['success']) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'SCAN_FAILED',
                    'message' => $resultat['error'],
                ],
            ], 422);
        }

        $tenantId = config('tenant.current_id');

        // Recherche dans le catalogue local
        $livreTrouve = null;

        if (!empty($resultat['isbn'])) {
            $livreTrouve = Livre::where('tenant_id', $tenantId)
                ->where('isbn', $resultat['isbn'])
                ->first();
        }

        if (!$livreTrouve && !empty($resultat['titre'])) {
            $livreTrouve = Livre::where('tenant_id', $tenantId)
                ->where('titre', 'ilike', $resultat['titre'])
                ->first();
        }

        if ($livreTrouve) {
            return response()->json([
                'success' => true,
                'source'  => 'catalogue',
                'data'    => [
                    'livre'      => $livreTrouve,
                    'disponible' => $livreTrouve->estDisponible(),
                    'nb_dispo'   => $livreTrouve->nb_disponibles,
                ],
                'ocr' => [
                    'titre'     => $resultat['titre'],
                    'auteur'    => $resultat['auteur'],
                    'isbn'      => $resultat['isbn'],
                    'confiance' => $resultat['confiance'],
                ],
            ]);
        }

        // Livre non trouvé → retourner les données OCR pour création
        return response()->json([
            'success' => true,
            'source'  => 'scan',
            'data'    => null,
            'ocr'     => [
                'titre'     => $resultat['titre'],
                'auteur'    => $resultat['auteur'],
                'isbn'      => $resultat['isbn'],
                'confiance' => $resultat['confiance'],
                'texte_brut' => $resultat['texte'],
            ],
            'message' => 'Livre non trouvé dans le catalogue. Vous pouvez l\'ajouter.',
        ]);
    }
}
```

---

## Étape 5 — Routes

**Fichier :** `routes/api/bibliotheque.php`

```php
<?php

use App\Http\Controllers\Api\V1\BibliothequeController;
use Illuminate\Support\Facades\Route;

Route::prefix('bibliotheque')->group(function () {

    // ── Catalogue ──
    Route::get('/', [BibliothequeController::class, 'index']);
    Route::post('/', [BibliothequeController::class, 'store']);
    Route::get('/{livre}', [BibliothequeController::class, 'show']);

    // ── Scan photo ──
    Route::post('/scan', [BibliothequeController::class, 'scanner']);

    // ── Emprunts ──
    Route::post('/emprunter', [BibliothequeController::class, 'emprunter']);
    Route::post('/retourner/{emprunt}', [BibliothequeController::class, 'retourner']);
    Route::get('/mes-emprunts', [BibliothequeController::class, 'mesEmprunts']);
});
```

**Enregistrement dans `routes/api.php` (ligne ~14, dans le groupe v1) :**
```php
Route::prefix('v1')->group(function () {
    // ... existant ...
    require __DIR__ . '/api/bibliotheque.php';
});
```

---

## Étape 6 — Tests

**Fichier :** `tests/Feature/Api/ScanLivreTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Livre;
use App\Models\User;
use App\Services\ScanLivreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class ScanLivreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Configurer le tenant
        config(['tenant.current_id' => 1]);

        // Créer un admin
        $this->admin = User::factory()->create([
            'role'     => 'admin',
            'tenant_id' => 1,
        ]);

        // Binder un ScanLivreService mocké
        App::bind(ScanLivreService::class, function () {
            $mock = new class extends ScanLivreService {
                public function estConfigured(): bool
                {
                    return true;
                }

                public function analyserImage(string $imageData): array
                {
                    return [
                        'success'   => true,
                        'texte'     => "Le Petit Prince\nAntoine de Saint-Exupéry\nISBN: 9782070612758",
                        'titre'     => 'Le Petit Prince',
                        'auteur'    => 'Antoine de Saint-Exupéry',
                        'isbn'      => '9782070612758',
                        'confiance' => 95.2,
                    ];
                }
            };

            return $mock;
        });
    }

    public function test_scan_livre_trouve_dans_catalogue(): void
    {
        $livre = Livre::create([
            'tenant_id'      => 1,
            'titre'          => 'Le Petit Prince',
            'auteur'         => 'Antoine de Saint-Exupéry',
            'isbn'           => '9782070612758',
            'nb_exemplaires' => 3,
            'nb_disponibles' => 2,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/bibliotheque/scan', [
                'image' => base64_encode('fake_image_data'),
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'source'  => 'catalogue',
            ])
            ->assertJsonPath('data.livre.id', $livre->id)
            ->assertJsonPath('data.disponible', true);
    }

    public function test_scan_livre_inconnu_retourne_ocr(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/bibliotheque/scan', [
                'image' => base64_encode('fake_image_data'),
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'source'  => 'scan',
                'data'    => null,
            ])
            ->assertJsonPath('ocr.titre', 'Le Petit Prince')
            ->assertJsonPath('ocr.isbn', '9782070612758');
    }

    public function test_scan_sans_image_retourne_422(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/bibliotheque/scan', []);

        $response->assertUnprocessable();
    }

    public function test_scan_sans_auth_retourne_401(): void
    {
        $response = $this->postJson('/api/v1/bibliotheque/scan', [
            'image' => base64_encode('fake_image_data'),
        ]);

        $response->assertUnauthorized();
    }

    public function test_scan_avec_url_image(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/bibliotheque/scan', [
                'image_url' => 'https://example.com/book_cover.jpg',
            ]);

        $response->assertOk()
            ->assertJsonPath('source', 'scan');
    }
}
```

---

## Étape 7 — Mobile Screen + Endpoints

### `mobile/src/screens/parent/BiblioScanScreen.js`

```javascript
import React, { useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  Alert,
  ActivityIndicator,
  Image,
} from 'react-native';

let ImagePicker;
try {
  ImagePicker = require('expo-image-picker');
} catch (e) {
  ImagePicker = null;
}

import { bibliothequeApi } from '../../api/endpoints';

export default function BiblioScanScreen({ navigation }) {
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState(null);

  if (!ImagePicker) {
    return (
      <View style={styles.container}>
        <Text style={styles.errorTitle}>Module indisponible</Text>
        <Text style={styles.errorText}>
          Le module de scan photo n'est pas installé.\n
          Veuillez installer expo-image-picker.
        </Text>
      </View>
    );
  }

  const pickImage = async (useCamera) => {
    try {
      setError(null);
      setResult(null);

      const options = {
        mediaTypes: ImagePicker.MediaTypeOptions.Images,
        quality: 0.8,
        base64: true,
      };

      const pickerResult = useCamera
        ? await ImagePicker.launchCameraAsync(options)
        : await ImagePicker.launchImageLibraryAsync(options);

      if (pickerResult.canceled) return;

      const base64 = pickerResult.assets[0].base64;
      scanImage(base64);
    } catch (err) {
      setError('Erreur lors de la sélection de l\'image.');
    }
  };

  const scanImage = async (base64) => {
    setLoading(true);
    try {
      const response = await bibliothequeApi.scan(base64);
      setResult(response.data);
    } catch (err) {
      const msg = err.response?.data?.error?.message || 'Erreur de scan.';
      setError(msg);
    } finally {
      setLoading(false);
    }
  };

  const emprunter = async (livreId) => {
    try {
      await bibliothequeApi.emprunter({
        livre_id: livreId,
        type_emprunteur: 'eleve',
      });
      Alert.alert('Succès', 'Livre emprunté avec succès !');
      navigation.goBack();
    } catch (err) {
      Alert.alert('Erreur', err.response?.data?.message || 'Emprunt échoué.');
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Scanner un livre</Text>
      <Text style={styles.subtitle}>
        Prenez en photo la couverture d'un livre pour le rechercher dans le catalogue.
      </Text>

      {loading ? (
        <ActivityIndicator size="large" color="#4F46E5" style={styles.loader} />
      ) : (
        <>
          <TouchableOpacity
            style={styles.button}
            onPress={() => pickImage(true)}
          >
            <Text style={styles.buttonText}>📷 Prendre une photo</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.button, styles.buttonSecondary]}
            onPress={() => pickImage(false)}
          >
            <Text style={[styles.buttonText, styles.buttonTextSecondary]}>
              🖼️ Choisir dans la galerie
            </Text>
          </TouchableOpacity>
        </>
      )}

      {error && (
        <View style={styles.errorBox}>
          <Text style={styles.errorText}>{error}</Text>
        </View>
      )}

      {result && (
        <View style={styles.resultBox}>
          {result.source === 'catalogue' ? (
            <>
              <Text style={styles.resultTitle}>✅ Livre trouvé !</Text>
              <Text style={styles.resultInfo}>
                {result.data.livre.titre} — {result.data.livre.auteur}
              </Text>
              <Text style={styles.resultInfo}>
                Disponibilité : {result.data.nb_dispo} exemplaire(s)
              </Text>
              {result.data.disponible && (
                <TouchableOpacity
                  style={styles.emprunterButton}
                  onPress={() => emprunter(result.data.livre.id)}
                >
                  <Text style={styles.emprunterButtonText}>Emprunter</Text>
                </TouchableOpacity>
              )}
            </>
          ) : (
            <>
              <Text style={styles.resultTitle}>📖 Livre non trouvé</Text>
              {result.ocr?.titre && (
                <Text style={styles.resultInfo}>Titre détecté : {result.ocr.titre}</Text>
              )}
              {result.ocr?.auteur && (
                <Text style={styles.resultInfo}>Auteur détecté : {result.ocr.auteur}</Text>
              )}
              <Text style={styles.resultInfo}>
                Confiance OCR : {result.ocr?.confiance}%
              </Text>
              <Text style={styles.ocrHint}>
                Vous pouvez l'ajouter manuellement au catalogue.
              </Text>
            </>
          )}

          {result.ocr && (
            <View style={styles.ocrBox}>
              <Text style={styles.ocrTitle}>Texte détecté :</Text>
              <Text style={styles.ocrText}>{result.ocr.texte_brut || result.ocr.titre}</Text>
            </View>
          )}
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: 20, backgroundColor: '#F9FAFB' },
  title: { fontSize: 22, fontWeight: 'bold', color: '#111827', marginBottom: 4 },
  subtitle: { fontSize: 14, color: '#6B7280', marginBottom: 24 },
  loader: { marginTop: 40 },
  button: {
    backgroundColor: '#4F46E5',
    padding: 16,
    borderRadius: 12,
    alignItems: 'center',
    marginBottom: 12,
  },
  buttonSecondary: { backgroundColor: '#fff', borderWidth: 1, borderColor: '#D1D5DB' },
  buttonText: { color: '#fff', fontSize: 16, fontWeight: '600' },
  buttonTextSecondary: { color: '#374151' },
  errorBox: { marginTop: 16, padding: 12, backgroundColor: '#FEF2F2', borderRadius: 8 },
  errorTitle: { fontSize: 18, fontWeight: 'bold', color: '#DC2626', marginBottom: 8 },
  errorText: { color: '#DC2626', fontSize: 14 },
  resultBox: { marginTop: 20, padding: 16, backgroundColor: '#fff', borderRadius: 12, borderWidth: 1, borderColor: '#E5E7EB' },
  resultTitle: { fontSize: 18, fontWeight: 'bold', color: '#111827', marginBottom: 8 },
  resultInfo: { fontSize: 14, color: '#374151', marginBottom: 4 },
  emprunterButton: { backgroundColor: '#10B981', padding: 12, borderRadius: 8, marginTop: 12, alignItems: 'center' },
  emprunterButtonText: { color: '#fff', fontSize: 16, fontWeight: '600' },
  ocrHint: { fontSize: 13, color: '#6B7280', marginTop: 8, fontStyle: 'italic' },
  ocrBox: { marginTop: 12, padding: 10, backgroundColor: '#F3F4F6', borderRadius: 8 },
  ocrTitle: { fontSize: 12, fontWeight: 'bold', color: '#6B7280', marginBottom: 4 },
  ocrText: { fontSize: 13, color: '#374151' },
});
```

### `mobile/src/api/endpoints.js` — ajouter :

```javascript
export const bibliothequeApi = {
  list:     (params)          => api.get('/bibliotheque', { params }),
  show:     (id)              => api.get(`/bibliotheque/${id}`),
  scan:     (base64Image)     => api.post('/bibliotheque/scan', { image: base64Image }),
  emprunter:(data)            => api.post('/bibliotheque/emprunter', data),
  retourner:(empruntId)       => api.post(`/bibliotheque/retourner/${empruntId}`),
  mesEmprunts: ()             => api.get('/bibliotheque/mes-emprunts'),
};
```

---

## Checklist

| # | Étape | Fichiers | Tests |
|---|-------|----------|-------|
| 1 | Migration bibliotheque tables | `2026_07_13_900000_create_bibliotheque_tables.php` | Migration OK |
| 2 | Models Livre + Emprunt | `Livre.php`, `Emprunt.php` | Factory unit |
| 3 | ScanLivreService | `ScanLivreService.php`, `config/services.php`, `.env.example` | Unit test Vision API mock |
| 4 | BibliothequeController | `BibliothequeController.php` | Feature tests |
| 5 | Routes | `routes/api/bibliotheque.php` | Route assertion |
| 6 | Tests | `ScanLivreTest.php` (5 tests) | `php artisan test --filter=ScanLivreTest` |
| 7 | Mobile | `BiblioScanScreen.js`, `endpoints.js` | `node --check` |

## Commandes de validation

```bash
php artisan migrate --force
php artisan test --filter=ScanLivreTest        # 5 ✅
php artisan test                               # ≥ 1035 ✅
node --check mobile/src/screens/parent/BiblioScanScreen.js  # OK
git add -A && git commit -m "feat(F8): bibliothèque scan photo (Google Vision API)"
git push origin develop
```
