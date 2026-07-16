import { useState, useCallback, useRef, useEffect } from 'react';
import api from '@api/axiosInstance';
import { toast } from 'react-hot-toast';

export const useApi = (endpoint, options = {}) => {
  const [data, setData] = useState(options.initialData ?? null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState(null);
  const [meta, setMeta] = useState(null);
  const abortRef = useRef(null);

  const fetch = useCallback(async (params = {}) => {
    if (abortRef.current) abortRef.current.abort();
    abortRef.current = new AbortController();
    setIsLoading(true);
    setError(null);
    try {
      const res = await api.get(endpoint, { params, signal: abortRef.current.signal });
      setData(res.data ?? res);
      setMeta(res.meta ?? null);
      return res;
    } catch (err) {
      if (err.name !== 'AbortError') {
        setError(err);
        if (options.showError !== false) toast.error(err?.error?.message || 'Erreur de chargement');
      }
      return null;
    } finally { setIsLoading(false); }
  }, [endpoint]);

  const mutate = useCallback(async (method, body = null, id = null) => {
    setIsLoading(true);
    try {
      const url = id ? `${endpoint}/${id}` : endpoint;
      const res = await api[method](url, body);
      const message = res.message || 'Opération réussie';
      if (options.showSuccess !== false) toast.success(message);
      return res;
    } catch (err) {
      toast.error(err?.error?.message || "Erreur lors de l'opération");
      throw err;
    } finally { setIsLoading(false); }
  }, [endpoint]);

  return {
    data, isLoading, error, meta,
    fetch,
    create: (body) => mutate('post', body),
    update: (id, body) => mutate('put', body, id),
    remove: (id) => mutate('delete', null, id),
    refresh: (params) => fetch(params),
  };
};

export const useList = (endpoint, queryOrParams = {}, _name, refreshKey) => {
  const [items, setItems] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [meta, setMeta] = useState({ total: 0, last_page: 1 });
  const lastFetchRef = useRef(null);

  const parseQuery = (q) => {
    if (typeof q === 'string') {
      const sp = new URLSearchParams(q);
      const obj = {};
      sp.forEach((v, k) => { obj[k] = v; });
      return obj;
    }
    return q || {};
  };

  const load = useCallback(async (overrideParams) => {
    const params = overrideParams !== undefined ? parseQuery(overrideParams) : parseQuery(queryOrParams);
    setIsLoading(true);
    try {
      const res = await api.get(endpoint, { params });
      setItems(res.data || []);
      setMeta(res.meta || { total: 0 });
      lastFetchRef.current = { endpoint: JSON.stringify(params) };
      return res;
    } catch (err) {
      if (err?.response?.status !== 401) {
        toast.error('Erreur de chargement');
      }
      return null;
    } finally { setIsLoading(false); }
  }, [endpoint]);

  useEffect(() => { load(queryOrParams); }, [refreshKey, typeof queryOrParams === 'string' ? queryOrParams : '']);

  const changePage = (page) => {
    const current = typeof queryOrParams === 'string' ? parseQuery(queryOrParams) : { ...queryOrParams };
    load({ ...current, page });
  };

  const changeFilter = (filters) => {
    load({ ...(typeof queryOrParams === 'string' ? parseQuery(queryOrParams) : queryOrParams), ...filters, page: 1 });
  };

  const search = (q) => {
    load({ search: q, page: 1 });
  };

  const reset = () => {
    load(queryOrParams);
  };

  const data = { data: items, meta };

  return { data, items, isLoading, meta, params: typeof queryOrParams === 'string' ? parseQuery(queryOrParams) : queryOrParams, load, changePage, changeFilter, search, reset };
};
