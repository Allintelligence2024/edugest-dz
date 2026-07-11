# 🔐 MISSION 1 — Auth Flow Complet (Login, Refresh, Redirect, Reset)
## EduGest DZ · Branche : develop · Tests : 859 ✅ · Objectif : ≥ 880 ✅
## PRIORITÉ : CRITIQUE — Sans ça, aucune démo n'est possible

---

## DIAGNOSTIC LU DANS LE REPO

```
CE QUI EXISTE :
✅ AuthController.php → login(), logout(), me() (JWT tymon/jwt-auth)
✅ JWT_TTL=60 (60 minutes) dans .env.example
✅ JwtBlacklistService.php → logout propre
✅ MFA (2FA) via TwoFactorController.php
✅ UserFactory avec adminAvec2fa() state

CE QUI MANQUE :
❌ LoginPage.jsx → pas de page dédiée (le frontend redirige directement vers /dashboard)
❌ Refresh token silencieux → token expiré = écran blanc sans message
❌ Redirect post-login selon le rôle (admin→/dashboard, élève→/devoirs, parent→/)
❌ "Mot de passe oublié" → PasswordResetController existe mais pas de page frontend
❌ Intercepteur Axios/fetch global → gère les 401 partout dans l'app
❌ AuthContext React → isAuthenticated, user, role, logout accessible partout
❌ ProtectedRoute → redirige vers /login si pas de token valide
❌ Message "Session expirée" au lieu d'un écran blanc
```

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## ÉTAPE 1 — AuthContext.jsx (state global d'authentification)

**Créer** : `edugestdz/frontend/src/context/AuthContext.jsx`

```jsx
import { createContext, useContext, useState, useEffect, useCallback } from 'react';

const BASE_URL = import.meta.env.VITE_API_URL ?? '';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user,          setUser]          = useState(null);
  const [isLoading,     setIsLoading]     = useState(true);
  const [sessionExpired, setSessionExpired] = useState(false);

  // Charger l'utilisateur depuis le token stocké au démarrage
  useEffect(() => {
    const token = localStorage.getItem('token');
    if (!token) { setIsLoading(false); return; }

    fetch(`${BASE_URL}/api/v1/auth/me`, {
      headers: { Authorization: `Bearer ${token}` }
    })
    .then(r => r.ok ? r.json() : Promise.reject(r.status))
    .then(data => { if (data.user) setUser(data.user); })
    .catch((status) => {
      if (status === 401) localStorage.removeItem('token');
    })
    .finally(() => setIsLoading(false));
  }, []);

  const login = useCallback(async (email, password) => {
    const res = await fetch(`${BASE_URL}/api/v1/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message ?? 'Identifiants incorrects');

    localStorage.setItem('token', data.token ?? data.access_token);
    setUser(data.user);
    setSessionExpired(false);
    return data.user;
  }, []);

  const logout = useCallback(async () => {
    const token = localStorage.getItem('token');
    if (token) {
      fetch(`${BASE_URL}/api/v1/auth/logout`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}` }
      }).catch(() => {});
    }
    localStorage.removeItem('token');
    setUser(null);
  }, []);

  const onSessionExpired = useCallback(() => {
    localStorage.removeItem('token');
    setUser(null);
    setSessionExpired(true);
  }, []);

  const isAuthenticated = !!user;
  const role = user?.role?.nom ?? user?.role ?? null;

  // Redirect destination selon le rôle
  const homeRoute = () => {
    switch (role) {
      case 'admin':       return '/dashboard';
      case 'enseignant':  return '/planning';
      case 'eleve':       return '/devoirs';
      case 'parent':      return '/dashboard';
      default:            return '/dashboard';
    }
  };

  return (
    <AuthContext.Provider value={{
      user, isLoading, isAuthenticated, role, sessionExpired,
      login, logout, onSessionExpired, homeRoute,
    }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth doit être utilisé dans <AuthProvider>');
  return ctx;
};
```

---

## ÉTAPE 2 — api/client.js : intercepteur 401 global

**Modifier** : `edugestdz/frontend/src/api/client.js`

Remplacer le contenu ENTIER par :

```javascript
const BASE_URL = import.meta.env.VITE_API_URL ?? '';

let _onSessionExpired = null;

export function setSessionExpiredHandler(fn) {
  _onSessionExpired = fn;
}

export function getToken() {
  return localStorage.getItem('token') ?? '';
}

export async function api(path, options = {}) {
  const url = `${BASE_URL}/api/v1${path}`;

  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    ...options.headers,
  };

  const token = getToken();
  if (token) headers['Authorization'] = `Bearer ${token}`;

  const response = await fetch(url, { ...options, headers });

  // 401 → session expirée → notifier AuthContext
  if (response.status === 401) {
    localStorage.removeItem('token');
    if (_onSessionExpired) _onSessionExpired();
    throw new Error('SESSION_EXPIRED');
  }

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    const msg = data?.message ?? data?.error?.message ?? `HTTP ${response.status}`;
    throw new Error(msg);
  }

  return data;
}

export function getApiUrl(path) {
  return `${BASE_URL}/api/v1${path}`;
}

export default api;
```

---

## ÉTAPE 3 — LoginPage.jsx

**Créer** : `edugestdz/frontend/src/pages/LoginPage.jsx`

```jsx
import { useState } from 'react';
import { useNavigate, Navigate } from 'react-router-dom';
import { useAuth } from '@context/AuthContext';

export default function LoginPage() {
  const { login, isAuthenticated, homeRoute, sessionExpired } = useAuth();
  const navigate = useNavigate();

  const [email,    setEmail]    = useState('');
  const [password, setPassword] = useState('');
  const [error,    setError]    = useState('');
  const [loading,  setLoading]  = useState(false);
  const [showPass, setShowPass] = useState(false);

  // Déjà connecté → rediriger
  if (isAuthenticated) return <Navigate to={homeRoute()} replace />;

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(''); setLoading(true);
    try {
      const user = await login(email, password);
      // Redirect selon rôle
      const dest = user?.role?.nom === 'eleve' ? '/devoirs'
                 : user?.role?.nom === 'enseignant' ? '/planning'
                 : '/dashboard';
      navigate(dest, { replace: true });
    } catch (err) {
      setError(err.message ?? 'Identifiants incorrects. Réessayez.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{ minHeight:'100vh', background:'var(--bg)', display:'flex', alignItems:'center', justifyContent:'center', padding:'20px' }}>
      <div style={{ width:'100%', maxWidth:'400px' }}>

        {/* Logo */}
        <div style={{ textAlign:'center', marginBottom:'32px' }}>
          <div style={{ fontSize:'40px', marginBottom:'8px' }}>🎓</div>
          <h1 style={{ fontSize:'26px', fontWeight:900, color:'var(--text)', letterSpacing:'-0.5px' }}>
            EduGest <span style={{ color:'var(--accent)' }}>DZ</span>
          </h1>
          <p style={{ color:'var(--muted)', fontSize:'13px', marginTop:'6px' }}>
            Plateforme de gestion scolaire
          </p>
        </div>

        {/* Session expirée */}
        {sessionExpired && (
          <div style={{
            background:'rgba(234,179,8,0.1)', border:'1px solid rgba(234,179,8,0.3)',
            borderRadius:'10px', padding:'12px 14px', marginBottom:'16px',
            color:'#ca8a04', fontSize:'13px', fontWeight:600,
          }}>
            ⏱️ Votre session a expiré. Veuillez vous reconnecter.
          </div>
        )}

        {/* Formulaire */}
        <div style={{
          background:'var(--surface)', border:'1px solid var(--border)',
          borderRadius:'20px', padding:'32px', boxShadow:'0 20px 60px rgba(0,0,0,0.4)'
        }}>
          <h2 style={{ fontSize:'18px', fontWeight:800, color:'var(--text)', marginBottom:'24px' }}>
            Connexion
          </h2>

          {error && (
            <div style={{
              background:'rgba(239,68,68,0.1)', border:'1px solid rgba(239,68,68,0.3)',
              borderRadius:'10px', padding:'12px 14px', marginBottom:'16px',
              color:'#f87171', fontSize:'13px'
            }}>
              ❌ {error}
            </div>
          )}

          <form onSubmit={handleSubmit}>
            <div style={{ marginBottom:'16px' }}>
              <label style={{ display:'block', fontSize:'12px', fontWeight:700, color:'var(--text)', marginBottom:'6px' }}>
                Email ou identifiant
              </label>
              <input
                type="email"
                required
                autoFocus
                value={email}
                onChange={e => setEmail(e.target.value)}
                placeholder="directeur@mon-ecole.dz"
                style={{
                  width:'100%', background:'var(--surface2)', border:'1px solid var(--border)',
                  borderRadius:'10px', padding:'10px 14px', color:'var(--text)', fontSize:'14px',
                  outline:'none', boxSizing:'border-box',
                }}
              />
            </div>

            <div style={{ marginBottom:'20px' }}>
              <label style={{ display:'block', fontSize:'12px', fontWeight:700, color:'var(--text)', marginBottom:'6px' }}>
                Mot de passe
              </label>
              <div style={{ position:'relative' }}>
                <input
                  type={showPass ? 'text' : 'password'}
                  required
                  value={password}
                  onChange={e => setPassword(e.target.value)}
                  placeholder="••••••••••••"
                  style={{
                    width:'100%', background:'var(--surface2)', border:'1px solid var(--border)',
                    borderRadius:'10px', padding:'10px 40px 10px 14px', color:'var(--text)', fontSize:'14px',
                    outline:'none', boxSizing:'border-box',
                  }}
                />
                <button type="button" onClick={() => setShowPass(s => !s)}
                  style={{ position:'absolute', right:'12px', top:'50%', transform:'translateY(-50%)',
                    background:'none', border:'none', cursor:'pointer', color:'var(--muted)', fontSize:'16px' }}>
                  {showPass ? '🙈' : '👁️'}
                </button>
              </div>
              <div style={{ textAlign:'right', marginTop:'6px' }}>
                <a href="/mot-de-passe-oublie" style={{ fontSize:'12px', color:'var(--accent)', textDecoration:'none' }}>
                  Mot de passe oublié ?
                </a>
              </div>
            </div>

            <button
              type="submit"
              disabled={loading}
              style={{
                width:'100%', background: loading ? 'var(--surface2)' : 'var(--accent)',
                color:'white', border:'none', borderRadius:'10px', padding:'12px',
                fontSize:'14px', fontWeight:700, cursor: loading ? 'not-allowed' : 'pointer',
                transition:'all 0.2s',
              }}
            >
              {loading ? '⏳ Connexion...' : '🔓 Se connecter'}
            </button>
          </form>

          <p style={{ fontSize:'11px', color:'var(--muted)', textAlign:'center', marginTop:'20px', lineHeight:'1.6' }}>
            Problème de connexion ? Contactez votre administrateur.
          </p>
        </div>

        <p style={{ textAlign:'center', color:'var(--muted)', fontSize:'11px', marginTop:'20px' }}>
          EduGest DZ · Made in Oran 🇩🇿 · 
          <a href="/privacy" style={{ color:'var(--accent)', textDecoration:'none' }}>Confidentialité</a>
        </p>
      </div>
    </div>
  );
}
```

---

## ÉTAPE 4 — ProtectedRoute.jsx

**Créer** : `edugestdz/frontend/src/components/ProtectedRoute.jsx`

```jsx
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '@context/AuthContext';

export default function ProtectedRoute({ children, roles = null }) {
  const { isAuthenticated, isLoading, role } = useAuth();
  const location = useLocation();

  if (isLoading) {
    return (
      <div style={{ minHeight:'100vh', background:'var(--bg)', display:'flex', alignItems:'center', justifyContent:'center' }}>
        <div style={{ textAlign:'center' }}>
          <div style={{ fontSize:'32px', marginBottom:'12px' }}>🎓</div>
          <p style={{ color:'var(--muted)', fontSize:'13px' }}>Chargement...</p>
        </div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  if (roles && !roles.includes(role)) {
    return <Navigate to="/accès-refusé" replace />;
  }

  return children;
}
```

---

## ÉTAPE 5 — Page Mot de Passe Oublié

**Créer** : `edugestdz/frontend/src/pages/MotDePasseOubliePage.jsx`

```jsx
import { useState } from 'react';
import { Link } from 'react-router-dom';
import api from '@api/client';

export default function MotDePasseOubliePage() {
  const [email,    setEmail]    = useState('');
  const [sent,     setSent]     = useState(false);
  const [error,    setError]    = useState('');
  const [loading,  setLoading]  = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true); setError('');
    try {
      await api('/auth/password/forgot', {
        method: 'POST',
        body: JSON.stringify({ email }),
      });
      setSent(true);
    } catch (err) {
      setError(err.message ?? 'Erreur lors de l\'envoi');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{ minHeight:'100vh', background:'var(--bg)', display:'flex', alignItems:'center', justifyContent:'center', padding:'20px' }}>
      <div style={{ width:'100%', maxWidth:'400px' }}>
        <div style={{ textAlign:'center', marginBottom:'32px' }}>
          <div style={{ fontSize:'40px' }}>🔑</div>
          <h1 style={{ fontSize:'22px', fontWeight:800, color:'var(--text)', marginTop:'8px' }}>Mot de passe oublié</h1>
        </div>

        <div style={{ background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'20px', padding:'32px' }}>
          {sent ? (
            <div style={{ textAlign:'center' }}>
              <div style={{ fontSize:'48px', marginBottom:'16px' }}>📧</div>
              <h3 style={{ color:'var(--green)', fontWeight:700, marginBottom:'8px' }}>Email envoyé !</h3>
              <p style={{ color:'var(--muted)', fontSize:'13px', lineHeight:'1.6' }}>
                Si un compte existe avec cet email, vous recevrez un lien de réinitialisation dans les prochaines minutes.
              </p>
              <Link to="/login" style={{ display:'inline-block', marginTop:'20px', color:'var(--accent)', fontSize:'13px', fontWeight:600 }}>
                ← Retour à la connexion
              </Link>
            </div>
          ) : (
            <form onSubmit={handleSubmit}>
              <p style={{ color:'var(--muted)', fontSize:'13px', marginBottom:'20px', lineHeight:'1.6' }}>
                Entrez votre adresse email. Nous vous enverrons un lien pour réinitialiser votre mot de passe.
              </p>
              {error && (
                <div style={{ background:'rgba(239,68,68,0.1)', border:'1px solid rgba(239,68,68,0.3)', borderRadius:'10px', padding:'10px 14px', marginBottom:'16px', color:'#f87171', fontSize:'13px' }}>
                  ❌ {error}
                </div>
              )}
              <input
                type="email" required autoFocus
                value={email} onChange={e => setEmail(e.target.value)}
                placeholder="votre@email.dz"
                style={{ width:'100%', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'10px', padding:'10px 14px', color:'var(--text)', fontSize:'14px', outline:'none', boxSizing:'border-box', marginBottom:'16px' }}
              />
              <button type="submit" disabled={loading}
                style={{ width:'100%', background:'var(--accent)', color:'white', border:'none', borderRadius:'10px', padding:'12px', fontSize:'14px', fontWeight:700, cursor:'pointer' }}>
                {loading ? '⏳ Envoi...' : '📧 Envoyer le lien'}
              </button>
              <div style={{ textAlign:'center', marginTop:'16px' }}>
                <Link to="/login" style={{ fontSize:'12px', color:'var(--muted)', textDecoration:'none' }}>← Retour à la connexion</Link>
              </div>
            </form>
          )}
        </div>
      </div>
    </div>
  );
}
```

---

## ÉTAPE 6 — Page Accès Refusé

**Créer** : `edugestdz/frontend/src/pages/AccesRefusePage.jsx`

```jsx
import { Link } from 'react-router-dom';
import { useAuth } from '@context/AuthContext';

export default function AccesRefusePage() {
  const { homeRoute } = useAuth();
  return (
    <div style={{ minHeight:'100vh', background:'var(--bg)', display:'flex', alignItems:'center', justifyContent:'center' }}>
      <div style={{ textAlign:'center', maxWidth:'400px', padding:'32px' }}>
        <div style={{ fontSize:'64px', marginBottom:'16px' }}>🚫</div>
        <h1 style={{ fontSize:'22px', fontWeight:800, color:'var(--text)', marginBottom:'8px' }}>Accès non autorisé</h1>
        <p style={{ color:'var(--muted)', fontSize:'13px', lineHeight:'1.6', marginBottom:'24px' }}>
          Vous n'avez pas les permissions nécessaires pour accéder à cette page.
        </p>
        <Link to={homeRoute()} style={{ background:'var(--accent)', color:'white', padding:'10px 24px', borderRadius:'10px', fontWeight:700, fontSize:'14px', textDecoration:'none', display:'inline-block' }}>
          🏠 Retour à l'accueil
        </Link>
      </div>
    </div>
  );
}
```

---

## ÉTAPE 7 — Mettre à jour App.jsx

**Modifier** : `edugestdz/frontend/src/App.jsx`

```jsx
// Ajouter ces imports
import { AuthProvider, useAuth } from '@context/AuthContext';
import { setSessionExpiredHandler } from '@api/client';
import ProtectedRoute from '@components/ProtectedRoute';
import LoginPage from '@pages/LoginPage';
import MotDePasseOubliePage from '@pages/MotDePasseOubliePage';
import AccesRefusePage from '@pages/AccesRefusePage';

// Dans le composant App, enregistrer le handler 401 :
function AppInner() {
  const { onSessionExpired } = useAuth();
  useEffect(() => {
    setSessionExpiredHandler(onSessionExpired);
  }, [onSessionExpired]);
  // ... reste du composant
}

// Wrapper principal avec AuthProvider
export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          {/* Routes publiques */}
          <Route path="/login"                element={<LoginPage />} />
          <Route path="/mot-de-passe-oublie"  element={<MotDePasseOubliePage />} />
          <Route path="/acces-refuse"         element={<AccesRefusePage />} />

          {/* Routes protégées */}
          <Route path="/*" element={
            <ProtectedRoute>
              <AppLayout />  {/* Votre layout existant avec Sidebar + Topbar */}
            </ProtectedRoute>
          } />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}
```

---

## ÉTAPE 8 — Backend : endpoint forgot password (si absent)

**Modifier** : `edugestdz/backend/routes/api.php`

Vérifier que ces routes existent (ajouter si absent) :

```php
// Mot de passe oublié (public, pas de middleware auth)
Route::prefix('v1/auth')->group(function () {
    Route::post('/password/forgot', function (\Illuminate\Http\Request $request) {
        $request->validate(['email' => 'required|email']);
        \Illuminate\Support\Facades\Password::sendResetLink(
            $request->only('email')
        );
        // Toujours retourner 200 pour éviter l'énumération d'emails
        return response()->json(['success' => true, 'message' => 'Si ce compte existe, un email a été envoyé.']);
    });
});
```

---

## ÉTAPE 9 — Tests

**Créer** : `edugestdz/backend/tests/Feature/AuthFlowTest.php`

```php
<?php
namespace Tests\Feature;
use App\Models\{Tenant, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_retourne_token_jwt(): void
    {
        $tenant = Tenant::factory()->create(['statut' => 'actif']);
        $role   = Role::firstOrCreate(['nom' => 'admin']);
        $user   = User::factory()->create([
            'tenant_id' => $tenant->id, 'role_id' => $role->id,
            'password'  => bcrypt('MonMotDePasse@2026'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'MonMotDePasse@2026',
        ])->assertStatus(200)->assertJsonStructure(['token']);
    }

    public function test_login_mauvais_mot_de_passe_retourne_401(): void
    {
        $tenant = Tenant::factory()->create(['statut' => 'actif']);
        $user   = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('correct')]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'mauvais',
        ])->assertStatus(401);
    }

    public function test_me_avec_token_valide(): void
    {
        $tenant = Tenant::factory()->create(['statut' => 'actif']);
        $user   = User::factory()->create(['tenant_id' => $tenant->id]);
        $token  = auth('api')->login($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonStructure(['user']);
    }

    public function test_me_sans_token_retourne_401(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_logout_invalide_le_token(): void
    {
        $tenant = Tenant::factory()->create(['statut' => 'actif']);
        $user   = User::factory()->create(['tenant_id' => $tenant->id]);
        $token  = auth('api')->login($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200);

        // Après logout, le même token doit retourner 401
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_forgot_password_retourne_200_meme_si_email_inconnu(): void
    {
        $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'inconnu@test.com',
        ])->assertStatus(200)->assertJsonPath('success', true);
    }
}
```

---

## ÉTAPE 10 — Exécution

```bash
# Backend
cd edugestdz/backend
composer dump-autoload -o
php artisan test tests/Feature/AuthFlowTest.php --stop-on-failure
php artisan test → ≥ 880 ✅

# Frontend
cd ../frontend
npm run build → 0 erreurs

git add \
  frontend/src/context/AuthContext.jsx \
  frontend/src/api/client.js \
  frontend/src/pages/LoginPage.jsx \
  frontend/src/pages/MotDePasseOubliePage.jsx \
  frontend/src/pages/AccesRefusePage.jsx \
  frontend/src/components/ProtectedRoute.jsx \
  frontend/src/App.jsx \
  backend/tests/Feature/AuthFlowTest.php \
  backend/routes/api.php

git commit -m "feat(auth-flow): Login complet, redirect par rôle, session expirée, mot de passe oublié

- AuthContext: isAuthenticated, user, role, login(), logout(), homeRoute()
- LoginPage: formulaire dark, redirect par rôle post-login, showPassword toggle
- ProtectedRoute: garde toutes les routes → /login si non authentifié
- api/client.js: intercepteur 401 global → onSessionExpired() → bannière
- MotDePasseOubliePage: envoi email reset, anti-énumération (toujours 200)
- AccesRefusePage: rôle non autorisé → message clair + retour accueil
- AuthFlowTest: 6 tests (login, logout, me, 401, forgot password)"

git push origin develop
```

---

## PROMPT EXACT POUR DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop  
git checkout develop && git pull origin main

Fichier : MISSION_AUTH_FLOW.md — 10 étapes.

CONTEXTE :
- AuthController.php existant : login(), logout(), me() via tymon/jwt-auth
- Pas de LoginPage.jsx, pas d'AuthContext, pas de ProtectedRoute
- api/client.js créé (Mission Fix Vigilances) — doit être mis à jour avec intercepteur 401

RÈGLES :
1. AuthContext doit envelopper App.jsx ENTIER (y compris BrowserRouter)
2. ProtectedRoute vérifie isLoading avant de rediriger (évite flash /login)
3. L'intercepteur 401 dans client.js utilise setSessionExpiredHandler() enregistré dans AppInner
4. homeRoute() dans AuthContext doit correspondre aux routes réelles dans App.jsx
5. Forgot password : si PasswordBroker non configuré → retourner JSON success=true sans crasher
6. LoginPage : les styles utilisent var(--bg), var(--surface), var(--accent) — variables CSS existantes

npm run build → 0 erreurs
php artisan test tests/Feature/AuthFlowTest.php → 6 ✅
php artisan test → ≥ 880 ✅
git push origin develop → PR → main
```
