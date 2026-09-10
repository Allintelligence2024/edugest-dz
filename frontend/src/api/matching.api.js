import api from './axiosInstance';

export const matchingApi = {
  suggestions: (eleveId, params = {}) =>
    api.get('/matching/suggestions', { params: { eleve_id: eleveId, ...params } }),
};
