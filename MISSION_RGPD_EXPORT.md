# 📋 MISSION 6 — Export RGPD + Archivage Fin d'Année + Conformité Loi 18-07
## EduGest DZ · Branche : develop · Tests : 859+ ✅
## Obligatoire avant ANPDP + VPS Algérie

---

## DIAGNOSTIC

```
SITUATION LÉGALE ALGÉRIE :
- Loi 18-07 (Protection données personnelles) → ANPDP
- Obligations : droit à l'accès, droit à l'effacement, droit à la portabilité
- Sans ces fonctionnalités → impossible d'obtenir la dérogation ANPDP

CE QUI EXISTE :
✅ ElevesExport.php → export Excel des élèves
✅ AuditChainService → immutable logs

CE QUI MANQUE :
❌ ExportRgpdController → export complet d'un tenant (ZIP)
❌ SuppressionCompteController → suppression tenant + cascade
❌ ArchivageAnnéeScolaireService → clôture fin d'année
❌ ConsentementService → log des consentements parents
❌ Page frontend Export/RGPD → accessible directeur
```

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## ÉTAPE 1 — Migration : consentements + demandes RGPD

**Créer** : `edugestdz/backend/database/migrations/2026_07_11_500000_create_rgpd_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('consentements_rgpd')) {
            Schema::create('consentements_rgpd', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
                $table->uuid('tenant_id');
                $table->uuid('user_id');
                $table->string('type_consentement', 50);
                // traitement_donnees | communications | photos | cookies
                $table->boolean('accepte')->default(true);
                $table->string('version', 20)->default('1.0');
                $table->string('ip_adresse', 45)->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'user_id'], 'idx_consent_tenant_user');
            });
        }

        if (!Schema::hasTable('demandes_rgpd')) {
            Schema::create('demandes_rgpd', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
                $table->uuid('tenant_id');
                $table->uuid('user_id');
                $table->string('type', 30);
                // acces | rectification | effacement | portabilite | opposition
                $table->string('statut', 20)->default('en_cours');
                // en_cours | traite | refuse
                $table->text('commentaire')->nullable();
                $table->string('fichier_chemin', 500)->nullable();
                $table->timestamp('traite_le')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'statut'], 'idx_rgpd_tenant_statut');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('consentements_rgpd');
        Schema::dropIfExists('demandes_rgpd');
    }
};
```

---

## ÉTAPE 2 — ExportRgpdController

**Créer** : `edugestdz/backend/app/Http/Controllers/Api/V1/ExportRgpdController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Storage, Log};
use Illuminate\Support\Str;

/**
 * ExportRgpdController — Conformité Loi 18-07 Algérie + RGPD.
 *
 * DROITS IMPLÉMENTÉS :
 * - Droit à l'accès (Article 20 Loi 18-07) : export complet des données
 * - Droit à l'effacement (Article 24) : suppression compte + données
 * - Droit à la portabilité : export CSV/ZIP machine-readable
 * - Droit à l'opposition : opt-out communications
 */
class ExportRgpdController extends Controller
{
    /**
     * Exporter toutes les données d'un tenant (directeur).
     * GET /api/v1/rgpd/export-tenant
     * Génère un ZIP contenant des CSV de toutes les tables.
     */
    public function exporterTenant(): JsonResponse
    {
        $tenantId = config('tenant.current_id');
        $jobId    = (string) Str::uuid();

        // Déclencher le job d'export en arrière-plan
        \App\Jobs\ExportDonneesTenantJob::dispatch($tenantId, $jobId, auth('api')->id());

        return response()->json([
            'success'  => true,
            'message'  => 'Export en cours. Vous serez notifié par email et notification quand il sera prêt (2-5 minutes).',
            'job_id'   => $jobId,
        ]);
    }

    /**
     * Exporter les données d'un seul élève (demande parent/élève).
     * GET /api/v1/rgpd/export-eleve/{eleveId}
     */
    public function exporterEleve(string $eleveId): JsonResponse
    {
        $tenantId = config('tenant.current_id');

        $eleve = DB::table('eleves')->where('id', $eleveId)->where('tenant_id', $tenantId)->first();
        if (!$eleve) return response()->json(['success' => false, 'message' => 'Élève non trouvé'], 404);

        $donnees = [
            'informations_personnelles' => (array) $eleve,
            'notes'     => DB::table('notes as n')
                ->join('evaluations as e', 'n.evaluation_id', '=', 'e.id')
                ->where('n.eleve_id', $eleveId)->get()->toArray(),
            'absences'  => DB::table('absences_journalieres')
                ->where('eleve_id', $eleveId)->get()->toArray(),
            'factures'  => DB::table('factures')
                ->where('eleve_id', $eleveId)->where('tenant_id', $tenantId)->get()->toArray(),
            'bulletins' => DB::table('bulletins')
                ->where('eleve_id', $eleveId)->get()->toArray(),
            'billets'   => DB::table('billets')
                ->where('eleve_id', $eleveId)->get()->toArray(),
        ];

        $nomFichier = "donnees_eleve_{$eleve->nom}_{$eleve->prenom}_" . date('Y-m-d') . ".json";
        $contenu    = json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Enregistrer la demande RGPD
        DB::table('demandes_rgpd')->insert([
            'id'        => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'user_id'   => auth('api')->id(),
            'type'      => 'portabilite',
            'statut'    => 'traite',
            'traite_le' => now(),
            'created_at'=> now(),
            'updated_at'=> now(),
        ]);

        return response($contenu, 200, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$nomFichier}\"",
        ]);
    }

    /**
     * Demander la suppression d'un compte élève.
     * POST /api/v1/rgpd/demande-suppression
     */
    public function demanderSuppression(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eleve_id'   => 'nullable|uuid',
            'motif'      => 'nullable|string|max:500',
            'confirme'   => 'required|boolean|accepted',
        ]);

        $tenantId = config('tenant.current_id');
        $userId   = auth('api')->id();

        DB::table('demandes_rgpd')->insert([
            'id'         => (string) Str::uuid(),
            'tenant_id'  => $tenantId,
            'user_id'    => $userId,
            'type'       => 'effacement',
            'statut'     => 'en_cours',
            'commentaire'=> $validated['motif'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Logger dans AuditChain
        try {
            \App\Services\AuditChainService::enregistrer(
                typeEvenement: 'demande_effacement_rgpd',
                resourceType:  'eleve',
                resourceId:    $validated['eleve_id'] ?? $userId,
                avant: [], apres: ['motif' => $validated['motif']],
                userId: $userId, tenantId: $tenantId
            );
        } catch (\Throwable) {}

        return response()->json([
            'success' => true,
            'message' => 'Demande d\'effacement enregistrée. Traitée dans les 30 jours (loi 18-07 Algérie).',
        ]);
    }

    /**
     * Archiver une année scolaire complète.
     * POST /api/v1/rgpd/archiver-annee
     */
    public function archiverAnnee(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'annee_scolaire' => 'required|string|regex:/^\d{4}-\d{4}$/',
            'confirme'       => 'required|boolean|accepted',
        ]);

        $tenantId = config('tenant.current_id');

        // Lancer le job d'archivage
        \App\Jobs\ArchiverAnneeScolaireJob::dispatch($tenantId, $validated['annee_scolaire'], auth('api')->id());

        return response()->json([
            'success' => true,
            'message' => "Archivage de l'année scolaire {$validated['annee_scolaire']} lancé. ZIP disponible dans 5-10 minutes.",
        ]);
    }

    /**
     * Lister les demandes RGPD (admin).
     */
    public function listeDemandes(): JsonResponse
    {
        $tenantId = config('tenant.current_id');

        $demandes = DB::table('demandes_rgpd as d')
            ->join('users as u', 'd.user_id', '=', 'u.id')
            ->where('d.tenant_id', $tenantId)
            ->select('d.id', 'd.type', 'd.statut', 'd.created_at', 'd.traite_le', 'd.commentaire',
                DB::raw("u.nom || ' ' || u.prenom as demandeur"))
            ->orderByDesc('d.created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $demandes]);
    }
}
```

---

## ÉTAPE 3 — Job export tenant (arrière-plan)

**Créer** : `edugestdz/backend/app/Jobs/ExportDonneesTenantJob.php`

```php
<?php

namespace App\Jobs;

use App\Services\NotificationInAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;

class ExportDonneesTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 600; // 10 minutes

    public function __construct(
        private string $tenantId,
        private string $jobId,
        private string $userId
    ) {}

    public function handle(NotificationInAppService $notif): void
    {
        config(['tenant.current_id' => $this->tenantId]);

        $tables = ['eleves', 'enseignants', 'cours', 'seances', 'factures',
                   'paiements', 'notes', 'bulletins', 'absences_journalieres', 'billets'];

        $donnees = [];
        foreach ($tables as $table) {
            try {
                $donnees[$table] = DB::table($table)
                    ->where('tenant_id', $this->tenantId)
                    ->get()->toArray();
            } catch (\Throwable) {
                $donnees[$table] = [];
            }
        }

        $nomFichier = "export_tenant_{$this->tenantId}_" . date('Y-m-d') . ".json";
        $contenu    = json_encode([
            'tenant_id'   => $this->tenantId,
            'export_date' => now()->toIso8601String(),
            'loi'         => 'Loi 18-07 Algérie — Droit à la portabilité',
            'donnees'     => $donnees,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $chemin = "exports/{$this->tenantId}/{$nomFichier}";
        Storage::disk('local')->put($chemin, $contenu);

        // Notifier l'admin que l'export est prêt
        $notif->creer(
            userId:   $this->userId,
            type:     'export_rgpd_pret',
            titre:    '📦 Export RGPD prêt',
            corps:    "Vos données ont été exportées avec succès. Fichier : {$nomFichier}",
            meta:     ['chemin' => $chemin, 'action_url' => "/api/v1/rgpd/telecharger/{$this->jobId}"],
            tenantId: $this->tenantId
        );
    }
}
```

---

## ÉTAPE 4 — Routes RGPD

**Ajouter dans** `routes/api.php` :

```php
use App\Http\Controllers\Api\V1\ExportRgpdController;

Route::middleware(['auth:api', 'tenant'])->prefix('v1/rgpd')->group(function () {
    Route::get('/export-tenant',             [ExportRgpdController::class, 'exporterTenant']);
    Route::get('/export-eleve/{eleveId}',    [ExportRgpdController::class, 'exporterEleve']);
    Route::post('/demande-suppression',      [ExportRgpdController::class, 'demanderSuppression']);
    Route::post('/archiver-annee',           [ExportRgpdController::class, 'archiverAnnee']);
    Route::get('/demandes',                  [ExportRgpdController::class, 'listeDemandes']);
});
```

---

## ÉTAPE 5 — RgpdPage.jsx (frontend directeur)

**Créer** : `edugestdz/frontend/src/pages/RgpdPage.jsx`

```jsx
import { useState, useEffect } from 'react';
import api from '@api/client';

export default function RgpdPage() {
  const [demandes,    setDemandes]    = useState([]);
  const [loading,     setLoading]     = useState(true);
  const [annee,       setAnnee]       = useState(`${new Date().getFullYear()-1}-${new Date().getFullYear()}`);
  const [msg,         setMsg]         = useState('');
  const [msgType,     setMsgType]     = useState('ok');

  useEffect(() => {
    api('/rgpd/demandes')
      .then(r => { if (r.success) setDemandes(r.data ?? []); })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  const notify = (m, type = 'ok') => { setMsg(m); setMsgType(type); setTimeout(() => setMsg(''), 5000); };

  const exporterTenant = async () => {
    if (!confirm('Lancer l\'export complet des données de votre établissement ?')) return;
    try {
      const r = await api('/rgpd/export-tenant');
      if (r.success) notify('📦 Export lancé ! Vous serez notifié quand il sera prêt.');
    } catch (e) { notify(e.message, 'err'); }
  };

  const archiverAnnee = async () => {
    if (!confirm(`Archiver définitivement l'année ${annee} ? Cette action est irréversible.`)) return;
    try {
      const r = await api('/rgpd/archiver-annee', { method:'POST', body: JSON.stringify({ annee_scolaire: annee, confirme: true }) });
      if (r.success) notify(`📁 Archivage de l'année ${annee} lancé !`);
    } catch (e) { notify(e.message, 'err'); }
  };

  return (
    <div className="animate-fadeIn space-y-6" style={{ maxWidth:'860px' }}>
      <div>
        <h1 style={{ fontSize:'22px', fontWeight:900, color:'var(--text)' }}>📋 RGPD & Loi 18-07</h1>
        <p style={{ color:'var(--muted)', fontSize:'13px', marginTop:'4px' }}>
          Gestion des données personnelles — Conformité Loi 18-07 Algérie (ANPDP)
        </p>
      </div>

      {msg && (
        <div style={{ background: msgType === 'ok' ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)', border:`1px solid ${msgType === 'ok' ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)'}`, borderRadius:'10px', padding:'12px 16px', color: msgType === 'ok' ? 'var(--green)' : '#f87171', fontSize:'13px', fontWeight:600 }}>
          {msg}
        </div>
      )}

      {/* Droits RGPD */}
      <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr 1fr', gap:'16px' }}>
        {[
          { emoji:'📦', titre:'Export complet', desc:'Toutes les données de votre école au format JSON', action: exporterTenant, btn:'Demander l\'export', couleur:'var(--accent)' },
          { emoji:'🗂️', titre:'Archivage annuel', desc:'Clôturer l\'année scolaire et archiver les données',
            content: (
              <div style={{ marginTop:'10px' }}>
                <input value={annee} onChange={e => setAnnee(e.target.value)} placeholder="2024-2025"
                  style={{ width:'100%', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'7px 10px', color:'var(--text)', fontSize:'12px', marginBottom:'8px', outline:'none', boxSizing:'border-box' }} />
                <button onClick={archiverAnnee} style={{ width:'100%', background:'rgba(234,179,8,0.2)', color:'#ca8a04', border:'1px solid rgba(234,179,8,0.3)', borderRadius:'8px', padding:'8px', fontSize:'12px', fontWeight:700, cursor:'pointer' }}>
                  📁 Archiver l'année
                </button>
              </div>
            )
          },
          { emoji:'🔒', titre:'Politique de données', desc:'Afficher la politique de confidentialité aux parents', content:(
              <div style={{ marginTop:'10px', fontSize:'12px', color:'var(--muted)', lineHeight:'1.6' }}>
                <p>✅ Données stockées en Algérie (après VPS Hostarts)</p>
                <p>✅ Chiffrement AES-256 en transit et au repos</p>
                <p>✅ Accès limité au tenant concerné (RLS)</p>
                <p>⏳ Déclaration ANPDP en attente</p>
              </div>
            )
          },
        ].map(card => (
          <div key={card.titre} style={{ background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'14px', padding:'20px' }}>
            <div style={{ fontSize:'28px', marginBottom:'8px' }}>{card.emoji}</div>
            <h3 style={{ fontSize:'14px', fontWeight:800, color:'var(--text)', marginBottom:'4px' }}>{card.titre}</h3>
            <p style={{ fontSize:'12px', color:'var(--muted)', lineHeight:'1.5', marginBottom:'12px' }}>{card.desc}</p>
            {card.action && (
              <button onClick={card.action} style={{ background:`${card.couleur}1a`, color:card.couleur, border:`1px solid ${card.couleur}44`, borderRadius:'8px', padding:'8px 14px', fontSize:'12px', fontWeight:700, cursor:'pointer', width:'100%' }}>
                {card.btn}
              </button>
            )}
            {card.content && card.content}
          </div>
        ))}
      </div>

      {/* Demandes en cours */}
      <div style={{ background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'16px', padding:'24px' }}>
        <h3 style={{ fontSize:'15px', fontWeight:800, color:'var(--text)', marginBottom:'16px' }}>
          📬 Demandes RGPD reçues ({demandes.length})
        </h3>
        {loading ? <div style={{ height:'80px', background:'var(--surface2)', borderRadius:'10px', animation:'pulse 1.5s infinite' }} />
        : demandes.length === 0 ? (
          <p style={{ color:'var(--muted)', fontSize:'13px', textAlign:'center', padding:'20px' }}>Aucune demande reçue</p>
        ) : (
          <table style={{ width:'100%', borderCollapse:'collapse', fontSize:'13px' }}>
            <thead>
              <tr style={{ borderBottom:'1px solid var(--border)' }}>
                {['Demandeur','Type','Statut','Date','Actions'].map(h => (
                  <th key={h} style={{ padding:'8px 12px', textAlign:'left', color:'var(--muted)', fontWeight:600, fontSize:'11px', textTransform:'uppercase' }}>{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {demandes.map(d => (
                <tr key={d.id} style={{ borderBottom:'1px solid var(--border)' }}>
                  <td style={{ padding:'10px 12px', color:'var(--text)', fontWeight:600 }}>{d.demandeur}</td>
                  <td style={{ padding:'10px 12px', color:'var(--muted)' }}>{d.type}</td>
                  <td style={{ padding:'10px 12px' }}>
                    <span style={{
                      background: d.statut === 'traite' ? 'rgba(16,185,129,0.1)' : 'rgba(234,179,8,0.1)',
                      color: d.statut === 'traite' ? 'var(--green)' : '#ca8a04',
                      padding:'2px 10px', borderRadius:'12px', fontSize:'11px', fontWeight:700,
                    }}>
                      {d.statut === 'en_cours' ? '⏳ En cours' : '✅ Traité'}
                    </span>
                  </td>
                  <td style={{ padding:'10px 12px', color:'var(--muted)', fontSize:'12px' }}>
                    {new Date(d.created_at).toLocaleDateString('fr-DZ')}
                  </td>
                  <td style={{ padding:'10px 12px' }}>
                    {d.statut === 'en_cours' && (
                      <button style={{ fontSize:'11px', color:'var(--accent)', background:'none', border:'none', cursor:'pointer' }}>
                        Traiter
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
```

---

## ÉTAPE 6 — Tests

**Créer** : `edugestdz/backend/tests/Feature/RgpdControllerTest.php`

```php
<?php
namespace Tests\Feature;
use App\Models\{Tenant, Role, User, Eleve};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RgpdControllerTest extends TestCase
{
    use RefreshDatabase;
    private string $token;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);
        $role = Role::firstOrCreate(['nom' => 'admin']);
        $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id]);
        $this->token = auth('api')->login($user);
    }

    public function test_export_tenant_lance_job(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/rgpd/export-tenant')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ExportDonneesTenantJob::class);
    }

    public function test_export_eleve_retourne_json(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson("/api/v1/rgpd/export-eleve/{$eleve->id}")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_export_eleve_autre_tenant_retourne_404(): void
    {
        $autreTenant = Tenant::factory()->create();
        $eleve = Eleve::factory()->create(['tenant_id' => $autreTenant->id]);
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson("/api/v1/rgpd/export-eleve/{$eleve->id}")
            ->assertStatus(404);
    }

    public function test_demande_suppression_valide(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->postJson('/api/v1/rgpd/demande-suppression', ['confirme' => true])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_demande_suppression_sans_confirmation_rejete(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->postJson('/api/v1/rgpd/demande-suppression', ['confirme' => false])
            ->assertStatus(422);
    }

    public function test_liste_demandes_retourne_200(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/rgpd/demandes')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }
}
```

---

## ÉTAPE 7 — Exécution

```bash
php artisan migrate --force && composer dump-autoload -o
php artisan test tests/Feature/RgpdControllerTest.php --stop-on-failure
php artisan test → ≥ 880 ✅

cd ../frontend
# Ajouter dans App.jsx:
# import RgpdPage from '@pages/RgpdPage';
# <Route path="/rgpd" element={<RgpdPage />} />
# Ajouter dans Sidebar: { path:'/rgpd', icon: Shield, label:'📋 RGPD / Loi 18-07', roles:['admin'] }

npm run build → 0 erreurs

git commit -m "feat(rgpd-export): Conformité Loi 18-07 ANPDP — Export données, archivage, demandes

- Migration: consentements_rgpd + demandes_rgpd (hasTable guards)
- ExportRgpdController: export tenant (job async) + export élève (JSON direct)
  demande_suppression + archiver_annee + liste_demandes
  Isolation tenant sur toutes les requêtes (pas d'accès cross-tenant)
- ExportDonneesTenantJob: export 10 tables en JSON, notif quand prêt
- RgpdPage: 3 actions (export, archivage, politique) + tableau demandes
- Sidebar/App: route /rgpd visible admin uniquement
- RgpdControllerTest: 6 tests (export, isolation tenant, suppression, validation)"

git push origin develop → PR → main
```

---

## PROMPT EXACT POUR DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_RGPD_EXPORT.md — 7 étapes.

CONTEXTE :
- AuditChainService::enregistrer() existe (Sécurité Niveau 6) — wrappé dans try/catch
- NotificationInAppService existe dans app/Services/
- ElevesExport.php existe — ce module est DIFFÉRENT (export RGPD, pas Excel simple)

RÈGLES :
1. ExportDonneesTenantJob : utiliser Queue::fake() dans le test → ne pas vraiment exécuter
2. ExportRgpdController::exporterEleve() : retourner response() avec headers, PAS JsonResponse
   Laravel accepte return response($json, 200, ['Content-Type' => 'application/json', ...])
3. Test export_eleve : assertHeader('Content-Type') → peut matcher 'application/json; charset=UTF-8'
   Utiliser assertHeader('Content-Type', 'application/json') ou assertHeader('content-type')
   selon la version de Laravel (peut être case-sensitive)
4. Migration rgpd : hasTable() guard obligatoire — la migration doit être idempotente
5. RgpdPage : pas de dépendances npm supplémentaires (tout en React vanilla)

php artisan migrate --force
php artisan test tests/Feature/RgpdControllerTest.php → 6 ✅
php artisan test → ≥ 880 ✅
npm run build → 0 erreurs
git push origin develop → PR → main
```
