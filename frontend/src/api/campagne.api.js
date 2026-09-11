import api from './axiosInstance';

export const campagneApi = {
  list: (params) => api.get('/campagnes', { params }),
  get: (id) => api.get(`/campagnes/${id}`),
  create: (data) => api.post('/campagnes', data),
  envoyer: (id) => api.post(`/campagnes/${id}/envoyer`),
};
