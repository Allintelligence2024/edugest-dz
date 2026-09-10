import React from 'react';
import { render, screen } from '@testing-library/react';
import DataTable from '@components/common/DataTable';
import { I18nProvider } from '@context/I18nContext';

const columns = [
  { key: 'nom', label: 'Nom' },
  { key: 'age', label: 'Âge' },
];

const data = [
  { id: 1, nom: 'Alice', age: 25 },
  { id: 2, nom: 'Bob', age: 30 },
];

describe('DataTable', () => {
  it('renders headers', () => {
    render(
      <I18nProvider>
        <DataTable columns={columns} data={data} />
      </I18nProvider>
    );
    expect(screen.getByText('Nom')).toBeInTheDocument();
    expect(screen.getByText('Âge')).toBeInTheDocument();
  });

  it('renders rows', () => {
    render(
      <I18nProvider>
        <DataTable columns={columns} data={data} />
      </I18nProvider>
    );
    expect(screen.getByText('Alice')).toBeInTheDocument();
    expect(screen.getByText('Bob')).toBeInTheDocument();
  });

  it('shows loading state', () => {
    render(
      <I18nProvider>
        <DataTable columns={columns} data={[]} isLoading />
      </I18nProvider>
    );
    expect(screen.getByText('Chargement...')).toBeInTheDocument();
  });

  it('shows empty state message', () => {
    render(
      <I18nProvider>
        <DataTable columns={columns} data={[]} emptyMessage="Aucun élément" />
      </I18nProvider>
    );
    expect(screen.getByText('Aucun élément')).toBeInTheDocument();
  });

  it('shows default empty message when no data', () => {
    render(
      <I18nProvider>
        <DataTable columns={columns} data={[]} />
      </I18nProvider>
    );
    expect(screen.getByText('Aucune donnée disponible')).toBeInTheDocument();
  });
});
