import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { I18nProvider } from '@context/I18nContext';
import MotDePasseOubliePage from '@pages/MotDePasseOubliePage';
import api from '@api/client';

vi.mock('@api/client', () => ({ default: vi.fn() }));

const renderPage = () =>
  render(
    <BrowserRouter>
      <I18nProvider>
        <MotDePasseOubliePage />
      </I18nProvider>
    </BrowserRouter>
  );

describe('MotDePasseOubliePage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    api.mockResolvedValue({});
  });

  it('affiche le formulaire de demande', () => {
    renderPage();
    expect(screen.getByText('Mot de passe oublié')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('votre@email.dz')).toBeInTheDocument();
  });

  it('appelle la route de réinitialisation avec l’adresse saisie', async () => {
    renderPage();

    await userEvent.type(screen.getByPlaceholderText('votre@email.dz'), 'directeur@ecole.dz');
    await userEvent.click(screen.getByRole('button', { name: /envoyer/i }));

    await waitFor(() => expect(api).toHaveBeenCalledWith('/auth/forgot-password', {
      method: 'POST',
      body: JSON.stringify({ email: 'directeur@ecole.dz' }),
    }));
  });

  it('confirme l’envoi sans révéler si le compte existe', async () => {
    renderPage();

    await userEvent.type(screen.getByPlaceholderText('votre@email.dz'), 'inconnu@ecole.dz');
    await userEvent.click(screen.getByRole('button', { name: /envoyer/i }));

    // Le message de confirmation ne doit rien dire de l'existence du compte :
    // ce serait un oracle d'énumération d'utilisateurs.
    expect(await screen.findByText('Email envoyé !')).toBeInTheDocument();
    expect(screen.getByText(/Si un compte existe avec cet email/)).toBeInTheDocument();
    expect(document.body.textContent).not.toMatch(/compte (inexistant|introuvable|inconnu)/i);
  });

  it('affiche l’erreur renvoyée par l’API sans bloquer la page', async () => {
    api.mockRejectedValue(new Error('Service indisponible'));
    renderPage();

    await userEvent.type(screen.getByPlaceholderText('votre@email.dz'), 'directeur@ecole.dz');
    await userEvent.click(screen.getByRole('button', { name: /envoyer/i }));

    // Le message est précédé d'un pictogramme dans le même bloc.
    expect(await screen.findByText(/Service indisponible/)).toBeInTheDocument();
    expect(screen.getByPlaceholderText('votre@email.dz')).toBeInTheDocument();
  });
});
