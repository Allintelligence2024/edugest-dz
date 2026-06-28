import api from './axiosInstance';

export const enseignantApi = {
  list: (params) => api.get('/enseignants', { params }),
  get: (id) => api.get(`/enseignants/${id}`),
  create: (data) => api.post('/enseignants', data),
  update: (id, data) => api.put(`/enseignants/${id}`, data),
  delete: (id) => api.delete(`/enseignants/${id}`),
};
