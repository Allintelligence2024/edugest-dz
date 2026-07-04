import { useState } from 'react';
import { authApi } from '../services/api';

export default function LoginPage() {
  const [email, setEmail]       = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading]   = useState(false);
  const [error, setError]       = useState('');

  const handleLogin = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      const res = await authApi.login(email, password);

      if (res?.success && res?.data?.token) {
        localStorage.setItem('token',    res.data.token);
        localStorage.setItem('tenantId', res.data.user?.tenant_id ?? '');
        localStorage.setItem('user',     JSON.stringify(res.data.user));
        localStorage.setItem('role',     res.data.user?.role ?? '');

        const role = res.data.user?.role;
        if (role === 'super_admin') window.location.href = '/super-admin';
        else                        window.location.href = '/dashboard';
      } else {
        setError(res?.message ?? 'Email ou mot de passe incorrect.');
      }
    } catch (e) {
      setError('Erreur réseau. Vérifiez votre connexion.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{
      minHeight: '100vh', background: '#08090f',
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      padding: '20px',
    }}>
      <div style={{
        background: '#111318', border: '1px solid #1e293b',
        borderRadius: '16px', padding: '40px', width: '100%', maxWidth: '420px',
      }}>
        <div style={{ textAlign: 'center', marginBottom: '32px' }}>
          <div style={{ fontSize: '40px', marginBottom: '8px' }}>🎓</div>
          <h1 style={{ fontSize: '24px', fontWeight: 900, color: '#fff', marginBottom: '4px' }}>
            EduGest DZ
          </h1>
          <p style={{ fontSize: '12px', color: '#64748b' }}>
            Plateforme de gestion scolaire
          </p>
        </div>

        {error && (
          <div style={{
            background: '#450a0a', border: '1px solid #b91c1c',
            borderRadius: '8px', padding: '12px', marginBottom: '16px',
            color: '#f87171', fontSize: '12px',
          }}>
            ❌ {error}
          </div>
        )}

        <form onSubmit={handleLogin}>
          <div style={{ marginBottom: '14px' }}>
            <label style={{ fontSize: '11px', color: '#64748b', display: 'block', marginBottom: '6px' }}>
              Adresse email
            </label>
            <input
              type="email"
              value={email}
              onChange={e => setEmail(e.target.value)}
              placeholder="directeur@ecole.dz"
              required
              style={{
                width: '100%', background: '#1e293b', border: '1px solid #334155',
                borderRadius: '8px', color: '#e2e8f0', padding: '12px 14px', fontSize: '13px',
              }}
            />
          </div>

          <div style={{ marginBottom: '20px' }}>
            <label style={{ fontSize: '11px', color: '#64748b', display: 'block', marginBottom: '6px' }}>
              Mot de passe
            </label>
            <input
              type="password"
              value={password}
              onChange={e => setPassword(e.target.value)}
              placeholder="••••••••"
              required
              style={{
                width: '100%', background: '#1e293b', border: '1px solid #334155',
                borderRadius: '8px', color: '#e2e8f0', padding: '12px 14px', fontSize: '13px',
              }}
            />
          </div>

          <button
            type="submit"
            disabled={loading}
            style={{
              width: '100%',
              background: loading ? '#1e293b' : 'linear-gradient(135deg,#3b82f6,#1d4ed8)',
              color: '#fff', border: 'none', borderRadius: '8px',
              padding: '13px', fontSize: '14px', fontWeight: 700,
              cursor: loading ? 'not-allowed' : 'pointer',
              transition: 'opacity .2s',
            }}
          >
            {loading ? '⏳ Connexion...' : '🔐 Se connecter'}
          </button>
        </form>

        <p style={{ textAlign: 'center', marginTop: '24px', fontSize: '11px', color: '#475569' }}>
          Problème de connexion ? Contactez l'administrateur de votre établissement.
        </p>

        <div style={{ marginTop: '24px', borderTop: '1px solid #1e293b', paddingTop: '16px', textAlign: 'center' }}>
          <p style={{ fontSize: '10px', color: '#334155' }}>
            Vous êtes un centre ? Rejoignez la Marketplace →{' '}
            <a href="/marketplace" style={{ color: '#60a5fa', textDecoration: 'none' }}>
              Trouver un cours
            </a>
          </p>
        </div>
      </div>
    </div>
  );
}
