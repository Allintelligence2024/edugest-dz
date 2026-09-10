import React from 'react';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { I18nProvider } from '@context/I18nContext';
import FacturesPage from '@pages/FacturesPage';
import api from '@api/axiosInstance';

vi.mock('@api/axiosInstance', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

const factures = [
  {
    id: 'f1',
    numero_facture: 'FAC-2026-0001',
    eleve_nom: 'BENZEMA',
    eleve_prenom: 'Karim',
    date_emission: '2026-01-05T00:00:00Z',
    date_echeance: '2026-01-31T00:00:00Z',
    total_ttc: 15000,
    statut: 'émise',
  },
  {
    id: 'f2',
    numero_facture: 'FAC-2026-0002',
    eleve_nom: 'HAKIMI',
    eleve_prenom: 'Achraf',
    date_emission: '2026-01-06T00:00:00Z',
    date_echeance: '2026-02-01T00:00:00Z',
    total_ttc: 22000,
    statut: 'payée',
  },
];

const synthese = { montant_total: 37000, montant_paye: 22000, montant_impaye: 15000 };

const renderPage = () =>
  render(
    <BrowserRouter>
      <I18nProvider>
        <FacturesPage />
      </I18nProvider>
    </BrowserRouter>
  );

describe('FacturesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    api.get.mockImplementation((url) => {
      if (url === '/factures') return Promise.resolve({ data: factures, synthese });
      return Promise.resolve({ data: [] });
    });
  });

  it('affiche l’en-tête de facturation', () => {
    renderPage();
    expect(screen.getByText('💰 Facturation')).toBeInTheDocument();
    expect(screen.getByText('➕ Nouvelle facture')).toBeInTheDocument();
  });

  it('charge les factures et la synthèse financière', async () => {
    renderPage();
    await waitFor(() => expect(screen.getByText('FAC-2026-0001')).toBeInTheDocument());

    expect(api.get).toHaveBeenCalledWith('/factures', { params: { per_page: 50 } });
    expect(screen.getByText('FAC-2026-0002')).toBeInTheDocument();
    expect(screen.getByText('Total facturé')).toBeInTheDocument();
  });

  it('restitue les montants de la synthèse renvoyée par l’API', async () => {
    renderPage();
    await waitFor(() => expect(screen.getByText('FAC-2026-0001')).toBeInTheDocument());

    // Les montants sont recherchés dans leur carte : les mêmes valeurs
    // apparaissent aussi dans les lignes du tableau.
    const carte = (libelle) => screen.getByText(libelle).closest('div');

    expect(within(carte('Total facturé')).getByText(`${(37000).toLocaleString()} DA`)).toBeInTheDocument();
    expect(within(carte('Payé')).getByText(`${(22000).toLocaleString()} DA`)).toBeInTheDocument();
    expect(within(carte('Impayé')).getByText(`${(15000).toLocaleString()} DA`)).toBeInTheDocument();
  });

  it('désactive le bouton de paiement d’une facture déjà payée', async () => {
    renderPage();
    await waitFor(() => expect(screen.getByText('FAC-2026-0002')).toBeInTheDocument());

    const boutons = screen.getAllByRole('button', { name: 'Paiement' });
    expect(boutons).toHaveLength(2);
    expect(boutons[0]).not.toBeDisabled(); // facture émise
    expect(boutons[1]).toBeDisabled();     // facture payée
  });

  it('ouvre la saisie de paiement pour une facture impayée', async () => {
    renderPage();
    await waitFor(() => expect(screen.getByText('FAC-2026-0001')).toBeInTheDocument());

    await userEvent.click(screen.getAllByRole('button', { name: 'Paiement' })[0]);

    await waitFor(() => expect(screen.getByText('Enregistrer un paiement')).toBeInTheDocument());
  });

  it('affiche un état vide explicite quand aucune facture n’existe', async () => {
    api.get.mockResolvedValue({ data: [], synthese: { montant_total: 0, montant_paye: 0, montant_impaye: 0 } });
    renderPage();

    await waitFor(() => expect(screen.getByText('Aucune facture')).toBeInTheDocument());
  });
});
