import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import api from '@api/axiosInstance';
import { DEMO_MODE as GLOBAL_DEMO } from '@api/axiosInstance';

const DEMO_USER = {
  id: 1,
  nom: 'Khellil',
  prenom: 'Youcef',
  email: 'admin@edugestdz.local',
  role: 'admin',
};

const DEMO_TENANT = {
  id: 1,
  nom: 'Centre EduGest — Alger',
  ville: 'Alger',
  statut: 'actif',
};

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [tenant, setTenant] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [isDemoMode, setIsDemoMode] = useState(GLOBAL_DEMO);

  const activateDemo = useCallback(() => {
    setUser(DEMO_USER);
    setTenant(DEMO_TENANT);
    setIsAuthenticated(true);
    setIsDemoMode(true);
    setIsLoading(false);
  }, []);

  const loadUser = useCallback(async () => {
    if (GLOBAL_DEMO) {
      activateDemo();
      return;
    }

    const token = localStorage.getItem('access_token');
    if (!token) {
      setIsLoading(false);
      return;
    }

    try {
      const res = await api.get('/auth/me');
      const data = res.data || res;
      setUser(data.user || data);
      setTenant(res.tenant || null);
      setIsAuthenticated(true);
    } catch {
      console.warn('[EduGest] Backend inaccessible — activation mode démo');
      activateDemo();
    } finally {
      setIsLoading(false);
    }
  }, [activateDemo]);

  useEffect(() => { loadUser(); }, [loadUser]);

  const login = async (email, password) => {
    if (GLOBAL_DEMO) {
      activateDemo();
      return { user: DEMO_USER, tenant: DEMO_TENANT };
    }

    const res = await api.post('/auth/login', { email, password });
    const { access_token, refresh_token, user: u, tenant: t } = res;

    localStorage.setItem('access_token', access_token);
    if (refresh_token) localStorage.setItem('refresh_token', refresh_token);

    setUser(u);
    setTenant(t);
    setIsAuthenticated(true);
    return { user: u, tenant: t };
  };

  const logout = async () => {
    if (!isDemoMode) {
      try { await api.post('/auth/logout'); } catch { /* ignore */ }
    }
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
    setUser(null);
    setTenant(null);
    setIsAuthenticated(false);
    setIsDemoMode(false);
    window.location.href = '/login';
  };

  return (
    <AuthContext.Provider
      value={{ user, tenant, isLoading, isAuthenticated, isDemoMode, login, logout, loadUser, activateDemo }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within <AuthProvider>');
  return ctx;
}
