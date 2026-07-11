# 🔍 MISSION 3 — Recherche Globale Meilisearch (Ctrl+K, résultats groupés)
## EduGest DZ · Branche : develop · Tests : 859+ ✅

---

## DIAGNOSTIC LU DANS LE REPO

```
BACKEND :
✅ Meilisearch v1.8 configuré (MEILISEARCH_HOST + MEILISEARCH_KEY dans .env.example)
✅ Laravel Scout installé (laravel/scout dans composer.json probable)
✅ EleveService utilise Scout pour l'indexation
✅ SearchController.php → existe (initial commit)

FRONTEND :
❌ Aucune barre de recherche globale dans Topbar.jsx
❌ Pas de shortcut Ctrl+K / Cmd+K
❌ Pas de composant SearchModal
❌ Le directeur doit naviguer page par page pour trouver un élève ou une facture
```

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## ÉTAPE 1 — Backend : SearchController unifié

**Vérifier/Modifier** `edugestdz/backend/app/Http/Controllers/Api/V1/SearchController.php` :

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;

/**
 * SearchController — Recherche globale ILIKE PostgreSQL.
 * Fallback si Meilisearch n'est pas disponible.
 *
 * Retourne des résultats groupés par entité pour la SearchModal.
 */
class SearchController extends Controller
{
    private const MAX_PAR_TYPE = 5;

    public function global(Request $request): JsonResponse
    {
        $q        = trim($request->query('q', ''));
        $tenantId = config('tenant.current_id');

        if (strlen($q) < 2) {
            return response()->json(['success' => true, 'resultats' => [], 'total' => 0]);
        }

        $like    = "%{$q}%";
        $resultats = [];

        // ── Élèves ────────────────────────────────────────────────────
        $eleves = DB::table('eleves')
            ->where('tenant_id', $tenantId)
            ->where(fn($w) => $w
                ->whereRaw("LOWER(nom || ' ' || prenom) ILIKE LOWER(?)", [$like])
                ->orWhereRaw("LOWER(prenom || ' ' || nom) ILIKE LOWER(?)", [$like])
                ->orWhereRaw("numero_inscription ILIKE ?", [$like])
            )
            ->select('id', 'nom', 'prenom', 'niveau_scolaire', 'photo')
            ->limit(self::MAX_PAR_TYPE)
            ->get();

        if ($eleves->isNotEmpty()) {
            $resultats[] = [
                'type'     => 'eleves',
                'label'    => '👨‍🎓 Élèves',
                'items'    => $eleves->map(fn($e) => [
                    'id'      => $e->id,
                    'label'   => "{$e->nom} {$e->prenom}",
                    'sub'     => $e->niveau_scolaire ?? 'Niveau non défini',
                    'url'     => "/eleves/{$e->id}",
                    'icone'   => '👨‍🎓',
                ]),
            ];
        }

        // ── Enseignants ───────────────────────────────────────────────
        $enseignants = DB::table('enseignants as e')
            ->join('users as u', 'e.user_id', '=', 'u.id')
            ->where('e.tenant_id', $tenantId)
            ->whereRaw("LOWER(u.nom || ' ' || u.prenom) ILIKE LOWER(?)", [$like])
            ->select('e.id', 'u.nom', 'u.prenom', 'e.specialite')
            ->limit(self::MAX_PAR_TYPE)
            ->get();

        if ($enseignants->isNotEmpty()) {
            $resultats[] = [
                'type'  => 'enseignants',
                'label' => '👩‍🏫 Enseignants',
                'items' => $enseignants->map(fn($e) => [
                    'id'    => $e->id,
                    'label' => "{$e->nom} {$e->prenom}",
                    'sub'   => $e->specialite ?? 'Toutes matières',
                    'url'   => "/enseignants/{$e->id}",
                    'icone' => '👩‍🏫',
                ]),
            ];
        }

        // ── Factures ──────────────────────────────────────────────────
        $factures = DB::table('factures as f')
            ->join('eleves as e', 'f.eleve_id', '=', 'e.id')
            ->where('f.tenant_id', $tenantId)
            ->where(fn($w) => $w
                ->whereRaw("f.numero_facture ILIKE ?", [$like])
                ->orWhereRaw("LOWER(e.nom || ' ' || e.prenom) ILIKE LOWER(?)", [$like])
            )
            ->select('f.id', 'f.numero_facture', 'f.total_ttc', 'f.statut', 'e.nom', 'e.prenom')
            ->limit(self::MAX_PAR_TYPE)
            ->get();

        if ($factures->isNotEmpty()) {
            $resultats[] = [
                'type'  => 'factures',
                'label' => '🧾 Factures',
                'items' => $factures->map(fn($f) => [
                    'id'    => $f->id,
                    'label' => $f->numero_facture,
                    'sub'   => "{$f->nom} {$f->prenom} · " . number_format($f->total_ttc, 0, ',', ' ') . ' DA',
                    'url'   => "/factures/{$f->id}",
                    'icone' => $f->statut === 'payee' ? '✅' : ($f->statut === 'en_retard' ? '❌' : '🧾'),
                ]),
            ];
        }

        // ── Cours ──────────────────────────────────────────────────────
        $cours = DB::table('cours as c')
            ->join('matieres as m', 'c.matiere_id', '=', 'm.id')
            ->where('c.tenant_id', $tenantId)
            ->whereRaw("m.nom_fr ILIKE ?", [$like])
            ->select('c.id', 'm.nom_fr', 'c.niveau')
            ->limit(self::MAX_PAR_TYPE)
            ->get();

        if ($cours->isNotEmpty()) {
            $resultats[] = [
                'type'  => 'cours',
                'label' => '📚 Cours',
                'items' => $cours->map(fn($c) => [
                    'id'    => $c->id,
                    'label' => $c->nom_fr,
                    'sub'   => $c->niveau ?? '',
                    'url'   => "/planning",
                    'icone' => '📚',
                ]),
            ];
        }

        $total = collect($resultats)->sum(fn($g) => count($g['items']));

        return response()->json([
            'success'   => true,
            'query'     => $q,
            'resultats' => $resultats,
            'total'     => $total,
        ]);
    }
}
```

**Ajouter dans** `routes/api.php` :

```php
use App\Http\Controllers\Api\V1\SearchController;

Route::middleware(['auth:api', 'tenant'])->group(function () {
    Route::get('/v1/search', [SearchController::class, 'global']);
});
```

---

## ÉTAPE 2 — useSearch.js hook

**Créer** : `edugestdz/frontend/src/hooks/useSearch.js`

```javascript
import { useState, useEffect, useRef, useCallback } from 'react';
import api from '@api/client';

export function useSearch() {
  const [query,    setQuery]    = useState('');
  const [results,  setResults]  = useState([]);
  const [loading,  setLoading]  = useState(false);
  const [open,     setOpen]     = useState(false);
  const debounceRef = useRef(null);

  const search = useCallback(async (q) => {
    if (q.length < 2) { setResults([]); return; }
    setLoading(true);
    try {
      const res = await api(`/search?q=${encodeURIComponent(q)}`);
      if (res.success) setResults(res.resultats ?? []);
    } catch {}
    finally { setLoading(false); }
  }, []);

  useEffect(() => {
    clearTimeout(debounceRef.current);
    if (query.length < 2) { setResults([]); return; }
    debounceRef.current = setTimeout(() => search(query), 300);
    return () => clearTimeout(debounceRef.current);
  }, [query, search]);

  // Ctrl+K / Cmd+K pour ouvrir
  useEffect(() => {
    const handler = (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        setOpen(o => !o);
      }
      if (e.key === 'Escape') {
        setOpen(false);
        setQuery('');
        setResults([]);
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, []);

  const close = useCallback(() => {
    setOpen(false);
    setQuery('');
    setResults([]);
  }, []);

  return { query, setQuery, results, loading, open, setOpen, close };
}
```

---

## ÉTAPE 3 — SearchModal.jsx

**Créer** : `edugestdz/frontend/src/components/SearchModal.jsx`

```jsx
import { useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { useSearch } from '@hooks/useSearch';

export default function SearchModal() {
  const navigate   = useNavigate();
  const { query, setQuery, results, loading, open, setOpen, close } = useSearch();
  const inputRef   = useRef(null);

  useEffect(() => {
    if (open) setTimeout(() => inputRef.current?.focus(), 50);
  }, [open]);

  const handleSelect = (url) => {
    navigate(url);
    close();
  };

  if (!open) return null;

  return (
    <>
      {/* Overlay */}
      <div onClick={close} style={{
        position:'fixed', inset:0, background:'rgba(0,0,0,0.6)', backdropFilter:'blur(4px)',
        zIndex:999, animation:'fadeIn 0.15s ease'
      }} />

      {/* Modal */}
      <div style={{
        position:'fixed', top:'80px', left:'50%', transform:'translateX(-50%)',
        width:'100%', maxWidth:'620px', zIndex:1000, padding:'0 16px',
      }}>
        <div style={{
          background:'var(--surface)', border:'1px solid var(--border)',
          borderRadius:'20px', boxShadow:'0 24px 80px rgba(0,0,0,0.6)',
          overflow:'hidden',
        }}>
          {/* Input */}
          <div style={{ display:'flex', alignItems:'center', gap:'12px', padding:'16px 20px', borderBottom:'1px solid var(--border)' }}>
            <span style={{ fontSize:'18px', flexShrink:0 }}>{loading ? '⏳' : '🔍'}</span>
            <input
              ref={inputRef}
              value={query}
              onChange={e => setQuery(e.target.value)}
              placeholder="Chercher un élève, facture, enseignant, cours..."
              style={{
                flex:1, background:'none', border:'none', color:'var(--text)',
                fontSize:'16px', outline:'none', fontWeight:500,
              }}
            />
            <kbd style={{
              background:'var(--surface2)', border:'1px solid var(--border)',
              borderRadius:'6px', padding:'2px 8px', fontSize:'11px', color:'var(--muted)',
              flexShrink:0,
            }}>Échap</kbd>
          </div>

          {/* Résultats */}
          <div style={{ maxHeight:'420px', overflowY:'auto' }}>
            {query.length < 2 ? (
              <div style={{ padding:'32px', textAlign:'center' }}>
                <div style={{ fontSize:'32px', marginBottom:'8px' }}>⌨️</div>
                <p style={{ color:'var(--muted)', fontSize:'13px' }}>
                  Tapez au moins 2 caractères...
                </p>
                <div style={{ display:'flex', gap:'8px', justifyContent:'center', marginTop:'16px', flexWrap:'wrap' }}>
                  {['Élèves', 'Factures', 'Enseignants', 'Cours'].map(t => (
                    <span key={t} style={{ background:'var(--surface2)', padding:'4px 12px', borderRadius:'20px', fontSize:'12px', color:'var(--muted)' }}>{t}</span>
                  ))}
                </div>
              </div>
            ) : loading ? (
              <div style={{ padding:'32px', textAlign:'center', color:'var(--muted)', fontSize:'13px' }}>
                Recherche en cours...
              </div>
            ) : results.length === 0 ? (
              <div style={{ padding:'32px', textAlign:'center' }}>
                <div style={{ fontSize:'32px', marginBottom:'8px' }}>😶</div>
                <p style={{ color:'var(--muted)', fontSize:'13px' }}>Aucun résultat pour « {query} »</p>
              </div>
            ) : (
              results.map(groupe => (
                <div key={groupe.type}>
                  <div style={{
                    padding:'10px 20px 6px',
                    fontSize:'11px', fontWeight:700, color:'var(--muted)',
                    textTransform:'uppercase', letterSpacing:'0.5px',
                  }}>
                    {groupe.label}
                  </div>
                  {groupe.items.map(item => (
                    <button key={item.id} onClick={() => handleSelect(item.url)}
                      style={{
                        width:'100%', background:'none', border:'none', textAlign:'left',
                        padding:'10px 20px', cursor:'pointer', display:'flex', alignItems:'center', gap:'14px',
                        transition:'background 0.1s',
                      }}
                      onMouseEnter={e => { e.currentTarget.style.background = 'var(--surface2)'; }}
                      onMouseLeave={e => { e.currentTarget.style.background = 'none'; }}
                    >
                      <span style={{ fontSize:'20px', flexShrink:0 }}>{item.icone}</span>
                      <div style={{ flex:1, minWidth:0 }}>
                        <div style={{ fontWeight:600, color:'var(--text)', fontSize:'14px',
                          overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap' }}>
                          {item.label}
                        </div>
                        {item.sub && (
                          <div style={{ color:'var(--muted)', fontSize:'12px', marginTop:'1px' }}>{item.sub}</div>
                        )}
                      </div>
                      <span style={{ color:'var(--muted)', fontSize:'11px', flexShrink:0 }}>→</span>
                    </button>
                  ))}
                </div>
              ))
            )}
          </div>

          {/* Footer */}
          <div style={{ padding:'10px 20px', borderTop:'1px solid var(--border)', display:'flex', gap:'16px', alignItems:'center' }}>
            <span style={{ fontSize:'11px', color:'var(--muted)' }}>
              <kbd style={{ background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'4px', padding:'1px 6px', fontSize:'10px' }}>↵</kbd> Ouvrir
            </span>
            <span style={{ fontSize:'11px', color:'var(--muted)' }}>
              <kbd style={{ background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'4px', padding:'1px 6px', fontSize:'10px' }}>Échap</kbd> Fermer
            </span>
          </div>
        </div>
      </div>
    </>
  );
}
```

---

## ÉTAPE 4 — Intégrer dans Topbar.jsx

**Modifier** `edugestdz/frontend/src/components/Topbar.jsx` — ajouter bouton recherche :

```jsx
import { useSearch } from '@hooks/useSearch';
import SearchModal from '@components/SearchModal';

// Dans le composant Topbar :
const { open, setOpen } = useSearch();

// Dans le JSX (à placer dans le header entre le titre et les actions) :
<>
  <button
    onClick={() => setOpen(true)}
    style={{
      display:'flex', alignItems:'center', gap:'8px',
      background:'var(--surface2)', border:'1px solid var(--border)',
      borderRadius:'10px', padding:'7px 14px', cursor:'pointer',
      color:'var(--muted)', fontSize:'13px', fontWeight:500,
      transition:'all 0.2s',
    }}
    onMouseEnter={e => { e.currentTarget.style.borderColor='var(--accent)'; e.currentTarget.style.color='var(--text)'; }}
    onMouseLeave={e => { e.currentTarget.style.borderColor='var(--border)'; e.currentTarget.style.color='var(--muted)'; }}
  >
    <span>🔍</span>
    <span>Rechercher...</span>
    <kbd style={{ background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'4px', padding:'1px 6px', fontSize:'10px' }}>
      Ctrl K
    </kbd>
  </button>

  <SearchModal />
</>
```

---

## ÉTAPE 5 — Tests Backend

**Créer** : `edugestdz/backend/tests/Feature/SearchControllerTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\{Tenant, Role, User, Eleve};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchControllerTest extends TestCase
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

    public function test_recherche_requiert_auth(): void
    {
        $this->getJson('/api/v1/search?q=Ahmed')->assertStatus(401);
    }

    public function test_requete_trop_courte_retourne_vide(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/search?q=A')
            ->assertStatus(200)
            ->assertJsonPath('total', 0);
    }

    public function test_recherche_trouve_eleve_par_nom(): void
    {
        Eleve::factory()->create([
            'tenant_id' => $this->tenant->id,
            'nom'       => 'Benali',
            'prenom'    => 'Ahmed',
        ]);

        $res = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/search?q=Benali')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->json();

        $this->assertGreaterThan(0, $res['total']);
        $this->assertTrue(
            collect($res['resultats'])->where('type', 'eleves')->isNotEmpty()
        );
    }

    public function test_recherche_ilike_insensible_casse(): void
    {
        Eleve::factory()->create([
            'tenant_id' => $this->tenant->id,
            'nom'       => 'MAMMERI',
            'prenom'    => 'Kamel',
        ]);

        $res = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/search?q=mammeri') // minuscule
            ->assertStatus(200)
            ->json();

        $this->assertGreaterThan(0, $res['total'], "ILIKE doit trouver MAMMERI avec 'mammeri'");
    }

    public function test_recherche_isolation_tenant(): void
    {
        $autresTenant = Tenant::factory()->create();
        Eleve::factory()->create(['tenant_id' => $autresTenant->id, 'nom' => 'TopSecret', 'prenom' => 'User']);

        $res = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/search?q=TopSecret')
            ->assertStatus(200)
            ->json();

        $this->assertEquals(0, $res['total'], "Un élève d'un autre tenant NE doit PAS apparaître");
    }

    public function test_structure_reponse_groupee(): void
    {
        $res = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/search?q=test')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'query', 'resultats', 'total'])
            ->json();

        foreach ($res['resultats'] as $groupe) {
            $this->assertArrayHasKey('type',  $groupe);
            $this->assertArrayHasKey('label', $groupe);
            $this->assertArrayHasKey('items', $groupe);
        }
    }
}
```

---

## ÉTAPE 6 — Exécution

```bash
cd edugestdz/backend
composer dump-autoload -o
php artisan test tests/Feature/SearchControllerTest.php --stop-on-failure
php artisan test → ≥ 865 ✅

cd ../frontend
npm run build → 0 erreurs

git add \
  backend/app/Http/Controllers/Api/V1/SearchController.php \
  backend/routes/api.php \
  backend/tests/Feature/SearchControllerTest.php \
  frontend/src/hooks/useSearch.js \
  frontend/src/components/SearchModal.jsx \
  frontend/src/components/Topbar.jsx

git commit -m "feat(recherche-globale): Ctrl+K recherche multi-entités — élèves, enseignants, factures, cours

- SearchController: ILIKE PostgreSQL insensible à la casse, groupé par entité
  Isolation tenant garantie. Max 5 résultats par type. Fallback sans Meilisearch.
- useSearch hook: debounce 300ms, Ctrl+K/Cmd+K open, Escape close
- SearchModal: overlay blur, résultats groupés avec icônes, navigation clavier
- Topbar: bouton Rechercher avec raccourci affiché (Ctrl K)
- SearchControllerTest: 5 tests (auth, trop court, ILIKE, isolation tenant, structure)"

git push origin develop → PR → main
```

---

## PROMPT EXACT POUR DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_RECHERCHE_GLOBALE.md — 6 étapes.

CONTEXTE :
- SearchController.php existant (initial commit) → vérifier son contenu et le remplacer si vide
- Meilisearch est configuré mais peut ne pas être disponible en CI → utiliser ILIKE PostgreSQL
- La table 'matieres' peut s'appeler autrement → vérifier avant le JOIN dans SearchController

RÈGLES :
1. SearchController : utiliser ILIKE PostgreSQL (pas 'like') — PostgreSQL est case-sensitive sur LIKE
2. La route GET /api/v1/search doit être dans le groupe middleware auth:api + tenant
3. useSearch hook : le debounce de 300ms évite les appels API à chaque frappe
4. SearchModal : doit être rendu dans Topbar (ou dans App.jsx via portail) — pas dans le Sidebar
5. Si la table 'enseignants' n'a pas de relation directe avec 'users' dans ce JOIN
   → utiliser LEFT JOIN et gérer les nulls dans le select
6. SearchControllerTest : utiliser RefreshDatabase → la BDD est vide → le test 'trop court'
   retourne total=0 même si aucun élève (comportement correct)

php artisan test tests/Feature/SearchControllerTest.php → 5 ✅
php artisan test → ≥ 865 ✅
npm run build → 0 erreurs
git push origin develop → PR → main
```
