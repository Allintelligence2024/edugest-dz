import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import FilterBar from '@components/common/FilterBar';

const filters = [
  { key: 'niveau', label: 'Niveau', type: 'select', options: [
    { value: '1AS', label: '1AS' },
    { value: '2AS', label: '2AS' },
    { value: '3AS', label: '3AS' },
  ]},
  { key: 'statut', label: 'Statut', type: 'select', options: [
    { value: 'actif', label: 'Actif' },
    { value: 'inactif', label: 'Inactif' },
  ]},
];

describe('FilterBar', () => {
  it('renders filter options', () => {
    render(<FilterBar filters={filters} values={{}} onChange={vi.fn()} />);
    expect(screen.getByText('Niveau')).toBeInTheDocument();
    expect(screen.getByText('Statut')).toBeInTheDocument();
  });

  it('calls onChange when a filter value is changed', async () => {
    const onChange = vi.fn();
    render(<FilterBar filters={filters} values={{}} onChange={onChange} />);
    const niveauSelect = screen.getAllByRole('combobox')[0];
    await userEvent.selectOptions(niveauSelect, '1AS');
    expect(onChange).toHaveBeenCalledWith({ niveau: '1AS' });
  });

  it('shows reset button when a filter has value', () => {
    render(<FilterBar filters={filters} values={{ niveau: '1AS' }} onChange={vi.fn()} />);
    expect(screen.getByText('🔄 Réinitialiser')).toBeInTheDocument();
  });

  it('calls onReset when reset button is clicked', async () => {
    const onReset = vi.fn();
    render(<FilterBar filters={filters} values={{ niveau: '1AS' }} onChange={vi.fn()} onReset={onReset} />);
    await userEvent.click(screen.getByText('🔄 Réinitialiser'));
    expect(onReset).toHaveBeenCalledOnce();
  });

  it('does not show reset when all values are empty', () => {
    render(<FilterBar filters={filters} values={{}} onChange={vi.fn()} />);
    expect(screen.queryByText('🔄 Réinitialiser')).not.toBeInTheDocument();
  });
});
