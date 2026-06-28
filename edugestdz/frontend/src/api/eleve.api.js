import api from './axiosInstance';

export const eleveApi = {
  list: (params) => api.get('/eleves', { params }),
  get: (id) => api.get(`/eleves/${id}`),
  create: (data) => api.post('/eleves', data),
  update: (id, data) => api.put(`/eleves/${id}`, data),
  delete: (id) => api.delete(`/eleves/${id}`),
  uploadPhoto: (id, formData) =>
    api.post(`/eleves/${id}/photo`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
  getNotes: (id, params) => api.get(`/eleves/${id}/notes`, { params }),
  getPresences: (id, params) => api.get(`/eleves/${id}/presences`, { params }),
  getPaiements: (id) => api.get(`/eleves/${id}/paiements`),
  inscrire: (id, data) => api.post(`/eleves/${id}/inscription`, data),
};
