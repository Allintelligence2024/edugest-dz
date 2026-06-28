import api from './axiosInstance';

export const paieApi = {
  list: (params) => api.get('/paies', { params }),
  calculer: (data) => api.post('/paies/calculer', data),
  valider: (id) => api.post(`/paies/${id}/valider`),
  payer: (id, data) => api.post(`/paies/${id}/payer`, data),
  bulletin: (id) => api.get(`/paies/${id}/bulletin`),
  bulletinPdf: (id) => api.get(`/paies/${id}/bulletin`, { responseType: 'blob' }),
};
