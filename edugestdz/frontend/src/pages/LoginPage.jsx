import React, { useState } from 'react';
import { useNavigate, Navigate, Link } from 'react-router-dom';
import { useAuth } from '@context/AuthContext';
import { api } from '@api/client';

export default function LoginPage() {
  const { login, isAuthenticated, homeRoute, sessionExpired } = useAuth();
  const navigate = useNavigate();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [showPass, setShowPass] = useState(false);

  if (isAuthenticated) return <Navigate to={homeRoute()} replace />;

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(''); setLoading(true);
    try {
      const user = await login(email, password);

      let dest = '/';
      if (user?.role === 'admin') {
        const onboardingRes = await api('/onboarding').catch(() => null);
        dest = onboardingRes?.complete === false && onboardingRes?.etape < 5
          ? '/onboarding'
          : '/';
      } else if (user?.role === 'eleve') {
        dest = '/devoirs';
      } else if (user?.role === 'enseignant') {
        dest = '/planning';
      }
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

        <div style={{ textAlign:'center', marginBottom:'32px' }}>
          <div style={{ fontSize:'40px', marginBottom:'8px' }}>🎓</div>
          <h1 style={{ fontSize:'26px', fontWeight:900, color:'var(--text)', letterSpacing:'-0.5px' }}>
            EduGest <span style={{ color:'var(--accent)' }}>DZ</span>
          </h1>
          <p style={{ color:'var(--muted)', fontSize:'13px', marginTop:'6px' }}>
            Plateforme de gestion scolaire
          </p>
        </div>

        {sessionExpired && (
          <div style={{
            background:'rgba(234,179,8,0.1)', border:'1px solid rgba(234,179,8,0.3)',
            borderRadius:'10px', padding:'12px 14px', marginBottom:'16px',
            color:'#ca8a04', fontSize:'13px', fontWeight:600,
          }}>
            ⏱️ Votre session a expiré. Veuillez vous reconnecter.
          </div>
        )}

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
                <Link to="/mot-de-passe-oublie" style={{ fontSize:'12px', color:'var(--accent)', textDecoration:'none' }}>
                  Mot de passe oublié ?
                </Link>
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
          <Link to="/" style={{ color:'var(--accent)', textDecoration:'none' }}>Confidentialité</Link>
        </p>
      </div>
    </div>
  );
}
