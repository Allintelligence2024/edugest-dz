import api from './axiosInstance';

export const auditApi = {
  list: (params) => api.get('/audit-logs', { params }),
  get: (id) => api.get(`/audit-logs/${id}`),
};
