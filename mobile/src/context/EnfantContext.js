import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { parentsApi } from '../api/endpoints';
import { useAuth } from './AuthContext';

const EnfantContext = createContext(null);

/**
 * PILOTE P1-C2..C5 — Contexte enfant actif du parent connecté.
 * Source unique : GET /parents/mes-enfants (backend, scopé via PerimetreAccesService).
 * Tous les écrans parent consomment `enfantActif` au lieu de `user.eleve_id` (inexistant).
 */
export function EnfantProvider({ children }) {
  const { user } = useAuth();
  const [enfants, setEnfants] = useState([]);
  const [enfantActif, setEnfantActif] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const recharger = useCallback(async () => {
    const roles = user?.roles ?? null;
    if (Array.isArray(roles) && !roles.includes('parent')) {
      setEnfants([]);
      setEnfantActif(null);
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await parentsApi.mesEnfants();
      const liste = res?.data ?? [];
      setEnfants(liste);
      setEnfantActif((prev) => {
        if (prev) return liste.find((e) => e.id === prev.id) ?? liste[0] ?? null;
        return liste[0] ?? null;
      });
    } catch (e) {
      setError(e);
      setEnfants([]);
      setEnfantActif(null);
    } finally {
      setLoading(false);
    }
  }, [user?.id, Array.isArray(user?.roles) ? user.roles.join(',') : '']);

  useEffect(() => {
    recharger();
  }, [recharger]);

  const choisir = useCallback(
    (id) => {
      const trouve = enfants.find((e) => e.id === id);
      if (trouve) setEnfantActif(trouve);
    },
    [enfants]
  );

  return (
    <EnfantContext.Provider value={{ enfants, enfantActif, choisir, loading, error, recharger }}>
      {children}
    </EnfantContext.Provider>
  );
}

export function useEnfants() {
  const ctx = useContext(EnfantContext);
  if (!ctx) throw new Error('useEnfants doit être utilisé dans <EnfantProvider>');
  return ctx;
}
