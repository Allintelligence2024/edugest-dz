import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import api from '@api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [sessionExpired, setSessionExpired] = useState(false);
  const [onboardingComplete, setOnboardingComplete] = useState(true);

  const getRole = useCallback((u) => {
    if (!u) return null;
    return typeof u.role === 'object' ? u.role?.nom : u.role;
  }, []);

  const checkOnboarding = useCallback(async (userData) => {
    const r = getRole(userData);
    if (r !== 'admin') { setOnboardingComplete(true); return; }
    try {
      const res = await api('/onboarding');
      setOnboardingComplete(!!res.complete);
    } catch {
      setOnboardingComplete(true);
    }
  }, [getRole]);

  useEffect(() => {
    const token = localStorage.getItem('access_token');
    if (!token) { setIsLoading(false); return; }

    api('/auth/me')
      .then(data => {
        const userData = data?.data ?? data?.user ?? null;
        if (userData) {
          setUser(userData);
          return checkOnboarding(userData);
        }
      })
      .catch(() => {
        localStorage.removeItem('access_token');
        localStorage.removeItem('refresh_token');
      })
      .finally(() => setIsLoading(false));
  }, [checkOnboarding]);

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
    await checkOnboarding(userData);
    return userData;
  }, [checkOnboarding]);

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

  const marquerOnboardingComplete = useCallback(() => {
    setOnboardingComplete(true);
  }, []);

  const isAuthenticated = !!user;
  const role = getRole(user);

  const homeRoute = useCallback(() => {
    switch (role) {
      case 'admin':      return onboardingComplete ? '/' : '/onboarding';
      case 'enseignant': return '/planning';
      case 'eleve':      return '/devoirs';
      case 'parent':     return '/';
      default:           return '/';
    }
  }, [role, onboardingComplete]);

  return (
    <AuthContext.Provider value={{
      user, isLoading, isAuthenticated, role, sessionExpired, onboardingComplete,
      login, logout, onSessionExpired, homeRoute, marquerOnboardingComplete,
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
