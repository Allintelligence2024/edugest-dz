import api from './axiosInstance';

export const noteApi = {
  update: (id, data) => api.put(`/notes/${id}`, data),
};

export const evaluationApi = {
  list: (params) => api.get('/evaluations', { params }),
  get: (id) => api.get(`/evaluations/${id}`),
  create: (data) => api.post('/evaluations', data),
  update: (id, data) => api.put(`/evaluations/${id}`, data),
  delete: (id) => api.delete(`/evaluations/${id}`),
  getNotes: (id) => api.get(`/evaluations/${id}/notes`),
  saisirNotes: (id, data) => api.post(`/evaluations/${id}/notes`, data),
};
