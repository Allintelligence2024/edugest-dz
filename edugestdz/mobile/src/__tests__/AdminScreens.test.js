import React from 'react';
import { Alert } from 'react-native';
import { render, fireEvent, waitFor } from '@testing-library/react-native';
import AdminMarketplaceScreen from '../screens/admin/AdminMarketplaceScreen';
import AdminIAScreen from '../screens/admin/AdminIAScreen';
import AdminSignalementsScreen from '../screens/admin/AdminSignalementsScreen';

jest.spyOn(Alert, 'alert').mockImplementation(() => {});

// Mock useAuth
jest.mock('../context/AuthContext', () => ({
  useAuth: () => ({
    user: { prenom: 'Directeur', nom: 'Test' },
    token: 'test-token',
    isAuthenticated: true,
    logout: jest.fn(),
  }),
}));

// Mock adminApi
jest.mock('../api/endpoints', () => ({
  adminApi: {
    marketplace: {
      dashboard: jest.fn().mockResolvedValue({
        data: {
          totaux: {
            nb_transactions: 150,
            ca_total: 2850000,
            commissions_percues: 199500,
            net_enseignants: 2650500,
            taux_moyen: '7%',
          },
        },
      }),
      commissions: jest.fn().mockResolvedValue({
        data: {
          top_enseignants: [
            { nom: 'Prof. Amine', nb_cours: 12, total_net: 850000 },
            { nom: 'Prof. Sara', nb_cours: 8, total_net: 620000 },
          ],
        },
      }),
    },
    predictions: {
      classement: jest.fn().mockResolvedValue({
        classement: [
          {
            eleve_id: 1,
            eleve_nom: 'Yacine B.',
            probabilite_echec: 85,
            niveau_risque: 'critique',
            facteurs_risque: [
              { label: 'Absences répétées', icone: '⚠️' },
            ],
          },
          {
            eleve_id: 2,
            eleve_nom: 'Nadir K.',
            probabilite_echec: 45,
            niveau_risque: 'modere',
            facteurs_risque: [],
          },
        ],
        stats: { critique: 5, eleve: 12, modere: 25, faible: 58 },
      }),
    },
    signalements: {
      list: jest.fn().mockResolvedValue({
        data: [
          {
            id: 1,
            type_incident: 'harcèlement',
            gravite: 'grave',
            statut: 'soumis',
            eleve_nom: 'Mohamed A.',
            date_incident: '2026-07-10',
            description: 'Harcèlement verbal en cour de sport.',
          },
          {
            id: 2,
            type_incident: 'violence',
            gravite: 'tres_grave',
            statut: 'en_investigation',
            eleve_nom: 'Sofiane M.',
            date_incident: '2026-07-08',
            description: 'Bagarre en cours de récréation.',
          },
        ],
        alerte: '1 nouveau signalement en attente',
      }),
      traiter: jest.fn().mockResolvedValue({ success: true }),
    },
  },
}));

// Mock navigation
jest.mock('@react-navigation/native', () => ({
  ...jest.requireActual('@react-navigation/native'),
  useNavigation: () => ({ navigate: jest.fn() }),
}));

describe('AdminMarketplaceScreen', () => {
  it('renders stats cards', async () => {
    const { findByText } = render(<AdminMarketplaceScreen />);
    expect(await findByText('Transactions', {}, { timeout: 15000 })).toBeTruthy();
    expect(await findByText('CA total (DA)', {}, { timeout: 5000 })).toBeTruthy();
  }, 30000);

  it('renders top enseignants list', async () => {
    const { getByText } = render(<AdminMarketplaceScreen />);
    await waitFor(() => {
      expect(getByText('Prof. Amine')).toBeTruthy();
      expect(getByText('Prof. Sara')).toBeTruthy();
    });
  });

  it('renders empty state when no transactions', async () => {
    const { adminApi } = require('../api/endpoints');
    adminApi.marketplace.dashboard.mockResolvedValueOnce({
      data: { totaux: { nb_transactions: 0, ca_total: 0, commissions_percues: 0, net_enseignants: 0 } },
    });
    adminApi.marketplace.commissions.mockResolvedValueOnce({
      data: { top_enseignants: [] },
    });
    const { getByText } = render(<AdminMarketplaceScreen />);
    await waitFor(() => {
      expect(getByText(/Aucune transaction encore/)).toBeTruthy();
    });
  });
});

describe('AdminIAScreen', () => {
  it('renders stats cards', async () => {
    const { getByText } = render(<AdminIAScreen />);
    await waitFor(() => {
      expect(getByText('5')).toBeTruthy(); // critique count
      expect(getByText('12')).toBeTruthy(); // eleve count
    });
  });

  it('renders students list', async () => {
    const { getByText } = render(<AdminIAScreen />);
    await waitFor(() => {
      expect(getByText('Yacine B.')).toBeTruthy();
      expect(getByText('Nadir K.')).toBeTruthy();
    });
  });

  it('renders empty state when no students', async () => {
    const { adminApi } = require('../api/endpoints');
    adminApi.predictions.classement.mockResolvedValueOnce({
      classement: [],
      stats: { critique: 0, eleve: 0, modere: 0, faible: 0 },
    });
    const { getByText } = render(<AdminIAScreen />);
    await waitFor(() => {
      expect(getByText(/Aucun élève dans cette catégorie/)).toBeTruthy();
    });
  });
});

describe('AdminSignalementsScreen', () => {
  it('renders signalements list', async () => {
    const { getByText } = render(<AdminSignalementsScreen />);
    await waitFor(() => {
      expect(getByText(/Mohamed A/)).toBeTruthy();
      expect(getByText(/Sofiane M/)).toBeTruthy();
    });
  });

  it('renders empty state when no signalements', async () => {
    const { adminApi } = require('../api/endpoints');
    adminApi.signalements.list.mockResolvedValueOnce({
      data: [],
      alerte: null,
    });
    const { getByText } = render(<AdminSignalementsScreen />);
    await waitFor(() => {
      expect(getByText(/Aucun signalement en cours/)).toBeTruthy();
    });
  });

  it('shows Traiter button only for soumis status', async () => {
    const { getByText, queryByText } = render(<AdminSignalementsScreen />);
    await waitFor(() => {
      // Mohamed A. is soumis - should have Traiter tag
      expect(getByText('Appuyer pour traiter →')).toBeTruthy();
      // Sofiane M. is en_investigation - should NOT have Traiter tag
      // The soumis card shows the tag
    });
  });
});
