import React, { createContext, useContext, useState, useEffect, useCallback, useMemo } from 'react';
import * as SecureStore from 'expo-secure-store';
import { authApi } from '../api/endpoints';
import { TOKEN_KEY, REFRESH_KEY } from '../api/axios';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [tenant, setTenant] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  const loadUser = useCallback(async () => {
    setIsLoading(true);
    try {
      const token = await SecureStore.getItemAsync(TOKEN_KEY);
      if (!token) {
        setIsLoading(false);
        return;
      }
      api.defaults.headers.common.Authorization = `Bearer ${token}`;
      const res = await authApi.me();
      setUser(res.data || res.user);
      setTenant(res.tenant || null);
      setIsAuthenticated(true);
    } catch {
      await SecureStore.deleteItemAsync(TOKEN_KEY);
      await SecureStore.deleteItemAsync(REFRESH_KEY);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => { loadUser(); }, [loadUser]);

  const login = useCallback(async (email, password) => {
    const res = await authApi.login(email, password);
    const { access_token, refresh_token, user: u, tenant: t } = res;
    await SecureStore.setItemAsync(TOKEN_KEY, access_token);
    if (refresh_token) await SecureStore.setItemAsync(REFRESH_KEY, refresh_token);
    setUser(u);
    setTenant(t);
    setIsAuthenticated(true);
    return { user: u, tenant: t };
  }, []);

  const logout = useCallback(async () => {
    try { await authApi.logout(); } catch {}
    await SecureStore.deleteItemAsync(TOKEN_KEY);
    await SecureStore.deleteItemAsync(REFRESH_KEY);
    delete api.defaults.headers.common.Authorization;
    setUser(null);
    setTenant(null);
    setIsAuthenticated(false);
  }, []);

  const value = useMemo(() => ({
    user, tenant, isLoading, isAuthenticated, login, logout, loadUser,
  }), [user, tenant, isLoading, isAuthenticated, login, logout, loadUser]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
