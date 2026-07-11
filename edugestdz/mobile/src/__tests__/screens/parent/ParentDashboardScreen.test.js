import React from 'react'
import { render } from '@testing-library/react-native'
import ParentDashboardScreen from '../../../screens/parent/ParentDashboardScreen'

jest.mock('../../../context/AuthContext', () => ({
  useAuth: () => ({
    user: { prenom: 'Amine', nom: 'Benali' },
    tenant: { nom: 'Lycée El Mokrani' },
    isAuthenticated: true,
    isLoading: false,
    logout: jest.fn(),
  }),
}))

jest.mock('../../../context/I18nContext', () => ({
  useI18n: () => ({
    t: (key) => {
      const labels = {
        dashboardTitle: 'Tableau de bord',
        welcomeMessage: 'Bienvenue',
        myChildren: 'Mes enfants',
        recentGrades: 'Notes récentes',
        attendance: 'Assiduité',
        payments: 'Paiements',
        schedule: 'Emploi du temps',
        noData: 'Aucune donnée disponible',
        logout: 'Déconnexion',
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

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'Ionicons',
  MaterialIcons: 'MaterialIcons',
  FontAwesome5: 'FontAwesome5',
  MaterialCommunityIcons: 'MaterialCommunityIcons',
}))

jest.mock('react-native-vector-icons/Ionicons', () => 'Ionicons')
jest.mock('react-native-vector-icons/MaterialIcons', () => 'MaterialIcons')
jest.mock('react-native-vector-icons/FontAwesome5', () => 'FontAwesome5')
jest.mock('react-native-vector-icons/MaterialCommunityIcons', () => 'MaterialCommunityIcons')
jest.mock('react-native-vector-icons', () => ({
  default: 'Icon',
}))

describe('ParentDashboardScreen', () => {
  it('renders welcome message with user name', () => {
    const { getByText } = render(<ParentDashboardScreen />)
    expect(getByText(/Amine/)).toBeTruthy()
    expect(getByText(/Benali/)).toBeTruthy()
  })

  it('renders tenant name', () => {
    const { getByText } = render(<ParentDashboardScreen />)
    expect(getByText(/Lycée El Mokrani/)).toBeTruthy()
  })

  it('renders dashboard sections', () => {
    const { getByText } = render(<ParentDashboardScreen />)
    expect(getByText(/Mes enfants/)).toBeTruthy()
  })

  it('renders logout button', () => {
    const { getByText } = render(<ParentDashboardScreen />)
    expect(getByText('Déconnexion')).toBeTruthy()
  })

  it('renders attendance section', () => {
    const { getByText } = render(<ParentDashboardScreen />)
    expect(getByText(/Assiduité/)).toBeTruthy()
  })

  it('renders payments section', () => {
    const { getByText } = render(<ParentDashboardScreen />)
    expect(getByText(/Paiements/)).toBeTruthy()
  })

  it('renders schedule section', () => {
    const { getByText } = render(<ParentDashboardScreen />)
    expect(getByText(/Emploi du temps/)).toBeTruthy()
  })
})
