import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { I18nProvider } from '@context/I18nContext';
import NotesPage from '@pages/NotesPage';
import api from '@api/axiosInstance';

vi.mock('@api/axiosInstance', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

const notes = [
  {
    id: 'n1',
    eleve_id: 'e1',
    eleve: { id: 'e1', nom: 'BENZEMA', prenom: 'Karim', niveau_scolaire: '3AS' },
    note: 16,
    note_sur: 20,
    absent: false,
    matiere: { nom_fr: 'Mathématiques', nom: 'mathematiques' },
    type: 'devoir',
    date: '2026-03-10T00:00:00Z',
  },
  {
    id: 'n2',
    eleve_id: 'e2',
    eleve: { id: 'e2', nom: 'HAKIMI', prenom: 'Achraf', niveau_scolaire: '1AM' },
    note: null,
    note_sur: 20,
    absent: true,
    matiere: { nom_fr: 'Physique', nom: 'physique' },
    type: 'composition',
    date: '2026-03-11T00:00:00Z',
  },
];

const matieres = [
  { id: 'm1', nom_fr: 'Mathématiques' },
  { id: 'm2', nom_fr: 'Physique' },
];

const renderPage = () =>
  render(
    <BrowserRouter>
      <I18nProvider>
        <NotesPage />
      </I18nProvider>
    </BrowserRouter>
  );

describe('NotesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    api.get.mockImplementation((url) => {
      if (url === '/notes') return Promise.resolve({ data: notes, meta: { total: 2, last_page: 1 } });
      if (url === '/matieres') return Promise.resolve({ data: matieres });
      return Promise.resolve({ data: [] });
    });
  });

  it('affiche l’en-tête des notes', () => {
    renderPage();
    expect(screen.getByText('Notes & Évaluations')).toBeInTheDocument();
    expect(screen.getByText('Nouvelle note')).toBeInTheDocument();
  });

  it('charge les notes et les matières', async () => {
    renderPage();
    await waitFor(() => expect(screen.getByText(/BENZEMA/)).toBeInTheDocument());

    expect(api.get).toHaveBeenCalledWith('/notes', expect.objectContaining({ params: expect.any(Object) }));
    expect(api.get).toHaveBeenCalledWith('/matieres');
  });

  it('affiche la note sur son barème', async () => {
    renderPage();
    await waitFor(() => expect(screen.getByText('16/20')).toBeInTheDocument());
  });

  it('distingue une absence d’une note nulle', async () => {
    renderPage();
    await waitFor(() => expect(screen.getByText('ABS')).toBeInTheDocument());
    // Une absence ne doit jamais être rendue comme un zéro : la confusion
    // fausserait la moyenne lue par le parent.
    expect(screen.queryByText('0/20')).not.toBeInTheDocument();
  });

  it('propose le filtre par matière alimenté par l’API', async () => {
    renderPage();
    await waitFor(() => expect(screen.getByRole('option', { name: 'Mathématiques' })).toBeInTheDocument());
    expect(screen.getByRole('option', { name: 'Toutes les matières' })).toBeInTheDocument();
  });

  it('ouvre le formulaire de saisie d’une note', async () => {
    renderPage();
    await userEvent.click(screen.getByText('Nouvelle note'));
    // Le bouton d'entête porte déjà « Nouvelle note » : on vise le titre du
    // modal (h2) pour le distinguer du bouton.
    await waitFor(() => expect(screen.getByRole('heading', { level: 2, name: 'Nouvelle note' })).toBeInTheDocument());
  });
});
