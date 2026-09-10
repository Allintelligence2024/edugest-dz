import React from 'react';
import { useNavigate } from 'react-router-dom';
import { useModules } from '@context/ModulesContext';

export default function ModuleProtectedRoute({ moduleKey, children }) {
  const { isActive, loading } = useModules();
  const navigate = useNavigate();

  if (loading) {
    return (
      <div style={{ minHeight:'50vh', display:'flex', alignItems:'center', justifyContent:'center' }}>
        <div className="w-8 h-8 border-3 border-border rounded-full animate-spin" style={{ borderTopColor:'#2563EB' }} />
      </div>
    );
  }

  if (!isActive(moduleKey)) {
    return (
      <div style={{ minHeight:'50vh', display:'flex', alignItems:'center', justifyContent:'center', padding:'20px' }}>
        <div style={{ textAlign:'center', maxWidth:'400px' }}>
          <div style={{ fontSize:'48px', marginBottom:'12px' }}>🔒</div>
          <h2 style={{ fontSize:'18px', fontWeight:800, color:'var(--text)', marginBottom:'8px' }}>
            Module indisponible
          </h2>
          <p style={{ color:'var(--muted)', fontSize:'13px', marginBottom:'20px', lineHeight:'1.6' }}>
            Le module <strong>{moduleKey}</strong> n'est pas activé sur votre établissement.
            Contactez votre administrateur pour l'activer.
          </p>
          <button
            onClick={() => navigate(-1)}
            style={{
              background:'var(--accent)', color:'white', border:'none',
              borderRadius:'10px', padding:'10px 24px', fontSize:'13px',
              fontWeight:700, cursor:'pointer',
            }}
          >
            ← Retour
          </button>
        </div>
      </div>
    );
  }

  return children;
}
