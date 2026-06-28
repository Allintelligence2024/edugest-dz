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
