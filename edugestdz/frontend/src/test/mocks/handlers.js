import { http, HttpResponse } from 'msw';

export const handlers = [
  http.get('/api/v1/auth/me', () => HttpResponse.json({
    success: true,
    data: { id: '1', nom: 'Test', prenom: 'User', email: 'test@test.dz', role: 'admin', langue: 'fr' },
    tenant: { id: '1', nom: 'Centre Test', statut: 'actif' },
  })),
  http.post('/api/v1/auth/login', () => HttpResponse.json({
    success: true,
    access_token: 'test-token',
    user: { id: '1', nom: 'Test', prenom: 'User', email: 'test@test.dz', role: 'admin', langue: 'fr' },
  })),
  http.get('/api/v1/eleves', () => HttpResponse.json({
    success: true,
    data: [
      { id: '1', nom: 'Eleve1', prenom: 'Test', niveau: '3AS', statut: 'actif' },
      { id: '2', nom: 'Eleve2', prenom: 'Test', niveau: '2AS', statut: 'actif' },
    ],
    meta: { total: 2, per_page: 20, current_page: 1 },
  })),
  http.get('/api/v1/groupes', () => HttpResponse.json({
    success: true,
    data: [{ id: '1', nom: 'Groupe A', niveau: '3AS', matiere: { nom: 'Maths' } }],
  })),
  http.get('/api/v1/planning', () => HttpResponse.json({ success: true, data: [] })),
];

export const errorHandler = http.get('*', () => HttpResponse.json(
  { success: false, error: { code: 'ERROR', message: 'Server error' } },
  { status: 500 },
));
