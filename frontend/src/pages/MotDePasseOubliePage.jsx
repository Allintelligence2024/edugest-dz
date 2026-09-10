import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import api from '@api/client';

export default function MotDePasseOubliePage() {
  const [email, setEmail] = useState('');
  const [sent, setSent] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true); setError('');
    try {
      await api('/auth/forgot-password', {
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
