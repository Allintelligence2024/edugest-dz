import React from 'react'
import { render } from '@testing-library/react-native'
// L'écran « tableau de bord parent » vit dans DashboardScreen.js (le
// composant exporté s'appelle ParentDashboardScreen) — l'ancien import
// visait screens/parent/ParentDashboardScreen, fichier qui n'existe pas.
import ParentDashboardScreen from '../../../screens/parent/DashboardScreen'

jest.mock('../../../context/AuthContext', () => ({
  useAuth: () => ({
    user: { prenom: 'Amine', nom: 'Benali' },
    tenant: { nom: 'Lycée El Mokrani' },
    isAuthenticated: true,
    isLoading: false,
    logout: jest.fn(),
  }),
}))

// Les libellés reflètent src/lang/fr.js (clés réellement utilisées par
// l'écran : welcome, nextCourse, average, monthPresences, lastPayment).
jest.mock('../../../context/I18nContext', () => ({
  useI18n: () => ({
    t: (key) => {
      const labels = {
        welcome: 'Bienvenue',
        nextCourse: 'Prochain cours',
        average: 'Moyenne générale',
        monthPresences: 'Présences du mois',
        lastPayment: 'Dernier paiement',
      }
      return labels[key] || key
    },
    locale: 'fr',
  }),
}))

jest.mock('@react-navigation/native', () => ({
  ...jest.requireActual('@react-navigation/native'),
  useNavigation: () => ({ navigate: jest.fn() }),
}))

describe('ParentDashboardScreen', () => {
  it('affiche le message de bienvenue avec le nom de l’utilisateur', () => {
    const { getByText } = render(<ParentDashboardScreen />)
    expect(getByText(/Amine/)).toBeTruthy()
    expect(getByText(/Benali/)).toBeTruthy()
  })

  it('affiche le nom de l’établissement', () => {
    const { getByText } = render(<ParentDashboardScreen />)
    expect(getByText(/Lycée El Mokrani/)).toBeTruthy()
  })

  it('affiche les quatre indicateurs', () => {
    const { getByText } = render(<ParentDashboardScreen />)
    expect(getByText('Prochain cours')).toBeTruthy()
    expect(getByText('Moyenne générale')).toBeTruthy()
    expect(getByText('Présences du mois')).toBeTruthy()
    expect(getByText('Dernier paiement')).toBeTruthy()
  })
})
