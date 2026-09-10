import React from 'react';
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
