import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { I18nProvider } from '@context/I18nContext';
import AbsencesPage from '@pages/AbsencesPage';
import api from '@api/axiosInstance';

vi.mock('@api/axiosInstance', () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

const reponse = {
  data: {
    data: [
      { id: 'a1', statut: 'absent', sms_parent_envoye: true, eleve: { nom: 'BENZEMA', prenom: 'Karim' } },
      { id: 'a2', statut: 'retard', sms_parent_envoye: false, eleve: { nom: 'HAKIMI', prenom: 'Achraf' } },
      { id: 'a3', statut: 'présent', sms_parent_envoye: false, eleve: { nom: 'MAHREZ', prenom: 'Riyad' } },
    ],
    meta: { stats: { absents: 1, retards: 1, presents: 1 } },
  },
};

const renderPage = () =>
  render(
    <BrowserRouter>
      <I18nProvider>
        <AbsencesPage />
      </I18nProvider>
    </BrowserRouter>
  );

describe('AbsencesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    api.get.mockResolvedValue(reponse);
  });

  it('interroge les absences du jour', async () => {
    renderPage();
    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/absences', {
      params: { date: new Date().toISOString().split('T')[0] },
    }));
  });

  it('affiche les compteurs renvoyés par l’API', async () => {
    renderPage();
    await waitFor(() => expect(screen.getByText('✅ Absences Journalières')).toBeInTheDocument());

    expect(screen.getByText('Élèves absents')).toBeInTheDocument();
    expect(screen.getByText('En retard')).toBeInTheDocument();
    expect(screen.getByText('Présents')).toBeInTheDocument();
    expect(screen.getByText('SMS envoyés')).toBeInTheDocument();
  });

  it('ne liste que les absents et les retards, jamais les présents', async () => {
    renderPage();
    await waitFor(() => expect(screen.getByText(/Absents & Retards du jour/)).toBeInTheDocument());

    expect(screen.getByText(/Absents & Retards du jour \(2\)/)).toBeInTheDocument();
    expect(screen.getByText(/BENZEMA/)).toBeInTheDocument();
    expect(screen.getByText(/HAKIMI/)).toBeInTheDocument();
    expect(screen.queryByText(/MAHREZ/)).not.toBeInTheDocument();
  });

  it('reste affichable quand l’API échoue', async () => {
    api.get.mockRejectedValue(new Error('réseau indisponible'));
    renderPage();

    // Une panne de l'API ne doit pas laisser un écran de chargement infini.
    await waitFor(() => expect(screen.getByText('✅ Absences Journalières')).toBeInTheDocument());
    expect(screen.getByText(/Absents & Retards du jour \(0\)/)).toBeInTheDocument();
  });
});
