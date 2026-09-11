import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { I18nProvider } from '@context/I18nContext';
import DashboardPage from '@pages/DashboardPage';

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return { ...actual, useNavigate: () => mockNavigate };
});

const mockFetch = vi.fn();
global.fetch = mockFetch;

const mockDashboard = {
  success: true,
  data: {
    kpis: {
      total_eleves: 42,
      evolution_eleves: 5.2,
      ca_mois: 1500000,
      ca_mois_precedent: 1200000,
      evolution_ca_pct: 25.0,
      impayes_montant: 350000,
      impayes_nb: 12,
      impayes_critiques_nb: 3,
      taux_recouvrement: 85.5,
      seances_aujourd_hui: 8,
      absences_aujourd_hui: 2,
    },
    graphiques: {
      ca_six_mois: [
        { mois: 'Jan 25', valeur: 1200000, label: '01/2025' },
        { mois: 'Fév 25', valeur: 1350000, label: '02/2025' },
      ],
      top_matieres: [
        { matiere: 'Mathématiques', moyenne: 14.5 },
      ],
      assiduite: [
        { semaine: 'S1', debut: '06/01', taux_presence: 92.5, absences: 5 },
      ],
    },
    alertes: [
      {
        type: 'danger',
        icone: '💰',
        message: '3 facture(s) impayée(s) depuis plus de 30 jours',
        action: 'Voir les impayés',
        route: '/finance?filtre=retard',
      },
    ],
    periode: 'Juillet 2026',
  },
};

const mockEleves = {
  success: true,
  data: [
    { id: '1', nom: 'Benali', prenom: 'Mohamed', niveau_scolaire: '3AS', statut: 'actif' },
    { id: '2', nom: 'Ziani', prenom: 'Sarah', niveau_scolaire: '2AS', statut: 'actif' },
  ],
};

const mockEmptyDashboard = {
  success: true,
  data: {
    kpis: { total_eleves: 0 },
    graphiques: { ca_six_mois: [], top_matieres: [], assiduite: [] },
    alertes: [],
  },
};

const mockEmptyEleves = { success: true, data: [] };

function renderDashboard() {
  return render(
    <BrowserRouter>
      <I18nProvider>
        <DashboardPage />
      </I18nProvider>
    </BrowserRouter>
  );
}

describe('DashboardPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockFetch.mockImplementation(url => {
      if (url.includes('analytics/dashboard')) {
        return Promise.resolve({ json: () => Promise.resolve(mockDashboard) });
      }
      if (url.includes('eleves')) {
        return Promise.resolve({ json: () => Promise.resolve(mockEleves) });
      }
      return Promise.resolve({ json: () => Promise.resolve({}) });
    });
  });

  it('renders loading spinner initially', () => {
    renderDashboard();
    expect(screen.getByTestId('dashboard')).toBeInTheDocument();
  });

  it('renders KPI cards after data loads', async () => {
    renderDashboard();
    await waitFor(() => {
      expect(screen.queryByText('Tableau de bord')).toBeInTheDocument();
    });
    expect(screen.getByText('Élèves actifs')).toBeInTheDocument();
    expect(screen.getByText('CA ce mois')).toBeInTheDocument();
    expect(screen.getByText('Impayés')).toBeInTheDocument();
    expect(screen.getByText("Séances aujourd'hui")).toBeInTheDocument();
  });

  it('displays real student data from API', async () => {
    renderDashboard();
    await waitFor(() => {
      expect(screen.getByText(/Benali/)).toBeInTheDocument();
    });
    expect(screen.getByText(/Ziani/)).toBeInTheDocument();
    expect(screen.getByText('3AS')).toBeInTheDocument();
    expect(screen.getByText('2AS')).toBeInTheDocument();
  });

  it('displays alertes from API', async () => {
    renderDashboard();
    await waitFor(() => {
      expect(screen.getByText(/facture\(s\) impayée\(s\)/)).toBeInTheDocument();
    });
  });

  it('renders chart with real ca_six_mois data', async () => {
    renderDashboard();
    await waitFor(() => {
      expect(screen.getByText('Évolution CA — 6 derniers mois')).toBeInTheDocument();
    });
    expect(screen.getByText('+25% vs N-1')).toBeInTheDocument();
  });

  it('shows empty state when no students', async () => {
    mockFetch.mockImplementation(url => {
      if (url.includes('analytics/dashboard')) {
        return Promise.resolve({ json: () => Promise.resolve(mockEmptyDashboard) });
      }
      if (url.includes('eleves')) {
        return Promise.resolve({ json: () => Promise.resolve(mockEmptyEleves) });
      }
      return Promise.resolve({ json: () => Promise.resolve({}) });
    });

    renderDashboard();
    await waitFor(() => {
      expect(screen.getByText(/Aucune donnée disponible/)).toBeInTheDocument();
    });
  });

  it('handles fetch errors gracefully', async () => {
    mockFetch.mockRejectedValue(new Error('Network error'));
    renderDashboard();
    await waitFor(() => {
      expect(screen.queryByText('Tableau de bord')).toBeInTheDocument();
    });
  });

  it('quick action buttons navigate correctly', async () => {
    renderDashboard();
    await waitFor(() => {
      expect(screen.getByText('Nouvel élève')).toBeInTheDocument();
    });
    screen.getByText('Nouvel élève').click();
    expect(mockNavigate).toHaveBeenCalledWith('/eleves');
  });
});
