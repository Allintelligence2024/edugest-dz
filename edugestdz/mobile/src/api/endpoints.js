import api from './axios';

export const authApi = {
  login: (email, password) => api.post('/auth/login', { email, password }),
  logout: () => api.post('/auth/logout'),
  me: () => api.get('/auth/me'),
  refresh: (refresh_token) => api.post('/auth/refresh', { refresh_token }),
};

export const planningApi = {
  list: (params) => api.get('/planning', { params }),
};

export const notesApi = {
  byEleve: (eleveId, params) => api.get(`/eleves/${eleveId}/notes`, { params }),
};

export const presencesApi = {
  byEleve: (eleveId, params) => api.get(`/eleves/${eleveId}/presences`, { params }),
};

export const paiementsApi = {
  byEleve: (eleveId, params) => api.get(`/eleves/${eleveId}/paiements`, { params }),
};

export const messagesApi = {
  conversations: () => api.get('/messages/conversations'),
  conversation: (id) => api.get(`/messages/conversations/${id}`),
  send: (convId, message) => api.post(`/messages/conversations/${convId}`, { message }),
};

export const bulletinsApi = {
  byEleve: (eleveId) => api.get(`/eleves/${eleveId}/bulletins`),
  pdf: (id) => api.get(`/bulletins/${id}/pdf`),
};

// ── API Enseignant ──
export const enseignantApi = {
  planning: (params)          => api.get('/planning', { params }),
  seances:  (params)          => api.get('/seances', { params }),
  groupes:  ()                => api.get('/groupes'),
  presences: {
    parSeance: (seanceId)     => api.get(`/presences/seance/${seanceId}`),
    saisir:    (seanceId, data)=> api.post(`/presences/seance/${seanceId}`, data),
  },
  evaluations: {
    list:      (params)       => api.get('/evaluations', { params }),
    notes:     (evalId)       => api.get(`/evaluations/${evalId}/notes`),
    saisirNotes: (evalId, data)=> api.post(`/evaluations/${evalId}/notes`, data),
  },
  pointage: {
    arrivee: (id, data)       => api.post(`/pointage/enseignants/${id}/arrivee`, data),
    depart:  (id, data)       => api.post(`/pointage/enseignants/${id}/depart`, data),
    aujourdhui: ()            => api.get('/pointage/enseignants/aujourd-hui'),
  },
  statistiques: (id)          => api.get(`/enseignants/${id}/statistiques`),
};

// ── API Admin / Dashboard ──
export const adminApi = {
  dashboard: {
    finance:     ()           => api.get('/finance/tableau-bord'),
    pedagogique: ()           => api.get('/rapports/pedagogique'),
  },
  eleves: {
    list:    (params)         => api.get('/eleves', { params }),
    show:    (id)             => api.get(`/eleves/${id}`),
    notes:   (id)             => api.get(`/eleves/${id}/notes`),
    paiements:(id)            => api.get(`/eleves/${id}/paiements`),
    bulletins:(id)            => api.get(`/eleves/${id}/bulletins`),
    presences:(id)            => api.get(`/eleves/${id}/presences`),
  },
  absences: {
    jour:        (params)     => api.get('/absences/jour', { params }),
    marquerPresent:(eleveId, data) => api.post(`/absences/${eleveId}/present`, data),
  },
  finance: {
    bilan:   (params)         => api.get('/budget/bilan-mensuel', { params }),
    factures:(params)         => api.get('/factures', { params }),
    impayes: ()               => api.get('/finance/impayes'),
  },
};
