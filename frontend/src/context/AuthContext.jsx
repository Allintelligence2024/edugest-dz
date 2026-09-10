import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import api, { rafraichirJeton } from '@api/client';
import { setAccessToken, clearAccessToken, purgerAncienStockage } from '@api/tokenStore';

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
    // Le jeton d'accès vit en mémoire : après un rechargement de page il est
    // perdu. On le reconstitue à partir du cookie httpOnly de refresh, ce qui
    // préserve la session sans jamais exposer de secret au JavaScript.
    let annule = false;

    (async () => {
      try {
        await rafraichirJeton();
      } catch {
        // Pas de cookie valide : visiteur non connecté, cas normal.
        if (!annule) setIsLoading(false);
        return;
      }

      try {
        const data = await api('/auth/me');
        const userData = data?.data ?? data?.user ?? null;
        if (!annule && userData) {
          setUser(userData);
          await checkOnboarding(userData);
        }
      } catch {
        if (!annule) clearAccessToken();
      } finally {
        if (!annule) setIsLoading(false);
      }
    })();

    return () => { annule = true; };
  }, [checkOnboarding]);

  const login = useCallback(async (email, password) => {
    const data = await api('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });

    const token = data?.access_token ?? data?.token;
    if (!token) throw new Error(data?.message ?? 'Identifiants incorrects');

    // En mémoire uniquement — le refresh token est déjà posé par le serveur
    // sous forme de cookie httpOnly.
    setAccessToken(token, data?.expires_in);
    const userData = data?.user ?? null;
    setUser(userData);
    setSessionExpired(false);
    await checkOnboarding(userData);
    return userData;
  }, [checkOnboarding]);

  const logout = useCallback(async () => {
    // L'appel serveur révoque le refresh token et efface le cookie ; sans
    // lui, la session resterait rejouable depuis le cookie.
    try {
      await api('/auth/logout', { method: 'POST' });
    } catch {
      /* déconnexion locale même si le réseau échoue */
    }
    clearAccessToken();
    purgerAncienStockage();
    setUser(null);
  }, []);

  const onSessionExpired = useCallback(() => {
    clearAccessToken();
    purgerAncienStockage();
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
