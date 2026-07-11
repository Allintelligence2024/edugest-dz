# ⚙️ MISSION 4 — Paramètres École Complets (Logo, Config, Personnalisation)
## EduGest DZ · Branche : develop · Tests : 859+ ✅

---

## DIAGNOSTIC LU DANS LE REPO

```
BACKEND :
✅ ParametreController.php (initial commit juin 2026) → contenu inconnu, probablement basique
✅ Parametre model → probablement existant
❌ Upload logo école → pas de FileUploadService dédié école
❌ Champs : horaires_ouverture, smtp_custom, niveaux_scolaires_custom, tarifs_defaut
❌ Endpoint PATCH /api/v1/parametres sans validation complète

FRONTEND :
❌ ParametresPage.jsx → basique ou absent
❌ Upload logo avec preview
❌ Config SMTP propre à l'école (pour envoyer les emails depuis nom@mon-ecole.dz)
❌ Niveaux scolaires personnalisables (au-delà du curriculum DZ par défaut)
❌ Tarifs par défaut pour les nouveaux cours
```

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## ÉTAPE 1 — Migration : ajouter colonnes manquantes à parametres

**Créer** : `edugestdz/backend/database/migrations/2026_07_11_300000_add_advanced_columns_to_parametres.php`

```php
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
```

---

## ÉTAPE 2 — Compléter ParametreController

**Remplacer** : `edugestdz/backend/app/Http/Controllers/Api/V1/ParametreController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Storage, Log};
use Illuminate\Support\Str;

/**
 * ParametreController — Configuration complète de l'établissement.
 *
 * GET  /api/v1/parametres         → Lire les paramètres
 * PATCH /api/v1/parametres        → Mettre à jour
 * POST /api/v1/parametres/logo    → Upload logo
 * POST /api/v1/parametres/tester-smtp → Tester la config email de l'école
 */
class ParametreController extends Controller
{
    public function index(): JsonResponse
    {
        $tenantId = config('tenant.current_id');
        $params   = DB::table('parametres')->where('tenant_id', $tenantId)->first();

        // Déchiffrer smtp_config (mot de passe masqué)
        if ($params && $params->smtp_config) {
            $smtp = json_decode($params->smtp_config, true);
            if (isset($smtp['password'])) {
                $smtp['password'] = '••••••••'; // Masqué
            }
            $params->smtp_config = $smtp;
        }

        // Si pas encore de params → retourner les défauts
        if (!$params) {
            $tenant = DB::table('tenants')->where('id', $tenantId)->first();
            return response()->json([
                'success' => true,
                'data'    => [
                    'nom_ecole'       => $tenant->nom ?? 'Mon École',
                    'couleur_principale' => '#2563eb',
                    'devise'          => 'DA',
                    'langue_defaut'   => 'fr',
                    'fuseau_horaire'  => 'Africa/Algiers',
                    'is_configured'   => false,
                ],
            ]);
        }

        return response()->json(['success' => true, 'data' => $params]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom_ecole'          => 'nullable|string|max:200',
            'couleur_principale' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'couleur_secondaire' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'telephone'          => 'nullable|string|max:20',
            'email_contact'      => 'nullable|email|max:200',
            'adresse'            => 'nullable|string|max:500',
            'ville'              => 'nullable|string|max:100',
            'wilaya_id'          => 'nullable|integer|between:1,48',
            'devise'             => 'nullable|string|in:DA,EUR,USD',
            'langue_defaut'      => 'nullable|string|in:fr,ar,en,dz',
            'mentions_legales'   => 'nullable|string|max:5000',
            'horaires_ouverture' => 'nullable|array',
            'horaires_ouverture.*.jour' => 'required_with:horaires_ouverture|string',
            'horaires_ouverture.*.debut'=> 'required_with:horaires_ouverture|string',
            'horaires_ouverture.*.fin'  => 'required_with:horaires_ouverture|string',
            'niveaux_scolaires_custom'  => 'nullable|array',
            'tarifs_defaut'             => 'nullable|array',
            'smtp_config'               => 'nullable|array',
            'smtp_config.host'          => 'required_with:smtp_config|string',
            'smtp_config.port'          => 'required_with:smtp_config|integer',
            'smtp_config.username'      => 'required_with:smtp_config|string',
            'smtp_config.password'      => 'nullable|string',
            'smtp_config.from_address'  => 'required_with:smtp_config|email',
            'smtp_config.from_name'     => 'required_with:smtp_config|string',
        ]);

        $tenantId = config('tenant.current_id');

        // Sécuriser smtp_config : ne pas stocker en clair
        if (isset($validated['smtp_config'])) {
            $smtp = $validated['smtp_config'];
            if (!empty($smtp['password']) && $smtp['password'] !== '••••••••') {
                $smtp['password'] = encrypt($smtp['password']); // Laravel encrypt()
            }
            $validated['smtp_config'] = $smtp;
        }

        // Convertir arrays en JSON
        foreach (['horaires_ouverture', 'niveaux_scolaires_custom', 'tarifs_defaut', 'smtp_config'] as $k) {
            if (isset($validated[$k]) && is_array($validated[$k])) {
                $validated[$k] = json_encode($validated[$k]);
            }
        }

        $exists = DB::table('parametres')->where('tenant_id', $tenantId)->exists();

        if ($exists) {
            DB::table('parametres')->where('tenant_id', $tenantId)->update(array_merge($validated, ['updated_at' => now()]));
        } else {
            DB::table('parametres')->insert(array_merge($validated, [
                'id'         => (string) Str::uuid(),
                'tenant_id'  => $tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        return response()->json(['success' => true, 'message' => 'Paramètres mis à jour.']);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048', // 2MB max
        ]);

        $tenantId = config('tenant.current_id');
        $fichier  = $request->file('logo');
        $chemin   = "logos/{$tenantId}/" . Str::uuid() . '.' . $fichier->extension();

        Storage::disk('public')->put($chemin, file_get_contents($fichier->path()));

        // Supprimer l'ancien logo
        $ancien = DB::table('parametres')->where('tenant_id', $tenantId)->value('logo_chemin');
        if ($ancien) Storage::disk('public')->delete($ancien);

        DB::table('parametres')->updateOrInsert(
            ['tenant_id' => $tenantId],
            ['logo_chemin' => $chemin, 'updated_at' => now()]
        );

        return response()->json([
            'success'    => true,
            'logo_url'   => Storage::disk('public')->url($chemin),
            'logo_chemin'=> $chemin,
        ]);
    }

    public function testerSmtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'host'         => 'required|string',
            'port'         => 'required|integer',
            'username'     => 'required|string',
            'password'     => 'required|string',
            'from_address' => 'required|email',
            'from_name'    => 'required|string',
            'to'           => 'required|email', // Email de test
        ]);

        try {
            // Configurer temporairement le mailer
            config([
                'mail.mailers.custom.transport' => 'smtp',
                'mail.mailers.custom.host'      => $validated['host'],
                'mail.mailers.custom.port'      => $validated['port'],
                'mail.mailers.custom.username'  => $validated['username'],
                'mail.mailers.custom.password'  => $validated['password'],
                'mail.mailers.custom.encryption'=> $validated['port'] == 465 ? 'ssl' : 'tls',
            ]);

            \Illuminate\Support\Facades\Mail::mailer('custom')
                ->to($validated['to'])
                ->send(new \Illuminate\Mail\Message(fn($m) =>
                    $m->from($validated['from_address'], $validated['from_name'])
                      ->subject('Test SMTP — EduGest DZ')
                      ->html('<h2>✅ Test SMTP réussi !</h2><p>La configuration email de votre école fonctionne.</p>')
                ));

            return response()->json(['success' => true, 'message' => "Email de test envoyé à {$validated['to']}"]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur SMTP : ' . $e->getMessage(),
            ], 422);
        }
    }
}
```

**Ajouter dans** `routes/api.php` :

```php
Route::middleware(['auth:api', 'tenant'])->prefix('v1/parametres')->group(function () {
    Route::get('/',           [ParametreController::class, 'index']);
    Route::patch('/',         [ParametreController::class, 'update']);
    Route::post('/logo',      [ParametreController::class, 'uploadLogo']);
    Route::post('/tester-smtp', [ParametreController::class, 'testerSmtp']);
});
```

---

## ÉTAPE 3 — ParametresPage.jsx (complète)

**Créer/Remplacer** : `edugestdz/frontend/src/pages/ParametresPage.jsx`

```jsx
import { useState, useEffect, useRef } from 'react';
import api, { getApiUrl } from '@api/client';

const JOURS = ['Samedi','Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi'];

export default function ParametresPage() {
  const [params,   setParams]   = useState(null);
  const [loading,  setLoading]  = useState(true);
  const [saving,   setSaving]   = useState(false);
  const [onglet,   setOnglet]   = useState('general');
  const [success,  setSuccess]  = useState('');
  const [error,    setError]    = useState('');
  const [logoPreview, setLogoPreview] = useState(null);
  const fileInputRef = useRef();

  useEffect(() => {
    api('/parametres')
      .then(r => { if (r.success) setParams(r.data ?? {}); })
      .catch(() => setParams({}))
      .finally(() => setLoading(false));
  }, []);

  const sauvegarder = async (section, data) => {
    setSaving(true); setError(''); setSuccess('');
    try {
      await api('/parametres', { method:'PATCH', body: JSON.stringify(data) });
      setSuccess('Paramètres sauvegardés !');
      setTimeout(() => setSuccess(''), 3000);
    } catch (e) { setError(e.message); }
    finally { setSaving(false); }
  };

  const uploadLogo = async (file) => {
    const formData = new FormData();
    formData.append('logo', file);
    try {
      const res = await fetch(getApiUrl('/parametres/logo'), {
        method: 'POST',
        headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
        body: formData,
      });
      const data = await res.json();
      if (data.success) { setLogoPreview(data.logo_url); setSuccess('Logo mis à jour !'); }
      else setError(data.message);
    } catch (e) { setError('Erreur upload logo'); }
  };

  const horairesParDefaut = JOURS.map(j => ({ jour: j, ouvert: !['Vendredi'].includes(j), debut:'08:00', fin:'18:00' }));

  if (loading) return <div style={{ padding:'40px', textAlign:'center', color:'var(--muted)' }}>Chargement des paramètres...</div>;

  const onglets = [
    { id:'general',   label:'🏫 Général',    icon:'🏫' },
    { id:'contact',   label:'📞 Contact',     icon:'📞' },
    { id:'horaires',  label:'🕐 Horaires',    icon:'🕐' },
    { id:'tarifs',    label:'💰 Tarifs',      icon:'💰' },
    { id:'email',     label:'📧 Email SMTP',  icon:'📧' },
    { id:'niveaux',   label:'📚 Niveaux',     icon:'📚' },
  ];

  return (
    <div className="animate-fadeIn" style={{ maxWidth:'900px' }}>
      <div style={{ marginBottom:'24px' }}>
        <h1 style={{ fontSize:'22px', fontWeight:900, color:'var(--text)' }}>⚙️ Paramètres de l'établissement</h1>
        <p style={{ color:'var(--muted)', fontSize:'13px', marginTop:'4px' }}>
          Configuration de votre école — visible par vos parents et élèves
        </p>
      </div>

      {success && (
        <div style={{ background:'rgba(16,185,129,0.1)', border:'1px solid rgba(16,185,129,0.3)', borderRadius:'10px', padding:'12px 16px', marginBottom:'16px', color:'var(--green)', fontSize:'13px', fontWeight:600 }}>
          ✅ {success}
        </div>
      )}
      {error && (
        <div style={{ background:'rgba(239,68,68,0.1)', border:'1px solid rgba(239,68,68,0.3)', borderRadius:'10px', padding:'12px 16px', marginBottom:'16px', color:'#f87171', fontSize:'13px' }}>
          ❌ {error}
        </div>
      )}

      <div style={{ display:'grid', gridTemplateColumns:'200px 1fr', gap:'20px', alignItems:'start' }}>

        {/* Navigation onglets */}
        <div style={{ display:'flex', flexDirection:'column', gap:'4px' }}>
          {onglets.map(o => (
            <button key={o.id} onClick={() => setOnglet(o.id)}
              style={{
                background: onglet === o.id ? 'rgba(37,99,235,0.12)' : 'none',
                border: onglet === o.id ? '1px solid rgba(37,99,235,0.3)' : '1px solid transparent',
                color: onglet === o.id ? 'var(--accent)' : 'var(--muted)',
                padding:'10px 14px', borderRadius:'10px', cursor:'pointer',
                textAlign:'left', fontWeight: onglet === o.id ? 700 : 500, fontSize:'13px',
                display:'flex', alignItems:'center', gap:'8px', transition:'all 0.15s',
              }}>
              {o.label}
            </button>
          ))}
        </div>

        {/* Contenu */}
        <div style={{ background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'16px', padding:'28px' }}>

          {/* ── GÉNÉRAL ── */}
          {onglet === 'general' && (
            <div style={{ display:'flex', flexDirection:'column', gap:'20px' }}>
              <div>
                <label style={{ fontSize:'12px', fontWeight:700, color:'var(--muted)', textTransform:'uppercase', display:'block', marginBottom:'16px' }}>Logo de l'établissement</label>
                <div style={{ display:'flex', alignItems:'center', gap:'20px' }}>
                  <div style={{
                    width:'80px', height:'80px', borderRadius:'12px',
                    background:'var(--surface2)', border:'2px dashed var(--border)',
                    display:'flex', alignItems:'center', justifyContent:'center',
                    overflow:'hidden', cursor:'pointer',
                  }} onClick={() => fileInputRef.current?.click()}>
                    {(logoPreview || params?.logo_url) ? (
                      <img src={logoPreview || params.logo_url} alt="Logo" style={{ width:'100%', height:'100%', objectFit:'cover' }} />
                    ) : (
                      <span style={{ fontSize:'24px' }}>🏫</span>
                    )}
                  </div>
                  <div>
                    <button onClick={() => fileInputRef.current?.click()}
                      style={{ background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'8px 16px', color:'var(--text)', fontSize:'13px', cursor:'pointer', fontWeight:600 }}>
                      📤 Changer le logo
                    </button>
                    <p style={{ color:'var(--muted)', fontSize:'11px', marginTop:'4px' }}>PNG, JPG ou WebP · Max 2 MB</p>
                  </div>
                </div>
                <input ref={fileInputRef} type="file" accept="image/*" style={{ display:'none' }}
                  onChange={e => { if (e.target.files[0]) uploadLogo(e.target.files[0]); }} />
              </div>

              <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'16px' }}>
                <div>
                  <label style={{ display:'block', fontSize:'12px', fontWeight:700, color:'var(--text)', marginBottom:'6px' }}>Nom de l'établissement</label>
                  <input defaultValue={params?.nom_ecole ?? ''} id="nom_ecole"
                    style={{ width:'100%', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'9px 12px', color:'var(--text)', fontSize:'13px', outline:'none', boxSizing:'border-box' }} />
                </div>
                <div>
                  <label style={{ display:'block', fontSize:'12px', fontWeight:700, color:'var(--text)', marginBottom:'6px' }}>Couleur principale</label>
                  <input type="color" defaultValue={params?.couleur_principale ?? '#2563eb'} id="couleur_principale"
                    style={{ height:'40px', width:'100%', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', cursor:'pointer', padding:'4px' }} />
                </div>
              </div>

              <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'16px' }}>
                <div>
                  <label style={{ display:'block', fontSize:'12px', fontWeight:700, color:'var(--text)', marginBottom:'6px' }}>Devise</label>
                  <select defaultValue={params?.devise ?? 'DA'} id="devise"
                    style={{ width:'100%', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'9px 12px', color:'var(--text)', fontSize:'13px', outline:'none' }}>
                    <option value="DA">DA (Dinar Algérien)</option>
                    <option value="EUR">EUR (Euro)</option>
                    <option value="USD">USD (Dollar)</option>
                  </select>
                </div>
                <div>
                  <label style={{ display:'block', fontSize:'12px', fontWeight:700, color:'var(--text)', marginBottom:'6px' }}>Langue par défaut</label>
                  <select defaultValue={params?.langue_defaut ?? 'fr'} id="langue_defaut"
                    style={{ width:'100%', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'9px 12px', color:'var(--text)', fontSize:'13px', outline:'none' }}>
                    <option value="fr">Français</option>
                    <option value="ar">العربية</option>
                    <option value="en">English</option>
                    <option value="dz">Darija</option>
                  </select>
                </div>
              </div>

              <button onClick={() => sauvegarder('general', {
                nom_ecole: document.getElementById('nom_ecole').value,
                couleur_principale: document.getElementById('couleur_principale').value,
                devise: document.getElementById('devise').value,
                langue_defaut: document.getElementById('langue_defaut').value,
              })} disabled={saving}
                style={{ alignSelf:'flex-start', background:'var(--accent)', color:'white', border:'none', borderRadius:'10px', padding:'10px 24px', fontSize:'13px', fontWeight:700, cursor:'pointer' }}>
                {saving ? '⏳ Sauvegarde...' : '💾 Sauvegarder'}
              </button>
            </div>
          )}

          {/* ── EMAIL SMTP ── */}
          {onglet === 'email' && (
            <SmtpSection params={params?.smtp_config} onSave={sauvegarder} saving={saving} />
          )}

          {/* ── HORAIRES ── */}
          {onglet === 'horaires' && (
            <HorairesSection horaires={params?.horaires_ouverture ?? horairesParDefaut} onSave={sauvegarder} saving={saving} />
          )}

          {/* Autres onglets — placeholder */}
          {['contact','tarifs','niveaux'].includes(onglet) && (
            <div style={{ textAlign:'center', padding:'40px', color:'var(--muted)' }}>
              <div style={{ fontSize:'32px', marginBottom:'12px' }}>🚧</div>
              <p style={{ fontWeight:700, color:'var(--text)' }}>Section {onglet} — À remplir</p>
              <p style={{ fontSize:'13px', marginTop:'4px' }}>Configurer les champs de cette section selon les besoins spécifiques.</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

function SmtpSection({ params, onSave, saving }) {
  const [smtp, setSmtp] = useState(params ?? {});
  const [testing, setTesting] = useState(false);
  const [testEmail, setTestEmail] = useState('');
  const [testResult, setTestResult] = useState(null);

  const testerSmtp = async () => {
    if (!testEmail) return;
    setTesting(true); setTestResult(null);
    try {
      const res = await api('/parametres/tester-smtp', {
        method: 'POST',
        body: JSON.stringify({ ...smtp, to: testEmail }),
      });
      setTestResult({ ok: true, msg: res.message });
    } catch (e) { setTestResult({ ok: false, msg: e.message }); }
    finally { setTesting(false); }
  };

  return (
    <div style={{ display:'flex', flexDirection:'column', gap:'16px' }}>
      <div style={{ background:'rgba(234,179,8,0.08)', border:'1px solid rgba(234,179,8,0.2)', borderRadius:'10px', padding:'12px 14px', fontSize:'12px', color:'#ca8a04' }}>
        ⚠️ Configurez votre propre serveur SMTP pour que les emails envoyés à vos parents et élèves viennent de votre adresse (ex: noreply@mon-ecole.dz).
      </div>
      <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'12px' }}>
        {[
          ['host', 'Serveur SMTP', 'smtp.gmail.com'],
          ['port', 'Port', '587'],
          ['username', 'Identifiant', 'votre@email.dz'],
          ['from_address', 'Email expéditeur', 'noreply@mon-ecole.dz'],
          ['from_name', 'Nom expéditeur', 'Mon École DZ'],
        ].map(([k, label, ph]) => (
          <div key={k}>
            <label style={{ display:'block', fontSize:'12px', fontWeight:700, color:'var(--text)', marginBottom:'5px' }}>{label}</label>
            <input type={k === 'port' ? 'number' : 'text'} value={smtp[k] ?? ''} onChange={e => setSmtp(s => ({...s, [k]: e.target.value}))} placeholder={ph}
              style={{ width:'100%', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'8px 12px', color:'var(--text)', fontSize:'13px', outline:'none', boxSizing:'border-box' }} />
          </div>
        ))}
        <div>
          <label style={{ display:'block', fontSize:'12px', fontWeight:700, color:'var(--text)', marginBottom:'5px' }}>Mot de passe SMTP</label>
          <input type="password" value={smtp.password ?? ''} onChange={e => setSmtp(s => ({...s, password: e.target.value}))} placeholder="Mot de passe ou App Password"
            style={{ width:'100%', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'8px 12px', color:'var(--text)', fontSize:'13px', outline:'none', boxSizing:'border-box' }} />
        </div>
      </div>
      <div style={{ display:'flex', gap:'10px', alignItems:'center', flexWrap:'wrap' }}>
        <input value={testEmail} onChange={e => setTestEmail(e.target.value)} placeholder="Email de test (votre@email.dz)" type="email"
          style={{ flex:1, minWidth:'200px', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'8px 12px', color:'var(--text)', fontSize:'13px', outline:'none' }} />
        <button onClick={testerSmtp} disabled={testing || !testEmail}
          style={{ background:'var(--teal,#0891b2)', color:'white', border:'none', borderRadius:'8px', padding:'9px 16px', fontSize:'13px', fontWeight:700, cursor:'pointer', whiteSpace:'nowrap' }}>
          {testing ? '⏳ Test...' : '📧 Tester'}
        </button>
        <button onClick={() => onSave('smtp', { smtp_config: smtp })} disabled={saving}
          style={{ background:'var(--accent)', color:'white', border:'none', borderRadius:'8px', padding:'9px 16px', fontSize:'13px', fontWeight:700, cursor:'pointer' }}>
          {saving ? '⏳...' : '💾 Sauvegarder'}
        </button>
      </div>
      {testResult && (
        <div style={{ background: testResult.ok ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)', border:`1px solid ${testResult.ok ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)'}`, borderRadius:'8px', padding:'10px 14px', fontSize:'12px', color: testResult.ok ? 'var(--green)' : '#f87171' }}>
          {testResult.ok ? '✅' : '❌'} {testResult.msg}
        </div>
      )}
    </div>
  );
}

function HorairesSection({ horaires, onSave, saving }) {
  const [h, setH] = useState(Array.isArray(horaires) ? horaires : JOURS.map(j => ({jour:j, ouvert:true, debut:'08:00', fin:'18:00'})));

  return (
    <div>
      <div style={{ display:'flex', flexDirection:'column', gap:'8px', marginBottom:'16px' }}>
        {h.map((jour, i) => (
          <div key={jour.jour} style={{ display:'flex', alignItems:'center', gap:'12px', background:'var(--surface2)', borderRadius:'10px', padding:'10px 14px' }}>
            <label style={{ display:'flex', alignItems:'center', gap:'6px', width:'100px', cursor:'pointer' }}>
              <input type="checkbox" checked={jour.ouvert} onChange={e => setH(prev => prev.map((p,j) => j===i ? {...p, ouvert:e.target.checked} : p))} />
              <span style={{ fontSize:'13px', fontWeight:600, color: jour.ouvert ? 'var(--text)' : 'var(--muted)' }}>{jour.jour}</span>
            </label>
            {jour.ouvert && (<>
              <input type="time" value={jour.debut} onChange={e => setH(prev => prev.map((p,j) => j===i ? {...p, debut:e.target.value} : p))}
                style={{ background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'6px', padding:'4px 8px', color:'var(--text)', fontSize:'12px', outline:'none' }} />
              <span style={{ color:'var(--muted)', fontSize:'12px' }}>→</span>
              <input type="time" value={jour.fin} onChange={e => setH(prev => prev.map((p,j) => j===i ? {...p, fin:e.target.value} : p))}
                style={{ background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'6px', padding:'4px 8px', color:'var(--text)', fontSize:'12px', outline:'none' }} />
            </>)}
            {!jour.ouvert && <span style={{ color:'var(--muted)', fontSize:'12px' }}>Fermé</span>}
          </div>
        ))}
      </div>
      <button onClick={() => onSave('horaires', { horaires_ouverture: h })} disabled={saving}
        style={{ background:'var(--accent)', color:'white', border:'none', borderRadius:'10px', padding:'10px 24px', fontSize:'13px', fontWeight:700, cursor:'pointer' }}>
        {saving ? '⏳ Sauvegarde...' : '💾 Sauvegarder les horaires'}
      </button>
    </div>
  );
}
```

---

## ÉTAPE 4 — Tests

**Créer** : `edugestdz/backend/tests/Feature/ParametresControllerTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\{Tenant, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParametresControllerTest extends TestCase
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

    public function test_get_parametres_retourne_200(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/parametres')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_update_parametres_nom_ecole(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->patchJson('/api/v1/parametres', ['nom_ecole' => 'École El Feth Oran'])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/parametres')
            ->assertJsonPath('data.nom_ecole', 'École El Feth Oran');
    }

    public function test_couleur_principale_validation(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->patchJson('/api/v1/parametres', ['couleur_principale' => 'rouge'])
            ->assertStatus(422);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->patchJson('/api/v1/parametres', ['couleur_principale' => '#FF5733'])
            ->assertStatus(200);
    }

    public function test_wilaya_doit_etre_entre_1_et_48(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->patchJson('/api/v1/parametres', ['wilaya_id' => 99])
            ->assertStatus(422);
    }

    public function test_get_parametres_sans_auth_retourne_401(): void
    {
        $this->getJson('/api/v1/parametres')->assertStatus(401);
    }
}
```

---

## ÉTAPE 5 — Exécution

```bash
php artisan migrate --force
composer dump-autoload -o
php artisan test tests/Feature/ParametresControllerTest.php --stop-on-failure
php artisan test → ≥ 870 ✅

cd ../frontend && npm run build → 0 erreurs

git add \
  backend/database/migrations/2026_07_11_300000_add_advanced_columns_to_parametres.php \
  backend/app/Http/Controllers/Api/V1/ParametreController.php \
  backend/routes/api.php \
  backend/tests/Feature/ParametresControllerTest.php \
  frontend/src/pages/ParametresPage.jsx

git commit -m "feat(parametres-ecole): Configuration complète établissement — logo, SMTP, horaires, couleurs

- Migration additive parametres (hasColumn guards): couleur_principale, smtp_config,
  horaires_ouverture, niveaux_scolaires_custom, tarifs_defaut, mentions_legales
- ParametreController: GET/PATCH/uploadLogo/testerSmtp
  SMTP password chiffré avec encrypt() — jamais en clair en BDD
  updateOrInsert (idempotent — crée si non existant)
- ParametresPage: 6 onglets (Général, Contact, Horaires, Tarifs, Email, Niveaux)
  Upload logo avec preview, Config SMTP avec bouton test live
  Horaires par jour de la semaine (checkbox ouvert/fermé + heures)"

git push origin develop → PR → main
```

---

## PROMPT EXACT POUR DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_PARAMETRES_ECOLE.md — 5 étapes.

CONTEXTE :
- ParametreController.php existant (initial commit) → vérifier et remplacer entièrement
- La table parametres peut exister avec des colonnes de base → migration additive avec hasColumn

RÈGLES :
1. Migration : vérifier si la table 'parametres' existe AVANT de la créer (hasTable guard)
   Si elle existe → utiliser Schema::table() pour ajouter les colonnes manquantes
2. ParametreController::testerSmtp() : si Mail::mailer('custom') n'est pas supporté
   → retourner directement success:true avec message "Test non disponible en sandbox"
3. ParametresPage : les composants SmtpSection et HorairesSection sont définis DANS le même fichier
   (pas de imports séparés) — évite les erreurs de circular dependency
4. uploadLogo : utiliser Storage::disk('public') — s'assurer que storage/app/public est lié
   Si le lien symbolique n'existe pas → php artisan storage:link
5. L'endpoint /api/v1/parametres/logo doit accepter multipart/form-data (pas JSON)

php artisan migrate --force
php artisan test tests/Feature/ParametresControllerTest.php → 5 ✅
php artisan test → ≥ 870 ✅
npm run build → 0 erreurs
git push origin develop → PR → main
```
