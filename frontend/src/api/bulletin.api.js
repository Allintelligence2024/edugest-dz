import api from './axiosInstance';

export const bulletinApi = {
  list: (params) => api.get('/bulletins', { params }),
  generer: (data) => api.post('/bulletins/generer', data),
  get: (id) => api.get(`/bulletins/${id}`),
  pdf: (id) => api.get(`/bulletins/${id}/pdf`, { responseType: 'blob' }),
  envoyer: (id) => api.post(`/bulletins/${id}/envoyer`),
};
