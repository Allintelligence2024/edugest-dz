import React from 'react';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import ModuleProtectedRoute from '@components/ModuleProtectedRoute';

let mockIsActive;
let mockLoading;

vi.mock('@context/ModulesContext', () => ({
  useModules: () => ({
    isActive: mockIsActive,
    loading: mockLoading,
  }),
}));

const wrapper = ({ children }) => (
  <MemoryRouter>{children}</MemoryRouter>
);

describe('ModuleProtectedRoute', () => {
  beforeEach(() => {
    mockIsActive = vi.fn(() => true);
    mockLoading = false;
  });

  it('renders children when module is active', () => {
    render(
      <ModuleProtectedRoute moduleKey="marketplace">
        <div>Protected Content</div>
      </ModuleProtectedRoute>,
      { wrapper }
    );
    expect(screen.getByText('Protected Content')).toBeInTheDocument();
  });

  it('shows blocked message when module is inactive', () => {
    mockIsActive = vi.fn(() => false);
    render(
      <ModuleProtectedRoute moduleKey="marketplace">
        <div>Protected Content</div>
      </ModuleProtectedRoute>,
      { wrapper }
    );
    expect(screen.queryByText('Protected Content')).not.toBeInTheDocument();
    expect(screen.getByText('Module indisponible')).toBeInTheDocument();
    expect(screen.getByText(/marketplace/)).toBeInTheDocument();
  });

  it('shows loading spinner while modules are loading', () => {
    mockLoading = true;
    render(
      <ModuleProtectedRoute moduleKey="marketplace">
        <div>Protected Content</div>
      </ModuleProtectedRoute>,
      { wrapper }
    );
    expect(screen.queryByText('Protected Content')).not.toBeInTheDocument();
    expect(screen.queryByText('Module indisponible')).not.toBeInTheDocument();
  });

  it('calls isActive with the correct module key', () => {
    render(
      <ModuleProtectedRoute moduleKey="transport">
        <div>Content</div>
      </ModuleProtectedRoute>,
      { wrapper }
    );
    expect(mockIsActive).toHaveBeenCalledWith('transport');
  });
});
