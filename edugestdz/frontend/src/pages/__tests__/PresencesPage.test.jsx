import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { I18nProvider } from '@context/I18nContext';
import PresencesPage from '@pages/PresencesPage';
import api from '@api/axiosInstance';

vi.mock('@api/axiosInstance', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

const seances = [
  {
    id: 's1',
    matiere: 'Mathématiques',
    groupe: 'Groupe 3AS-A',
    heure_debut: '08:00:00',
    heure_fin: '09:30:00',
    inscriptions: [
      { id: 'i1', eleve_nom: 'BENZEMA', eleve_prenom: 'Karim' },
      { id: 'i2', eleve_nom: 'HAKIMI', eleve_prenom: 'Achraf' },
    ],
  },
];

const renderPage = () =>
  render(
    <BrowserRouter>
      <I18nProvider>
        <PresencesPage />
      </I18nProvider>
    </BrowserRouter>
  );

describe('PresencesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    api.get.mockImplementation((url) => {
      if (url === '/seances') return Promise.resolve({ data: seances });
      if (url === '/seances/s1/presences') return Promise.resolve({ data: [] });
      return Promise.resolve({ data: [] });
    });
    api.post.mockResolvedValue({ message: 'ok' });
  });

  it('affiche l’en-tête et invite à choisir une séance', async () => {
    renderPage();
    expect(screen.getByText('📋 Gestion des présences')).toBeInTheDocument();
    await waitFor(() => expect(screen.getByText('Sélectionnez une séance à gauche')).toBeInTheDocument());
  });

  it('charge les séances du jour', async () => {
    renderPage();
    await waitFor(() => expect(screen.getByText('Mathématiques')).toBeInTheDocument());

    expect(api.get).toHaveBeenCalledWith('/seances', expect.objectContaining({
      params: expect.objectContaining({ statut: 'planifiée' }),
    }));
    expect(screen.getByText('08:00 - 09:30')).toBeInTheDocument();
  });

  it('affiche la feuille d’appel après sélection d’une séance', async () => {
    renderPage();
    await waitFor(() => expect(screen.getByText('Mathématiques')).toBeInTheDocument());

    await userEvent.click(screen.getByText('Mathématiques'));

    await waitFor(() => expect(screen.getByText(/BENZEMA/)).toBeInTheDocument());
    expect(screen.getByText(/HAKIMI/)).toBeInTheDocument();
    expect(screen.getByText('💾 Enregistrer')).toBeInTheDocument();
  });

  it('enregistre les présences saisies', async () => {
    renderPage();
    await waitFor(() => expect(screen.getByText('Mathématiques')).toBeInTheDocument());
    await userEvent.click(screen.getByText('Mathématiques'));
    await waitFor(() => expect(screen.getByText('💾 Enregistrer')).toBeInTheDocument());

    await userEvent.click(screen.getByText('💾 Enregistrer'));

    await waitFor(() => expect(api.post).toHaveBeenCalledWith(
      '/seances/s1/presences',
      expect.objectContaining({ presences: expect.any(Object) })
    ));
  });

  it('signale l’absence de séance plutôt que d’afficher un tableau vide', async () => {
    api.get.mockResolvedValue({ data: [] });
    renderPage();

    await waitFor(() => expect(screen.getByText("Aucune séance aujourd'hui")).toBeInTheDocument());
  });
});
