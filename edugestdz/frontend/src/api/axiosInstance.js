import axios from 'axios';
import {
  getAccessToken,
  setAccessToken,
  clearAccessToken,
  purgerAncienStockage,
} from './tokenStore';

const DEMO_MODE = import.meta.env.VITE_DEMO_MODE === 'true';

const _rawBase = (import.meta.env.VITE_API_URL || '/api/v1').replace(/\/api\/v1\/?$/, '');
const BASE = _rawBase ? `${_rawBase}/api/v1` : '/api/v1';

const api = axios.create({
  baseURL: BASE,
  // Indispensable : le refresh token est un cookie httpOnly, il n'est
  // transmis que si les credentials sont explicitement autorisés.
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

// Nettoyage unique des jetons laissés par l'ancienne version localStorage.
purgerAncienStockage();

api.interceptors.request.use((config) => {
  if (DEMO_MODE) return config;

  const token = getAccessToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

/* ────────────────────────────────────────────────────────────────────────
 * Rafraîchissement silencieux, sérialisé.
 *
 * Si plusieurs requêtes échouent en 401 simultanément, on ne déclenche
 * qu'UN seul appel à /auth/refresh : sans cela, la rotation des refresh
 * tokens côté serveur interpréterait les appels concurrents comme une
 * réutilisation de jeton volé et révoquerait toute la session.
 * ──────────────────────────────────────────────────────────────────────── */
let refreshEnCours = null;

async function rafraichir() {
  if (!refreshEnCours) {
    refreshEnCours = axios
      .post(`${BASE}/auth/refresh`, {}, { withCredentials: true })
      .then((res) => {
        const { access_token: accessToken, expires_in: expiresIn } = res.data ?? {};
        if (!accessToken) throw new Error('REFRESH_SANS_JETON');
        setAccessToken(accessToken, expiresIn);
        return accessToken;
      })
      .finally(() => {
        refreshEnCours = null;
      });
  }
  return refreshEnCours;
}

export { rafraichir };

api.interceptors.response.use(
  (response) => response.data,
  async (error) => {
    if (DEMO_MODE) return Promise.reject(error);

    const originalRequest = error.config;
    const estRouteRefresh = originalRequest?.url?.includes('/auth/refresh');

    if (error.response?.status === 401 && !originalRequest?._retry && !estRouteRefresh) {
      originalRequest._retry = true;

      try {
        const token = await rafraichir();
        originalRequest.headers.Authorization = `Bearer ${token}`;
        return api(originalRequest);
      } catch {
        clearAccessToken();
        if (typeof window !== 'undefined' && !window.location.pathname.startsWith('/login')) {
          window.location.href = '/login';
        }
        return Promise.reject(error);
      }
    }

    const normalized = error.response?.data || {
      success: false,
      error: { code: 'NETWORK_ERROR', message: error.message || 'Erreur réseau' },
    };
    return Promise.reject(normalized);
  },
);

export default api;
export { DEMO_MODE };
