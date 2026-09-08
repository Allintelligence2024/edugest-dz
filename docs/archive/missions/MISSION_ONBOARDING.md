# 🚀 MISSION 5 — Onboarding Wizard (5 Étapes Post-Inscription)
## EduGest DZ · Branche : develop · Tests : 859+ ✅
## Conversion inscription → utilisation réelle — actuellement ~0%

---

## DIAGNOSTIC

```
PROBLÈME RÉEL :
Un directeur s'inscrit → Dashboard vide → aucun guide → abandonne en 5 minutes.

Sans onboarding :
  Étape 1 : S'inscrire → Bien se passe
  Étape 2 : Voir un dashboard avec 0 élève, 0 cours, 0 DA → Panique
  Étape 3 : Chercher comment ajouter un élève → Trouver la page Élèves
  Étape 4 : Ajouter un élève sans avoir créé de groupe → Erreur
  Étape 5 : Abandon

Avec onboarding (ce que cette mission crée) :
  Étape 1 : Matière → Étape 2 : Enseignant → Étape 3 : Groupe/Niveau
  Étape 4 : Premier élève → Étape 5 : Tester une notification → Terminé 🎉

CE QUI EXISTE :
✅ Toutes les APIs nécessaires (matières, enseignants, groupes, élèves)
✅ NotificationInAppService pour l'étape test notification

CE QUI MANQUE :
❌ OnboardingController.php → état d'avancement du wizard
❌ OnboardingPage.jsx → wizard frontend 5 étapes
❌ Colonne 'onboarding_complete' dans tenants
❌ Redirect post-inscription vers /onboarding
```

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## ÉTAPE 1 — Migration : colonne onboarding dans tenants

**Créer** : `edugestdz/backend/database/migrations/2026_07_11_400000_add_onboarding_to_tenants.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tenants', 'onboarding_etape')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->integer('onboarding_etape')->default(0)->after('statut');
                $table->boolean('onboarding_complete')->default(false)->after('onboarding_etape');
                $table->timestamp('onboarding_complete_le')->nullable()->after('onboarding_complete');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'onboarding_etape')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn(['onboarding_etape', 'onboarding_complete', 'onboarding_complete_le']);
            });
        }
    }
};
```

---

## ÉTAPE 2 — OnboardingController

**Créer** : `edugestdz/backend/app/Http/Controllers/Api/V1/OnboardingController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\NotificationInAppService;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;

/**
 * OnboardingController — Wizard d'installation guidée.
 *
 * Les 5 étapes :
 * 0 → Bienvenue
 * 1 → Créer une matière
 * 2 → Ajouter un enseignant
 * 3 → Créer un groupe/niveau
 * 4 → Inscrire le premier élève
 * 5 → Tester une notification → Terminé !
 */
class OnboardingController extends Controller
{
    public function __construct(private NotificationInAppService $notif) {}

    /**
     * État actuel de l'onboarding pour ce tenant.
     */
    public function statut(): JsonResponse
    {
        $tenantId = config('tenant.current_id');
        $tenant   = DB::table('tenants')->where('id', $tenantId)->first();

        $etape    = (int) ($tenant->onboarding_etape ?? 0);
        $complete = (bool)($tenant->onboarding_complete ?? false);

        // Calculer la progression réelle depuis la BDD
        $progression = [
            'matieres'    => DB::table('matieres')->where('tenant_id', $tenantId)->count(),
            'enseignants' => DB::table('enseignants')->where('tenant_id', $tenantId)->count(),
            'groupes'     => DB::table('groupes')->where('tenant_id', $tenantId)->count(),
            'eleves'      => DB::table('eleves')->where('tenant_id', $tenantId)->count(),
        ];

        // Déterminer l'étape réelle selon les données
        $etapeReelle = 0;
        if ($progression['matieres'] > 0)    $etapeReelle = max($etapeReelle, 1);
        if ($progression['enseignants'] > 0) $etapeReelle = max($etapeReelle, 2);
        if ($progression['groupes'] > 0)     $etapeReelle = max($etapeReelle, 3);
        if ($progression['eleves'] > 0)      $etapeReelle = max($etapeReelle, 4);
        if ($complete)                       $etapeReelle = 5;

        return response()->json([
            'success'     => true,
            'etape'       => $etapeReelle,
            'complete'    => $complete,
            'progression' => $progression,
            'etapes'      => [
                ['id' => 1, 'label' => 'Créer une matière',        'complete' => $progression['matieres'] > 0],
                ['id' => 2, 'label' => 'Ajouter un enseignant',    'complete' => $progression['enseignants'] > 0],
                ['id' => 3, 'label' => 'Créer un groupe',          'complete' => $progression['groupes'] > 0],
                ['id' => 4, 'label' => 'Inscrire un élève',        'complete' => $progression['eleves'] > 0],
                ['id' => 5, 'label' => 'Tester une notification',  'complete' => $complete],
            ],
        ]);
    }

    /**
     * Avancer d'une étape et sauvegarder.
     */
    public function avancer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'etape' => 'required|integer|between:1,5',
        ]);

        $tenantId = config('tenant.current_id');

        DB::table('tenants')
            ->where('id', $tenantId)
            ->update(['onboarding_etape' => $validated['etape']]);

        return response()->json(['success' => true, 'etape' => $validated['etape']]);
    }

    /**
     * Étape 5 : envoyer une notification de test au directeur.
     */
    public function testerNotification(): JsonResponse
    {
        $tenantId = config('tenant.current_id');
        $userId   = auth('api')->id();

        $this->notif->creer(
            userId:   $userId,
            type:     'onboarding_test',
            titre:    '🎉 EduGest DZ est prêt !',
            corps:    'Félicitations ! Votre établissement est configuré. Les notifications fonctionnent correctement.',
            meta:     ['action_url' => '/dashboard'],
            tenantId: $tenantId
        );

        // Marquer l'onboarding comme terminé
        DB::table('tenants')->where('id', $tenantId)->update([
            'onboarding_etape'       => 5,
            'onboarding_complete'    => true,
            'onboarding_complete_le' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '🎉 Félicitations ! EduGest DZ est prêt à être utilisé.',
        ]);
    }

    /**
     * Ignorer l'onboarding (skip).
     */
    public function ignorer(): JsonResponse
    {
        DB::table('tenants')
            ->where('id', config('tenant.current_id'))
            ->update(['onboarding_complete' => true, 'onboarding_complete_le' => now()]);

        return response()->json(['success' => true, 'message' => 'Onboarding ignoré.']);
    }
}
```

**Ajouter dans** `routes/api.php` :

```php
use App\Http\Controllers\Api\V1\OnboardingController;

Route::middleware(['auth:api', 'tenant'])->prefix('v1/onboarding')->group(function () {
    Route::get('/',                  [OnboardingController::class, 'statut']);
    Route::post('/avancer',          [OnboardingController::class, 'avancer']);
    Route::post('/tester-notification', [OnboardingController::class, 'testerNotification']);
    Route::post('/ignorer',          [OnboardingController::class, 'ignorer']);
});
```

---

## ÉTAPE 3 — OnboardingPage.jsx (wizard complet)

**Créer** : `edugestdz/frontend/src/pages/OnboardingPage.jsx`

```jsx
import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '@api/client';

const ETAPES = [
  {
    id:    1, emoji: '📚', titre: 'Votre première matière',
    desc:  'Commencez par créer les matières enseignées dans votre établissement.',
    action:'Créer une matière', url:'/api/v1/matieres',
    champs:[
      { key:'nom_fr', label:'Nom en français', placeholder:'Mathématiques', required:true },
      { key:'nom_ar', label:'الاسم بالعربية',  placeholder:'الرياضيات', required:false },
      { key:'code',   label:'Code court',       placeholder:'MATH', required:false },
    ],
  },
  {
    id:    2, emoji: '👩‍🏫', titre: 'Votre premier enseignant',
    desc:  'Ajoutez l\'enseignant qui donnera les premiers cours.',
    action:'Ajouter un enseignant', url:'/api/v1/enseignants',
    champs:[
      { key:'nom',       label:'Nom',       placeholder:'Benali',   required:true },
      { key:'prenom',    label:'Prénom',    placeholder:'Amina',    required:true },
      { key:'email',     label:'Email',     placeholder:'prof@ecole.dz', type:'email', required:true },
      { key:'telephone', label:'Téléphone', placeholder:'0555 12 34 56', required:false },
      { key:'specialite',label:'Spécialité',placeholder:'Mathématiques', required:false },
    ],
  },
  {
    id:    3, emoji: '📋', titre: 'Votre premier groupe',
    desc:  'Créez un groupe de niveau pour regrouper vos élèves.',
    action:'Créer un groupe', url:'/api/v1/groupes',
    champs:[
      { key:'nom',    label:'Nom du groupe',   placeholder:'3ème AM - Groupe A', required:true },
      { key:'niveau', label:'Niveau scolaire', placeholder:'3AM', required:true },
      { key:'capacite_max', label:'Capacité maximale', placeholder:'25', type:'number', required:false },
    ],
  },
  {
    id:    4, emoji: '👨‍🎓', titre: 'Votre premier élève',
    desc:  'Inscrivez le premier élève de votre établissement.',
    action:'Inscrire un élève', url:'/api/v1/eleves',
    champs:[
      { key:'nom',         label:'Nom',    placeholder:'Mammeri',  required:true },
      { key:'prenom',      label:'Prénom', placeholder:'Karim',    required:true },
      { key:'date_naissance', label:'Date de naissance', type:'date', required:false },
      { key:'niveau_scolaire', label:'Niveau',    placeholder:'3AM', required:false },
      { key:'telephone_parent', label:'Téléphone parent', placeholder:'0555 00 00 00', required:false },
    ],
  },
  {
    id:    5, emoji: '🔔', titre: 'Tester les notifications',
    desc:  'Envoyez-vous une notification de test pour confirmer que tout fonctionne.',
    action:'Envoyer la notification test',
    champs:[],
  },
];

export default function OnboardingPage() {
  const navigate   = useNavigate();
  const [statut,   setStatut]   = useState(null);
  const [etapeIdx, setEtapeIdx] = useState(0);
  const [form,     setForm]     = useState({});
  const [loading,  setLoading]  = useState(false);
  const [error,    setError]    = useState('');
  const [success,  setSuccess]  = useState('');

  useEffect(() => {
    api('/onboarding').then(r => {
      if (r.success) {
        setStatut(r);
        // Commencer à l'étape non encore complétée
        const prochaine = r.etapes.findIndex(e => !e.complete);
        setEtapeIdx(prochaine === -1 ? 4 : prochaine);
      }
    }).catch(() => {});
  }, []);

  const etape = ETAPES[etapeIdx];

  const handleSubmit = async () => {
    setLoading(true); setError(''); setSuccess('');
    try {
      if (etape.id === 5) {
        // Notification de test
        await api('/onboarding/tester-notification', { method:'POST' });
        setSuccess('🎉 Notification envoyée ! Votre installation est terminée.');
        setTimeout(() => navigate('/dashboard'), 2000);
      } else {
        // Créer la ressource
        await api(etape.url.replace('/api/v1', ''), { method:'POST', body: JSON.stringify(form) });
        await api('/onboarding/avancer', { method:'POST', body: JSON.stringify({ etape: etape.id }) });
        setSuccess(`✅ ${etape.emoji} ${etape.titre} créé(e) avec succès !`);
        setTimeout(() => {
          setSuccess('');
          setForm({});
          if (etapeIdx < ETAPES.length - 1) setEtapeIdx(e => e + 1);
        }, 1500);
      }
    } catch (e) { setError(e.message ?? 'Erreur lors de la sauvegarde'); }
    finally { setLoading(false); }
  };

  const skip = async () => {
    await api('/onboarding/ignorer', { method:'POST' }).catch(() => {});
    navigate('/dashboard');
  };

  const pctComplete = statut ? (statut.etapes.filter(e => e.complete).length / 5) * 100 : 0;

  return (
    <div style={{ minHeight:'100vh', background:'var(--bg)', display:'flex', alignItems:'center', justifyContent:'center', padding:'20px' }}>
      <div style={{ width:'100%', maxWidth:'600px' }}>

        {/* Header */}
        <div style={{ textAlign:'center', marginBottom:'32px' }}>
          <div style={{ fontSize:'48px', marginBottom:'8px' }}>🎓</div>
          <h1 style={{ fontSize:'26px', fontWeight:900, color:'var(--text)' }}>
            Bienvenue sur <span style={{ color:'var(--accent)' }}>EduGest DZ</span>
          </h1>
          <p style={{ color:'var(--muted)', fontSize:'13px', marginTop:'6px' }}>
            Configurez votre établissement en 5 étapes simples — Moins de 5 minutes
          </p>
        </div>

        {/* Barre de progression */}
        <div style={{ background:'var(--surface2)', borderRadius:'50px', height:'8px', marginBottom:'8px', overflow:'hidden' }}>
          <div style={{ height:'100%', background:'var(--accent)', borderRadius:'50px', width:`${pctComplete}%`, transition:'width 0.5s ease' }} />
        </div>
        <div style={{ display:'flex', justifyContent:'space-between', marginBottom:'28px' }}>
          {ETAPES.map((e, i) => {
            const done = statut?.etapes[i]?.complete;
            return (
              <div key={e.id} style={{ textAlign:'center', cursor: done ? 'pointer' : 'default' }}
                onClick={() => done && setEtapeIdx(i)}>
                <div style={{
                  width:'32px', height:'32px', borderRadius:'50%', margin:'0 auto 4px',
                  display:'flex', alignItems:'center', justifyContent:'center',
                  fontSize:'14px',
                  background: done ? 'rgba(16,185,129,0.2)' : i === etapeIdx ? 'rgba(37,99,235,0.2)' : 'var(--surface2)',
                  border: `2px solid ${done ? 'var(--green)' : i === etapeIdx ? 'var(--accent)' : 'var(--border)'}`,
                }}>
                  {done ? '✓' : e.emoji}
                </div>
                <div style={{ fontSize:'10px', color: i === etapeIdx ? 'var(--text)' : 'var(--muted)', fontWeight: i === etapeIdx ? 700 : 400, maxWidth:'70px' }}>
                  {e.titre.split(' ').slice(0,2).join(' ')}
                </div>
              </div>
            );
          })}
        </div>

        {/* Carte étape */}
        <div style={{ background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'20px', padding:'32px', boxShadow:'0 20px 60px rgba(0,0,0,0.3)' }}>
          <div style={{ textAlign:'center', marginBottom:'24px' }}>
            <div style={{ fontSize:'48px', marginBottom:'8px' }}>{etape.emoji}</div>
            <h2 style={{ fontSize:'20px', fontWeight:800, color:'var(--text)', marginBottom:'6px' }}>
              Étape {etapeIdx + 1} — {etape.titre}
            </h2>
            <p style={{ color:'var(--muted)', fontSize:'13px' }}>{etape.desc}</p>
          </div>

          {error && (
            <div style={{ background:'rgba(239,68,68,0.1)', border:'1px solid rgba(239,68,68,0.3)', borderRadius:'10px', padding:'12px', marginBottom:'16px', color:'#f87171', fontSize:'13px' }}>
              ❌ {error}
            </div>
          )}
          {success && (
            <div style={{ background:'rgba(16,185,129,0.1)', border:'1px solid rgba(16,185,129,0.3)', borderRadius:'10px', padding:'12px', marginBottom:'16px', color:'var(--green)', fontSize:'13px', fontWeight:600 }}>
              {success}
            </div>
          )}

          {/* Champs du formulaire */}
          {etape.champs.length > 0 && (
            <div style={{ display:'grid', gridTemplateColumns: etape.champs.length > 2 ? '1fr 1fr' : '1fr', gap:'14px', marginBottom:'20px' }}>
              {etape.champs.map(ch => (
                <div key={ch.key}>
                  <label style={{ display:'block', fontSize:'12px', fontWeight:700, color:'var(--text)', marginBottom:'5px' }}>
                    {ch.label} {ch.required && <span style={{ color:'var(--red)' }}>*</span>}
                  </label>
                  <input
                    type={ch.type ?? 'text'}
                    value={form[ch.key] ?? ''}
                    onChange={e => setForm(f => ({...f, [ch.key]: e.target.value}))}
                    placeholder={ch.placeholder}
                    required={ch.required}
                    style={{
                      width:'100%', background:'var(--surface2)', border:'1px solid var(--border)',
                      borderRadius:'10px', padding:'9px 12px', color:'var(--text)', fontSize:'13px',
                      outline:'none', boxSizing:'border-box',
                    }}
                  />
                </div>
              ))}
            </div>
          )}

          {/* Étape 5 : notification test */}
          {etape.id === 5 && (
            <div style={{ textAlign:'center', padding:'20px 0' }}>
              <div style={{ fontSize:'64px', marginBottom:'16px' }}>🔔</div>
              <p style={{ color:'var(--muted)', fontSize:'13px', lineHeight:'1.7' }}>
                Cliquez pour recevoir votre première notification EduGest DZ.<br/>
                Elle apparaîtra dans votre cloche 🔔 en haut à droite.
              </p>
            </div>
          )}

          {/* Boutons */}
          <div style={{ display:'flex', gap:'10px' }}>
            <button onClick={handleSubmit} disabled={loading || !!success}
              style={{
                flex:1, background: loading ? 'var(--surface2)' : 'var(--accent)',
                color: loading ? 'var(--muted)' : 'white',
                border:'none', borderRadius:'12px', padding:'14px', fontSize:'15px',
                fontWeight:800, cursor: loading ? 'not-allowed' : 'pointer',
              }}>
              {loading ? '⏳ En cours...' : `${etape.action} →`}
            </button>

            {etapeIdx < ETAPES.length - 1 && (
              <button onClick={() => { setForm({}); setEtapeIdx(e => e + 1); }}
                style={{ background:'none', border:'1px solid var(--border)', borderRadius:'12px', padding:'14px 20px', color:'var(--muted)', fontSize:'13px', cursor:'pointer' }}>
                Passer
              </button>
            )}
          </div>

          {etapeIdx === 0 && (
            <div style={{ textAlign:'center', marginTop:'16px' }}>
              <button onClick={skip} style={{ background:'none', border:'none', color:'var(--muted)', fontSize:'12px', cursor:'pointer', textDecoration:'underline' }}>
                Je suis déjà configuré, accéder au dashboard →
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
```

---

## ÉTAPE 4 — Intégrer dans App.jsx + redirect post-login

**Ajouter dans** `App.jsx` :

```jsx
import OnboardingPage from '@pages/OnboardingPage';

// Route onboarding (protégée mais SANS layout — plein écran)
<Route path="/onboarding" element={<ProtectedRoute><OnboardingPage /></ProtectedRoute>} />
```

**Modifier** `LoginPage.jsx` — dans la fonction `handleSubmit`, après login réussi :

```jsx
// Vérifier si l'onboarding est complet
const onboardingRes = await api('/onboarding').catch(() => null);
const dest = onboardingRes?.complete === false && onboardingRes?.etape < 5
  ? '/onboarding'
  : (user?.role?.nom === 'eleve' ? '/devoirs' : user?.role?.nom === 'enseignant' ? '/planning' : '/dashboard');
navigate(dest, { replace: true });
```

---

## ÉTAPE 5 — Tests

**Créer** : `edugestdz/backend/tests/Feature/OnboardingTest.php`

```php
<?php
namespace Tests\Feature;
use App\Models\{Tenant, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
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

    public function test_statut_retourne_progression(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/onboarding')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['etape', 'complete', 'progression', 'etapes']);
    }

    public function test_avancer_sauvegarde_etape(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->postJson('/api/v1/onboarding/avancer', ['etape' => 2])
            ->assertStatus(200)
            ->assertJsonPath('etape', 2);
    }

    public function test_etape_invalide_retourne_422(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->postJson('/api/v1/onboarding/avancer', ['etape' => 99])
            ->assertStatus(422);
    }

    public function test_ignorer_marque_complete(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->postJson('/api/v1/onboarding/ignorer')
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/onboarding')
            ->assertJsonPath('complete', true);
    }

    public function test_tester_notification_marque_complete(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->postJson('/api/v1/onboarding/tester-notification')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/onboarding')
            ->assertJsonPath('complete', true);
    }
}
```

---

## ÉTAPE 6 — Exécution

```bash
php artisan migrate --force && composer dump-autoload -o
php artisan test tests/Feature/OnboardingTest.php --stop-on-failure
php artisan test → ≥ 875 ✅
npm run build → 0 erreurs

git commit -m "feat(onboarding): Wizard 5 étapes post-inscription — conversion inscription→utilisation

- Migration: onboarding_etape + onboarding_complete + onboarding_complete_le dans tenants
- OnboardingController: statut (progression réelle depuis BDD), avancer, ignorer, testerNotification
- OnboardingPage: wizard plein écran 5 étapes (matière→enseignant→groupe→élève→notification test)
  Barre progression, formulaires inline, skip individuel, redirect post-completion
- LoginPage: redirect /onboarding si onboarding_complete=false après login admin
- OnboardingTest: 5 tests (statut, avancer, invalide, ignorer, notification)"

git push origin develop → PR → main
```

---

## PROMPT EXACT POUR DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_ONBOARDING.md — 6 étapes.

CONTEXTE :
- Les tables 'matieres', 'enseignants', 'groupes', 'eleves' existent
- NotificationInAppService existe dans app/Services/
- La table 'tenants' existe — ajouter les colonnes avec hasColumn() guards

RÈGLES :
1. Migration hasColumn() : vérifier que 'onboarding_etape' n'existe pas avant ALTER TABLE
2. OnboardingController::statut() : si la colonne onboarding_complete n'existe pas encore
   (migration pas encore jouée) → retourner complete=false, etape=0 sans crasher
3. OnboardingPage : l'URL dans ETAPES (/api/v1/matieres etc.) doit utiliser
   la fonction api() de client.js qui préfixe automatiquement /api/v1
   Donc l'URL dans le config devient '/matieres', '/enseignants', etc.
4. La route /onboarding dans App.jsx doit être AVANT le catch-all /* pour être trouvée
5. LoginPage redirect onboarding : appeler l'API /onboarding SEULEMENT pour les admins
   Un élève ou parent ne doit pas être redirigé vers l'onboarding.

php artisan migrate --force
php artisan test tests/Feature/OnboardingTest.php → 5 ✅
php artisan test → ≥ 875 ✅
npm run build → 0 erreurs
git push origin develop → PR → main
```
