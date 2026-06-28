import api from './axiosInstance';

export const planningApi = {
  get: (params) => api.get('/planning', { params }),
  conflits: (params) => api.get('/planning/conflits', { params }),
  generer: (data) => api.post('/planning/generer', data),
};

export const coursApi = {
  list: (params) => api.get('/cours', { params }),
  get: (id) => api.get(`/cours/${id}`),
  create: (data) => api.post('/cours', data),
  update: (id, data) => api.put(`/cours/${id}`, data),
  delete: (id) => api.delete(`/cours/${id}`),
};

export const seanceApi = {
  list: (params) => api.get('/seances', { params }),
  create: (data) => api.post('/seances', data),
  demarrer: (id) => api.post(`/seances/${id}/demarrer`),
  terminer: (id) => api.post(`/seances/${id}/terminer`),
  annuler: (id) => api.post(`/seances/${id}/annuler`),
  reporter: (id, data) => api.post(`/seances/${id}/reporter`, data),
};
