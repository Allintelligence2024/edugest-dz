import api from './axiosInstance';

export const authApi = {
  login: (email, password) => api.post('/auth/login', { email, password }),
  logout: () => api.post('/auth/logout'),
  me: () => api.get('/auth/me'),
  changePassword: (data) => api.put('/auth/change-password', data),
  updateProfile: (data) => api.put('/auth/profile', data),

  get2faStatus: () => api.get('/auth/2fa/status'),
  enable2fa: (type, phone) => api.post('/auth/2fa/enable', { type, phone }),
  confirm2fa: (code) => api.post('/auth/2fa/confirm', { code }),
  disable2fa: (password) => api.post('/auth/2fa/disable', { password }),
  complete2fa: (tempToken, code) => api.post('/auth/2fa/complete', { temp_token: tempToken, code }),
  getRecoveryCodes: () => api.get('/auth/2fa/recovery-codes'),
};
