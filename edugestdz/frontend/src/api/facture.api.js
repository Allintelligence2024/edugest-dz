import api from './axiosInstance';

export const factureApi = {
  list: (params) => api.get('/factures', { params }),
  get: (id) => api.get(`/factures/${id}`),
  create: (data) => api.post('/factures', data),
  update: (id, data) => api.put(`/factures/${id}`, data),
  delete: (id) => api.delete(`/factures/${id}`),
  getPdf: (id) => api.get(`/factures/${id}/pdf`, { responseType: 'blob' }),
  envoyer: (id) => api.post(`/factures/${id}/envoyer`),
};

export const paiementApi = {
  list: (params) => api.get('/paiements', { params }),
  create: (data) => api.post('/paiements', data),
  getRecu: (id) => api.get(`/paiements/${id}/recu`, { responseType: 'blob' }),
  caisseJour: (params) => api.get('/paiements/caisse-jour', { params }),
};
