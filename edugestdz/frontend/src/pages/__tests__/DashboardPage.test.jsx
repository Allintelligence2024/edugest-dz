import React from 'react';
import { render, screen, act } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { I18nProvider } from '@context/I18nContext';
import DashboardPage from '@pages/DashboardPage';

vi.mock('@hooks/useAuth', () => ({
  useAuth: () => ({ isDemoMode: true }),
}));

vi.mock('@api/axiosInstance', () => ({
  default: {
    get: vi.fn(),
  },
}));

vi.mock('@components/dashboard/StatCard', () => ({
  default: ({ label, value }) => <div data-testid="stat-card"><span>{label}</span><span>{value}</span></div>,
}));

describe('DashboardPage', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  const renderDashboard = () =>
    render(
      <BrowserRouter>
        <I18nProvider>
          <DashboardPage />
        </I18nProvider>
      </BrowserRouter>
    );

  it('shows loading state initially', () => {
    renderDashboard();
    expect(screen.queryByText('Tableau de bord')).not.toBeInTheDocument();
  });

  it('renders StatCards after data loads', () => {
    renderDashboard();
    act(() => { vi.advanceTimersByTime(400); });
    expect(screen.getByText('Élèves actifs')).toBeInTheDocument();
    expect(screen.getByText('Enseignants')).toBeInTheDocument();
    expect(screen.getByText('Groupes')).toBeInTheDocument();
    expect(screen.getByText('Séances cette semaine')).toBeInTheDocument();
  });

  it('shows demo mode badge', () => {
    renderDashboard();
    act(() => { vi.advanceTimersByTime(400); });
    expect(screen.getByText(/Mode démo/)).toBeInTheDocument();
  });

  it('renders seances aujourdhui section', () => {
    renderDashboard();
    act(() => { vi.advanceTimersByTime(400); });
    expect(screen.getByText((content) => content.includes('Séances aujourd'))).toBeInTheDocument();
    expect(screen.getByText('Mathématiques')).toBeInTheDocument();
  });

  it('renders paiements recents section', () => {
    renderDashboard();
    act(() => { vi.advanceTimersByTime(400); });
    expect(screen.getByText((content) => content.includes('Paiements récents'))).toBeInTheDocument();
    expect(screen.getByText('Amine Boudiaf')).toBeInTheDocument();
  });
});
