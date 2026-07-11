const BASE_URL = import.meta.env.VITE_API_URL ?? '';

let _onSessionExpired = null;

export function setSessionExpiredHandler(fn) {
  _onSessionExpired = fn;
}

export function getToken() {
  return localStorage.getItem('access_token') ?? '';
}

export async function api(path, options = {}) {
  const url = `${BASE_URL}/api/v1${path}`;

  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    ...options.headers,
  };

  const token = getToken();
  if (token) headers['Authorization'] = `Bearer ${token}`;

  const response = await fetch(url, { ...options, headers });

  if (response.status === 401) {
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
    if (_onSessionExpired) _onSessionExpired();
    throw new Error('SESSION_EXPIRED');
  }

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    const msg = data?.message ?? data?.error?.message ?? `HTTP ${response.status}`;
    throw new Error(msg);
  }

  return data;
}

export function getApiUrl(path) {
  return `${BASE_URL}/api/v1${path}`;
}

export default api;
