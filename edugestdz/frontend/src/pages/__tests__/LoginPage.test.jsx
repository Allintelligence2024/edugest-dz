import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { I18nProvider } from '@context/I18nContext';
import LoginPage from '@pages/LoginPage';

const mockLogin = vi.fn();

vi.mock('@context/AuthContext', () => ({
  useAuth: () => ({
    login: mockLogin,
    isAuthenticated: false,
    homeRoute: () => '/',
    sessionExpired: false,
  }),
}));

describe('LoginPage', () => {
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
    expect(screen.getByRole('heading', { name: /Connexion/ })).toBeInTheDocument();
    expect(screen.getByPlaceholderText('directeur@mon-ecole.dz')).toBeInTheDocument();
  });

  it('has email input', () => {
    renderLogin();
    expect(screen.getByText('Email ou identifiant')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('directeur@mon-ecole.dz')).toBeInTheDocument();
  });

  it('has password input', () => {
    renderLogin();
    expect(screen.getByText('Mot de passe')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('••••••••••••')).toBeInTheDocument();
  });

  it('has submit button', () => {
    renderLogin();
    const submitBtn = screen.getByRole('button', { name: /Se connecter/ });
    expect(submitBtn).toBeInTheDocument();
  });

  it('shows error on failed login', async () => {
    mockLogin.mockRejectedValueOnce(new Error('Email ou mot de passe incorrect'));
    renderLogin();
    const emailInput = screen.getByPlaceholderText('directeur@mon-ecole.dz');
    const passInput = screen.getByPlaceholderText('••••••••••••');
    const submitBtn = screen.getByRole('button', { name: /Se connecter/ });

    await userEvent.type(emailInput, 'test@test.com');
    await userEvent.type(passInput, 'wrongpassword');
    await userEvent.click(submitBtn);

    expect(await screen.findByText(/Email ou mot de passe incorrect/)).toBeInTheDocument();
  });
});
