import api from './axiosInstance';

export const salleApi = {
  list: (params) => api.get('/salles', { params }),
  get: (id) => api.get(`/salles/${id}`),
  create: (data) => api.post('/salles', data),
  update: (id, data) => api.put(`/salles/${id}`, data),
  delete: (id) => api.delete(`/salles/${id}`),
};
