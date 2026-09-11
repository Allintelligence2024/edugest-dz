import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '@context/AuthContext';

import { GraduationCap } from 'lucide-react';

export default function ProtectedRoute({ children, roles = null }) {
  const { isAuthenticated, isLoading, role } = useAuth();
  const location = useLocation();

  if (isLoading) {
    return (
      <div style={{ minHeight:'100vh', background:'var(--bg)', display:'flex', alignItems:'center', justifyContent:'center' }}>
        <div style={{ textAlign:'center' }}>
          <div style={{ fontSize:'32px', marginBottom:'12px' }}><GraduationCap size={32} aria-hidden='true' /></div>
          <p style={{ color:'var(--muted)', fontSize:'13px' }}>Chargement...</p>
        </div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  if (roles && !roles.includes(role)) {
    return <Navigate to="/acces-refuse" replace />;
  }

  return children;
}
