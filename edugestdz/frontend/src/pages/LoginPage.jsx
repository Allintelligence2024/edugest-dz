import { useState } from 'react';
import { authApi } from '../services/api';
import { useI18n } from '@context/I18nContext';
import { useTheme } from '@context/ThemeContext';
import LanguageThemeSelector from '@components/LanguageThemeSelector';

export default function LoginPage() {
  const { t } = useI18n();
  const { isDark } = useTheme();
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
        setError(res?.message ?? t('login_error'));
      }
    } catch (e) {
      if (e.message && e.message.includes('serveur')) {
        setError(e.message);
      } else if (!navigator.onLine) {
        setError('Pas de connexion internet. Vérifiez votre réseau.');
      } else {
        setError(
          'Le serveur est temporairement indisponible. ' +
          'Réessayez dans quelques instants.'
        );
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{
      minHeight: '100vh', background: 'var(--eg-bg)',
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      padding: '20px', position: 'relative',
    }}>
      <div style={{ position: 'absolute', top: '20px', right: '20px' }}>
        <LanguageThemeSelector compact />
      </div>

      <div style={{
        background: 'var(--eg-surface)', border: '1px solid var(--eg-border)',
        borderRadius: '16px', padding: '40px', width: '100%', maxWidth: '420px',
      }}>
        <div style={{ textAlign: 'center', marginBottom: '32px' }}>
          <div style={{ fontSize: '40px', marginBottom: '8px' }}>🎓</div>
          <h1 style={{ fontSize: '24px', fontWeight: 900, color: 'var(--eg-text)', marginBottom: '4px' }}>
            {t('app_name')}
          </h1>
          <p style={{ fontSize: '12px', color: 'var(--eg-muted)' }}>
            {t('login_subtitle')}
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
            <label style={{ fontSize: '11px', color: 'var(--eg-muted)', display: 'block', marginBottom: '6px' }}>
              {t('login_email')}
            </label>
            <input
              type="email"
              value={email}
              onChange={e => setEmail(e.target.value)}
              placeholder="directeur@ecole.dz"
              required
              style={{
                width: '100%', background: 'var(--eg-input-bg)', border: '1px solid var(--eg-border)',
                borderRadius: '8px', color: 'var(--eg-text)', padding: '12px 14px', fontSize: '13px',
              }}
            />
          </div>

          <div style={{ marginBottom: '20px' }}>
            <label style={{ fontSize: '11px', color: 'var(--eg-muted)', display: 'block', marginBottom: '6px' }}>
              {t('login_password')}
            </label>
            <input
              type="password"
              value={password}
              onChange={e => setPassword(e.target.value)}
              placeholder="••••••••"
              required
              style={{
                width: '100%', background: 'var(--eg-input-bg)', border: '1px solid var(--eg-border)',
                borderRadius: '8px', color: 'var(--eg-text)', padding: '12px 14px', fontSize: '13px',
              }}
            />
          </div>

          <button
            type="submit"
            disabled={loading}
            style={{
              width: '100%',
              background: loading ? 'var(--eg-surface2)' : 'linear-gradient(135deg,#3b82f6,#1d4ed8)',
              color: '#fff', border: 'none', borderRadius: '8px',
              padding: '13px', fontSize: '14px', fontWeight: 700,
              cursor: loading ? 'not-allowed' : 'pointer',
              transition: 'opacity .2s',
            }}
          >
            {loading ? `⏳ ${t('login_loading')}` : `🔐 ${t('login_submit')}`}
          </button>
        </form>

        <p style={{ textAlign: 'center', marginTop: '24px', fontSize: '11px', color: 'var(--eg-muted)' }}>
          Problème de connexion ? Contactez l'administrateur de votre établissement.
        </p>

        <div style={{ marginTop: '24px', borderTop: '1px solid var(--eg-border)', paddingTop: '16px', textAlign: 'center' }}>
          <p style={{ fontSize: '10px', color: 'var(--eg-border)' }}>
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
