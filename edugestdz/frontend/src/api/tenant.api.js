import api from './axiosInstance';

export const tenantApi = {
  list: (params) => api.get('/super-admin/tenants', { params }),
  get: (id) => api.get(`/super-admin/tenants/${id}`),
  create: (data) => api.post('/super-admin/tenants', data),
  update: (id, data) => api.put(`/super-admin/tenants/${id}`, data),
  stats: () => api.get('/super-admin/stats'),
  impersonate: (id) => api.post(`/super-admin/tenants/${id}/impersonate`),
};
