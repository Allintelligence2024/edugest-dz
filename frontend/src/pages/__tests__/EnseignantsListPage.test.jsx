import React from 'react';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { I18nProvider } from '@context/I18nContext';
import EnseignantsListPage from '@pages/EnseignantsListPage';
import api from '@api/axiosInstance';

vi.mock('@api/axiosInstance', () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

const enseignants = [
  {
    id: 'p1',
    matricule: 'ENS-2026-001',
    nom: 'ZIDANE',
    prenom: 'Yacine',
    email: 'y.zidane@edugest.dz',
    telephone: '0555000001',
    type_contrat: 'CDI',
    statut: 'actif',
  },
  {
    id: 'p2',
    matricule: 'ENS-2026-002',
    nom: 'BELKACEM',
    prenom: 'Amina',
    email: 'a.belkacem@edugest.dz',
    telephone: '0555000002',
    type_contrat: 'vacataire',
    statut: 'inactif',
  },
];

const renderPage = () =>
  render(
    <BrowserRouter>
      <I18nProvider>
        <EnseignantsListPage />
      </I18nProvider>
    </BrowserRouter>
  );

describe('EnseignantsListPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    api.get.mockImplementation((url) => {
      if (url === '/enseignants') {
        return Promise.resolve({ data: enseignants, meta: { total: 2, last_page: 1 } });
      }
      return Promise.resolve({ data: [] });
    });
  });

  it('affiche l’en-tête et le bouton de création', () => {
    renderPage();
    expect(screen.getByText('Enseignants')).toBeInTheDocument();
  });

  it('charge la liste des enseignants', async () => {
    renderPage();
    await waitFor(() => expect(screen.getByText(/ZIDANE/)).toBeInTheDocument());
    expect(screen.getByText(/BELKACEM/)).toBeInTheDocument();
  });

  it('expose les filtres statut, contrat et wilaya', async () => {
    renderPage();
    expect(screen.getByRole('combobox', { name: 'Statut' })).toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: 'Contrat' })).toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: 'Wilaya' })).toBeInTheDocument();
  });

  it('transmet le filtre de contrat à l’API', async () => {
    renderPage();
    await waitFor(() => expect(api.get).toHaveBeenCalled());

    await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Contrat' }), 'CDI');

    await waitFor(() => {
      const contrats = api.get.mock.calls
        .filter(([url]) => url === '/enseignants')
        .map(([, config]) => config?.params?.type_contrat);
      expect(contrats).toContain('CDI');
    });
  });

  it('transmet la recherche saisie à l’API', async () => {
    renderPage();
    await waitFor(() => expect(api.get).toHaveBeenCalled());

    await userEvent.type(screen.getByPlaceholderText('Rechercher un enseignant...'), 'Zid');

    await waitFor(() => {
      const recherches = api.get.mock.calls
        .filter(([url]) => url === '/enseignants')
        .map(([, config]) => config?.params?.search);
      expect(recherches).toContain('Zid');
    });
  });

  it('affiche le matricule de chaque enseignant', async () => {
    renderPage();
    await waitFor(() => expect(screen.getByText('ENS-2026-001')).toBeInTheDocument());
    expect(screen.getByText('ENS-2026-002')).toBeInTheDocument();
  });
});
