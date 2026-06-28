import React from 'react';
import { render, screen } from '@testing-library/react';
import DataTable from '@components/common/DataTable';

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
    render(<DataTable columns={columns} data={data} />);
    expect(screen.getByText('Nom')).toBeInTheDocument();
    expect(screen.getByText('Âge')).toBeInTheDocument();
  });

  it('renders rows', () => {
    render(<DataTable columns={columns} data={data} />);
    expect(screen.getByText('Alice')).toBeInTheDocument();
    expect(screen.getByText('Bob')).toBeInTheDocument();
  });

  it('shows loading state', () => {
    render(<DataTable columns={columns} data={[]} isLoading />);
    expect(screen.getByText('Chargement...')).toBeInTheDocument();
  });

  it('shows empty state message', () => {
    render(<DataTable columns={columns} data={[]} emptyMessage="Aucun élément" />);
    expect(screen.getByText('Aucun élément')).toBeInTheDocument();
  });

  it('shows default empty message when no data', () => {
    render(<DataTable columns={columns} data={[]} />);
    expect(screen.getByText('Aucune donnée')).toBeInTheDocument();
  });
});
