import React from 'react';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { I18nProvider } from '@context/I18nContext';
import ElevesListPage from '@pages/ElevesListPage';
import api from '@api/axiosInstance';

vi.mock('@api/axiosInstance', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

const eleves = [
  {
    id: 'e1',
    numero_inscription: 'INS-2026-001',
    nom: 'BENZEMA',
    prenom: 'Karim',
    niveau_scolaire: '3AS',
    statut: 'actif',
    parents_count: 2,
    created_at: '2026-01-15T10:00:00Z',
  },
  {
    id: 'e2',
    numero_inscription: 'INS-2026-002',
    nom: 'HAKIMI',
    prenom: 'Achraf',
    niveau_scolaire: '1AM',
    statut: 'suspendu',
    parents_count: 1,
    created_at: '2026-02-02T10:00:00Z',
  },
];

const renderPage = () =>
  render(
    <BrowserRouter>
      <I18nProvider>
        <ElevesListPage />
      </I18nProvider>
    </BrowserRouter>
  );

describe('ElevesListPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    api.get.mockImplementation((url) => {
      if (url === '/eleves') {
        return Promise.resolve({ data: eleves, meta: { total: 2, last_page: 1, per_page: 15, current_page: 1 } });
      }
      return Promise.resolve({ data: [] });
    });
  });

  it('affiche l’en-tête et le bouton de création', () => {
    renderPage();
    expect(screen.getByText('👨‍🎓 Élèves')).toBeInTheDocument();
    expect(screen.getByText('➕ Nouvel élève')).toBeInTheDocument();
  });

  it('charge la liste des élèves depuis l’API', async () => {
    renderPage();
    await waitFor(() => expect(screen.getByText(/BENZEMA/)).toBeInTheDocument());
    expect(screen.getByText(/HAKIMI/)).toBeInTheDocument();
    expect(api.get).toHaveBeenCalledWith('/eleves', expect.objectContaining({ params: expect.any(Object) }));
  });

  it('affiche le numéro d’inscription et le statut de chaque élève', async () => {
    renderPage();
    await waitFor(() => expect(screen.getByText('INS-2026-001')).toBeInTheDocument());
    expect(screen.getByText('INS-2026-002')).toBeInTheDocument();

    // Les statuts sont rendus en badge dans le tableau ; les mêmes mots
    // existent aussi comme options du filtre « Statut ».
    const lignes = screen.getAllByRole('row');
    expect(lignes.some((l) => within(l).queryByText('actif'))).toBe(true);
    expect(lignes.some((l) => within(l).queryByText('suspendu'))).toBe(true);
  });

  it('expose la recherche et les trois filtres déclarés', async () => {
    renderPage();
    expect(screen.getByPlaceholderText('Rechercher un élève...')).toBeInTheDocument();

    // Les filtres sont des <select> : ils étaient déclarés par la page mais
    // FilterBar n'en rendait aucun (il exigeait un `type` que personne ne
    // renseignait). On vérifie donc le contrôle lui-même, pas un libellé
    // qui existe aussi en en-tête de tableau.
    expect(screen.getByRole('combobox', { name: 'Niveau' })).toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: 'Statut' })).toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: 'Sexe' })).toBeInTheDocument();
  });

  it('transmet le filtre de niveau sélectionné à l’API', async () => {
    renderPage();
    await waitFor(() => expect(api.get).toHaveBeenCalled());

    await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Niveau' }), '3AS');

    await waitFor(() => {
      const niveaux = api.get.mock.calls
        .filter(([url]) => url === '/eleves')
        .map(([, config]) => config?.params?.niveau_scolaire);
      expect(niveaux).toContain('3AS');
    });
  });

  it('transmet la recherche saisie à l’API', async () => {
    renderPage();
    await waitFor(() => expect(api.get).toHaveBeenCalled());

    await userEvent.type(screen.getByPlaceholderText('Rechercher un élève...'), 'Benz');

    await waitFor(() => {
      const recherches = api.get.mock.calls
        .filter(([url]) => url === '/eleves')
        .map(([, config]) => config?.params?.search);
      expect(recherches).toContain('Benz');
    });
  });

  it('n’affiche jamais de jeton d’authentification dans le DOM', async () => {
    renderPage();
    await waitFor(() => expect(screen.getByText(/BENZEMA/)).toBeInTheDocument());
    expect(document.body.innerHTML).not.toMatch(/Bearer\s/i);
  });
});
