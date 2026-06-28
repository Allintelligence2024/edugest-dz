import api from './axiosInstance';

export const messageApi = {
  conversations: (params) => api.get('/messages/conversations', { params }),
  creerConversation: (data) => api.post('/messages/conversations', data),
  conversation: (id) => api.get(`/messages/conversations/${id}`),
  envoyerMessage: (convId, data) => api.post(`/messages/conversations/${convId}`, data),
  marquerLu: (convId) => api.put(`/messages/conversations/${convId}/lu`),
};
