import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { I18nProvider } from '@context/I18nContext';
import GroupesPage from '@pages/GroupesPage';

const mockData = {
  data: {
    data: [
      { id: '1', nom: 'Groupe A', niveau: '3AS', matiere: { nom_fr: 'Maths', couleur: '#1E5EBC' }, statut: 'actif', eleves_count: 10, capacite_max: 20 },
    ],
    meta: { total: 1, last_page: 1, per_page: 15, current_page: 1 },
  },
  isLoading: false,
};

const mockLoad = vi.fn();
const mockChangePage = vi.fn();
const mockChangeFilter = vi.fn();
const mockSearch = vi.fn();
const mockReset = vi.fn();

vi.mock('@hooks/useApi', () => ({
  useList: () => ({
    items: mockData.data.data,
    data: mockData.data,
    isLoading: false,
    meta: mockData.data.meta,
    load: mockLoad,
    changePage: mockChangePage,
    changeFilter: mockChangeFilter,
    search: mockSearch,
    reset: mockReset,
  }),
}));

vi.mock('@api/axiosInstance', () => ({
  default: {
    get: vi.fn().mockResolvedValue({ data: [] }),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

describe('GroupesPage', () => {
  const renderGroupes = () =>
    render(
      <BrowserRouter>
        <I18nProvider>
          <GroupesPage />
        </I18nProvider>
      </BrowserRouter>
    );

  it('renders groupes list', () => {
    renderGroupes();
    expect(screen.getByText('👥 Groupes')).toBeInTheDocument();
    expect(screen.getByText('Groupe A')).toBeInTheDocument();
    expect(screen.getByText((content) => content.startsWith('Maths'))).toBeInTheDocument();
  });

  it('has add button', () => {
    renderGroupes();
    const addBtn = screen.getByText('➕ Nouveau groupe');
    expect(addBtn).toBeInTheDocument();
  });

  it('opens modal on add button click', async () => {
    renderGroupes();
    const addBtn = screen.getByText('➕ Nouveau groupe');
    await userEvent.click(addBtn);
    const modalTitles = screen.getAllByText('➕ Nouveau groupe');
    expect(modalTitles.length).toBeGreaterThanOrEqual(2);
  });

  it('renders search bar', () => {
    renderGroupes();
    expect(screen.getByPlaceholderText('Rechercher un groupe...')).toBeInTheDocument();
  });

  it('renders niveau filter', () => {
    renderGroupes();
    expect(screen.getByText('Niveau')).toBeInTheDocument();
    expect(screen.getByText('Statut')).toBeInTheDocument();
  });
});
