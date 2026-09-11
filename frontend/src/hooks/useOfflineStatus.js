/**
 * useOfflineStatus — Détecte l'état réseau et gère le cache hors-ligne.
 */

import { useState, useEffect, useCallback } from 'react';

export function useOfflineStatus() {
  const [isOffline,      setIsOffline]      = useState(!navigator.onLine);
  const [wasOffline,     setWasOffline]      = useState(false);
  const [pendingActions, setPendingActions]  = useState([]);

  useEffect(() => {
    const handleOnline  = () => {
      setIsOffline(false);
      setWasOffline(true);
      setTimeout(() => setWasOffline(false), 3000);
    };

    const handleOffline = () => {
      setIsOffline(true);
      setWasOffline(false);
    };

    window.addEventListener('online',  handleOnline);
    window.addEventListener('offline', handleOffline);

    return () => {
      window.removeEventListener('online',  handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  const queuerAction = useCallback((action) => {
    if (!isOffline) {
      action();
    } else {
      setPendingActions(prev => [...prev, action]);
    }
  }, [isOffline]);

  useEffect(() => {
    if (!isOffline && pendingActions.length > 0) {
      pendingActions.forEach(action => {
        try { action(); } catch {}
      });
      setPendingActions([]);
    }
  }, [isOffline, pendingActions]);

  return { isOffline, wasOffline, pendingActions: pendingActions.length, queuerAction };
}
