import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { I18nProvider } from '@context/I18nContext';
import LoginPage from '@pages/LoginPage';

const mockLogin = vi.fn();
const mockNavigate = vi.fn();

vi.mock('@hooks/useAuth', () => ({
  useAuth: () => ({ login: mockLogin }),
}));

vi.mock('@api/auth.api', () => ({
  authApi: {
    login: vi.fn(),
    complete2fa: vi.fn(),
  },
}));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return { ...actual, useNavigate: () => mockNavigate };
});

vi.mock('react-hot-toast', () => ({
  toast: { error: vi.fn(), success: vi.fn() },
}));

describe('LoginPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  const renderLogin = () =>
    render(
      <BrowserRouter>
        <I18nProvider>
          <LoginPage />
        </I18nProvider>
      </BrowserRouter>
    );

  it('renders login form', () => {
    renderLogin();
    expect(screen.getByText('EduGest DZ')).toBeInTheDocument();
    expect(screen.getByText('Se connecter')).toBeInTheDocument();
  });

  it('has email input', () => {
    renderLogin();
    expect(screen.getByText('Email')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('votre@email.com')).toBeInTheDocument();
  });

  it('has password input', () => {
    renderLogin();
    expect(screen.getByText('Mot de passe')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('••••••••')).toBeInTheDocument();
  });

  it('has submit button', () => {
    renderLogin();
    const submitBtn = screen.getByRole('button', { name: /Se connecter/ });
    expect(submitBtn).toBeInTheDocument();
  });

  it('shows error on empty submit', async () => {
    renderLogin();
    const submitBtn = screen.getByRole('button', { name: /Se connecter/ });
    await userEvent.click(submitBtn);
    const { toast } = await import('react-hot-toast');
    expect(toast.error).toHaveBeenCalledWith('Veuillez remplir tous les champs');
  });

  it('renders copyright notice', () => {
    renderLogin();
    expect(screen.getByText(/Tous droits réservés/)).toBeInTheDocument();
  });
});
