import api from './axiosInstance';

export const matiereApi = {
  list: (params) => api.get('/matieres', { params }),
  get: (id) => api.get(`/matieres/${id}`),
  create: (data) => api.post('/matieres', data),
  update: (id, data) => api.put(`/matieres/${id}`, data),
  delete: (id) => api.delete(`/matieres/${id}`),
};
