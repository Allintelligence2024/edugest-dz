import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import api from '@api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [sessionExpired, setSessionExpired] = useState(false);

  useEffect(() => {
    const token = localStorage.getItem('access_token');
    if (!token) { setIsLoading(false); return; }

    api('/auth/me')
      .then(data => {
        const userData = data?.data ?? data?.user ?? null;
        if (userData) setUser(userData);
      })
      .catch(() => {
        localStorage.removeItem('access_token');
        localStorage.removeItem('refresh_token');
      })
      .finally(() => setIsLoading(false));
  }, []);

  const login = useCallback(async (email, password) => {
    const data = await api('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });

    const token = data?.access_token ?? data?.token;
    if (!token) throw new Error(data?.message ?? 'Identifiants incorrects');

    localStorage.setItem('access_token', token);
    const userData = data?.user ?? null;
    setUser(userData);
    setSessionExpired(false);
    return userData;
  }, []);

  const logout = useCallback(async () => {
    const token = localStorage.getItem('access_token');
    if (token) {
      api('/auth/logout', { method: 'POST' }).catch(() => {});
    }
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
    setUser(null);
  }, []);

  const onSessionExpired = useCallback(() => {
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
    setUser(null);
    setSessionExpired(true);
  }, []);

  const isAuthenticated = !!user;
  const role = user?.role ?? null;

  const homeRoute = useCallback(() => {
    switch (role) {
      case 'admin':      return '/';
      case 'enseignant': return '/planning';
      case 'eleve':      return '/devoirs';
      case 'parent':     return '/';
      default:           return '/';
    }
  }, [role]);

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
