import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { I18nProvider } from '@context/I18nContext';
import BulletinsPage from '@pages/BulletinsPage';
import { bulletinApi } from '@api/bulletin.api';
import { groupeApi } from '@api/groupe.api';

vi.mock('@api/bulletin.api', () => ({
  bulletinApi: {
    list: vi.fn(),
    generer: vi.fn(),
    get: vi.fn(),
    pdf: vi.fn(),
    envoyer: vi.fn(),
  },
}));

vi.mock('@api/groupe.api', () => ({
  groupeApi: { list: vi.fn() },
}));

const bulletins = [
  {
    id: 'b1',
    eleve: { nom: 'BENZEMA', prenom: 'Karim' },
    moyenne_generale: 14.25,
    rang: 3,
    effectif_classe: 28,
    statut: 'publié',
  },
];

const renderPage = () =>
  render(
    <BrowserRouter>
      <I18nProvider>
        <BulletinsPage />
      </I18nProvider>
    </BrowserRouter>
  );

describe('BulletinsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    groupeApi.list.mockResolvedValue({ data: [{ id: 'g1', nom: 'Groupe 3AS-A' }] });
    bulletinApi.list.mockResolvedValue({ data: bulletins });
    bulletinApi.generer.mockResolvedValue({ message: 'Bulletins générés' });
    bulletinApi.envoyer.mockResolvedValue({});
  });

  it('charge la liste des groupes au montage', async () => {
    renderPage();
    await waitFor(() => expect(groupeApi.list).toHaveBeenCalledWith({ per_page: 100 }));
    expect(await screen.findByRole('option', { name: 'Groupe 3AS-A' })).toBeInTheDocument();
  });

  it('refuse de générer des bulletins sans groupe sélectionné', async () => {
    renderPage();
    // Générer sans périmètre produirait des bulletins pour tout
    // l'établissement : le bouton reste désactivé.
    expect(screen.getByRole('button', { name: 'Générer les bulletins' })).toBeDisabled();
  });

  it('charge les bulletins du groupe, du trimestre et de l’année choisis', async () => {
    renderPage();
    await waitFor(() => expect(groupeApi.list).toHaveBeenCalled());

    await userEvent.selectOptions(screen.getAllByRole('combobox')[0], 'g1');
    await userEvent.selectOptions(screen.getAllByRole('combobox')[1], 'T2');
    await userEvent.click(screen.getByRole('button', { name: 'Actualiser' }));

    await waitFor(() => expect(bulletinApi.list).toHaveBeenCalledWith({
      groupe_id: 'g1',
      trimestre: 'T2',
      annee_scolaire: '2025-2026',
    }));

    expect(await screen.findByText(/BENZEMA/)).toBeInTheDocument();
    expect(screen.getByText('14.25/20')).toBeInTheDocument();
    expect(screen.getByText('3/28')).toBeInTheDocument();
  });

  it('affiche un état vide quand aucun bulletin n’existe', async () => {
    renderPage();
    expect(screen.getByText('Aucun bulletin')).toBeInTheDocument();
  });

  it('envoie un bulletin puis rafraîchit la liste', async () => {
    renderPage();
    await waitFor(() => expect(groupeApi.list).toHaveBeenCalled());
    await userEvent.selectOptions(screen.getAllByRole('combobox')[0], 'g1');
    await userEvent.click(screen.getByRole('button', { name: 'Actualiser' }));
    await screen.findByText(/BENZEMA/);

    await userEvent.click(screen.getByRole('button', { name: 'Envoyer' }));

    await waitFor(() => expect(bulletinApi.envoyer).toHaveBeenCalledWith('b1'));
    expect(bulletinApi.list).toHaveBeenCalledTimes(2);
  });
});
