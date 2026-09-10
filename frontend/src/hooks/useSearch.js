import { useState, useCallback, useRef, useEffect } from 'react';
import { api } from '@api/client';

export function useSearch(debounceMs = 300) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState([]);
  const [total, setTotal] = useState(0);
  const [isLoading, setIsLoading] = useState(false);
  const [isOpen, setIsOpen] = useState(false);
  const timerRef = useRef(null);

  const search = useCallback(async (q) => {
    if (q.length < 2) {
      setResults([]);
      setTotal(0);
      setIsLoading(false);
      return;
    }
    setIsLoading(true);
    try {
      const res = await api(`/search?q=${encodeURIComponent(q)}`);
      setResults(res.data || []);
      setTotal(res.total || 0);
    } catch {
      setResults([]);
      setTotal(0);
    } finally {
      setIsLoading(false);
    }
  }, []);

  const handleQueryChange = useCallback((value) => {
    setQuery(value);
    setIsOpen(true);
    if (timerRef.current) clearTimeout(timerRef.current);
    timerRef.current = setTimeout(() => search(value), debounceMs);
  }, [search, debounceMs]);

  useEffect(() => {
    return () => { if (timerRef.current) clearTimeout(timerRef.current); };
  }, []);

  return {
    query,
    setQuery: handleQueryChange,
    results,
    total,
    isLoading,
    isOpen,
    setIsOpen,
  };
}
