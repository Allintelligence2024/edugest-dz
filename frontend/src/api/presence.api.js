import api from './axiosInstance';

export const presenceApi = {
  parSeance: (seanceId) => api.get(`/presences/seance/${seanceId}`),
  saisir: (seanceId, data) => api.post(`/presences/seance/${seanceId}`, data),
  update: (id, data) => api.put(`/presences/${id}`, data),
  rapport: (params) => api.get('/presences/rapport', { params }),
};
