import api from './axiosInstance';

export const groupeApi = {
  list: (params) => api.get('/groupes', { params }),
  get: (id) => api.get(`/groupes/${id}`),
  create: (data) => api.post('/groupes', data),
  update: (id, data) => api.put(`/groupes/${id}`, data),
  delete: (id) => api.delete(`/groupes/${id}`),
  getEleves: (id) => api.get(`/groupes/${id}/eleves`),
  addEleve: (groupeId, data) => api.post(`/groupes/${groupeId}/eleves`, data),
  removeEleve: (groupeId, eleveId) =>
    api.delete(`/groupes/${groupeId}/eleves/${eleveId}`),
};
