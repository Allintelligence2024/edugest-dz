const BASE_URL = (() => {
  const raw = import.meta.env.VITE_API_URL ?? import.meta.env.VITE_API_BASE_URL ?? '';
  const base = raw.replace(/\/api\/v1\/?$/, '');
  if (!base || base.includes('TON_SERVICE')) {
    console.warn(
      '[EduGest] VITE_API_URL non configuré.\n' +
      'Configurer dans Vercel : Settings → Environment Variables\n' +
      'VITE_API_URL = https://[votre-backend].up.railway.app/api/v1'
    );
  }
  return base ? `${base}/api/v1` : '/api/v1';
})();

const apiClient = {
  getHeaders() {
    const token    = localStorage.getItem('access_token');
    const tenantId = localStorage.getItem('tenantId');
    return {
      'Content-Type': 'application/json',
      'Accept':       'application/json',
      ...(token    ? { 'Authorization': `Bearer ${token}` } : {}),
      ...(tenantId ? { 'X-Tenant-ID': tenantId }            : {}),
    };
  },

  async request(method, path, body = null) {
    const config = {
      method,
      headers: this.getHeaders(),
    };
    if (body) config.body = JSON.stringify(body);

    try {
      const response = await fetch(`${BASE_URL}${path}`, config);

      if (response.status === 401) {
        localStorage.removeItem('access_token');
        localStorage.removeItem('tenantId');
        window.location.href = '/login';
        return null;
      }

      return response.json();
    } catch (error) {
      console.error(`API Error [${method} ${path}]:`, error.message);
      if (error.message === 'Failed to fetch' || error.name === 'TypeError') {
        throw new Error(
          'Impossible de joindre le serveur. ' +
          'Vérifiez votre connexion internet ou contactez le support.'
        );
      }
      throw error;
    }
  },

  get:    (path)         => apiClient.request('GET',    path),
  post:   (path, body)   => apiClient.request('POST',   path, body),
  put:    (path, body)   => apiClient.request('PUT',    path, body),
  patch:  (path, body)   => apiClient.request('PATCH',  path, body),
  delete: (path)         => apiClient.request('DELETE', path),
};

export default apiClient;

export const authApi = {
  login:   (email, password) => apiClient.post('/auth/login', { email, password }),
  logout:  ()                => apiClient.post('/auth/logout'),
  refresh: ()                => apiClient.post('/auth/refresh'),
  me:      ()                => apiClient.get('/auth/me'),
};

export const elevesApi = {
  list:    (params = '') => apiClient.get(`/eleves?${params}`),
  get:     (id)          => apiClient.get(`/eleves/${id}`),
  create:  (data)        => apiClient.post('/eleves', data),
  update:  (id, data)    => apiClient.put(`/eleves/${id}`, data),
  delete:  (id)          => apiClient.delete(`/eleves/${id}`),
  qrCode:  (id)          => `${BASE_URL}/eleves/${id}/qr-code`,
};

export const financeApi = {
  dashboard:      ()          => apiClient.get('/finance/tableau-bord'),
  factures:       (params='') => apiClient.get(`/factures?${params}`),
  payer:          (factureId) => apiClient.post('/paiements/cib/initier', { facture_id: factureId }),
};

export const absencesApi = {
  list:      (params='') => apiClient.get(`/absences?${params}`),
  create:    (data)      => apiClient.post('/absences', data),
  justifier: (id, motif) => apiClient.patch(`/absences/${id}/justifier`, { motif }),
};

export const rapportsApi = {
  absencesPDF:   (mois, annee) => `${BASE_URL}/rapports/absences-pdf?mois=${mois}&annee=${annee}`,
  simulationBEM: (eleveId)     => apiClient.get(`/rapports/simulation-bem/${eleveId}`),
  simulationBAC: (eleveId, filiere) => apiClient.get(`/rapports/simulation-bac/${eleveId}?filiere=${filiere}`),
};

export const surveillanceApi = {
  alertes:  (params='') => apiClient.get(`/surveillance/alertes?${params}`),
  cameras:  ()          => apiClient.get('/surveillance/cameras'),
  traiter:  (id, note)  => apiClient.post(`/surveillance/alertes/${id}/traiter`, { note_admin: note }),
  addCamera:(data)      => apiClient.post('/surveillance/cameras', data),
};

export const marketplaceApi = {
  recherche: (params='') => fetch(`${BASE_URL}/marketplace/recherche?${params}`).then(r => r.json()),
  featured:  ()          => fetch(`${BASE_URL}/marketplace/featured`).then(r => r.json()),
  profil:    (tenantId)  => fetch(`${BASE_URL}/marketplace/centres/${tenantId}`).then(r => r.json()),
  reserver:  (data)      => apiClient.post('/marketplace/reserver', data),
};
