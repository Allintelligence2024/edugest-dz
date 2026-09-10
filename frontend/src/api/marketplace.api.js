import api from './axiosInstance';

export const marketplaceApi = {
  searchOffres: (params) => api.get('/marketplace/offres', { params }),
  getOffre: (id) => api.get(`/marketplace/offres/${id}`),
  createOffre: (data) => api.post('/marketplace/offres', data),
  updateOffre: (id, data) => api.put(`/marketplace/offres/${id}`, data),
  deleteOffre: (id) => api.delete(`/marketplace/offres/${id}`),
  mesOffres: () => api.get('/marketplace/mes-offres'),

  createReservation: (data) => api.post('/marketplace/reservations', data),
  payerReservation: (id, data) => api.post(`/marketplace/reservations/${id}/payer`, data),
  mesReservations: () => api.get('/marketplace/mes-reservations'),
  annulerReservation: (id) => api.post(`/marketplace/reservations/${id}/annuler`),
  terminerReservation: (id) => api.post(`/marketplace/reservations/${id}/terminer`),

  createAvis: (data) => api.post('/marketplace/avis', data),
  getAvisEnseignant: (id) => api.get(`/marketplace/avis/enseignant/${id}`),
};
