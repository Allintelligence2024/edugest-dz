import { createContext, useContext, useState, useEffect, useCallback } from 'react';

const ModulesContext = createContext(null);

export function ModulesProvider({ children }) {
  const [modules, setModules]   = useState([]);
  const [actifs, setActifs]     = useState([]);
  const [loading, setLoading]   = useState(true);
  const [error, setError]       = useState(null);

  const loadModules = useCallback(async () => {
    const token = localStorage.getItem('token');
    if (!token) { setLoading(false); return; }

    try {
      const BASE = import.meta.env.VITE_API_BASE_URL || '/api/v1';
      const [allRes, actifsRes] = await Promise.all([
        fetch(`${BASE}/modules`, {
          headers: { Authorization: `Bearer ${token}`, 'X-Tenant-ID': localStorage.getItem('tenantId') ?? '' }
        }).then(r => r.json()),
        fetch(`${BASE}/modules/actifs`, {
          headers: { Authorization: `Bearer ${token}`, 'X-Tenant-ID': localStorage.getItem('tenantId') ?? '' }
        }).then(r => r.json()),
      ]);

      if (allRes.success)    setModules(allRes.data?.modules ?? []);
      if (actifsRes.success) setActifs(actifsRes.data ?? []);
    } catch (e) {
      setError(e.message);
      setActifs(['transport','cantine','stock','budget','personnel','entretien',
                 'surveillance','lms','marketplace','examens','diagnostic',
                 'billets','pointage','bibliotheque','core']);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadModules(); }, [loadModules]);

  const isActive = useCallback((moduleKey) => {
    if (loading) return true;
    if (error)   return true;
    if (moduleKey === 'core') return true;
    return actifs.includes(moduleKey);
  }, [actifs, loading, error]);

  const activerModule = useCallback(async (moduleKey) => {
    const token = localStorage.getItem('token');
    const BASE  = import.meta.env.VITE_API_BASE_URL || '/api/v1';
    const res   = await fetch(`${BASE}/modules/${moduleKey}/activer`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}`, 'X-Tenant-ID': localStorage.getItem('tenantId') ?? '' },
    }).then(r => r.json());

    if (res.success) {
      setActifs(prev => [...new Set([...prev, moduleKey])]);
      setModules(prev => prev.map(m => m.key === moduleKey ? { ...m, actif: true } : m));
    }
    return res;
  }, []);

  const desactiverModule = useCallback(async (moduleKey, raison = '') => {
    const token = localStorage.getItem('token');
    const BASE  = import.meta.env.VITE_API_BASE_URL || '/api/v1';
    const res   = await fetch(`${BASE}/modules/${moduleKey}/desactiver`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json', 'X-Tenant-ID': localStorage.getItem('tenantId') ?? '' },
      body: JSON.stringify({ raison }),
    }).then(r => r.json());

    if (res.success) {
      setActifs(prev => prev.filter(k => k !== moduleKey));
      setModules(prev => prev.map(m => m.key === moduleKey ? { ...m, actif: false } : m));
    }
    return res;
  }, []);

  return (
    <ModulesContext.Provider value={{
      modules, actifs, loading, error,
      isActive, activerModule, desactiverModule, recharger: loadModules,
    }}>
      {children}
    </ModulesContext.Provider>
  );
}

export function useModules() {
  const ctx = useContext(ModulesContext);
  if (!ctx) throw new Error('useModules must be used within <ModulesProvider>');
  return ctx;
}
