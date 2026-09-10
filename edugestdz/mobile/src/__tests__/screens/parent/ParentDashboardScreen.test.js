import React from 'react'
import { render } from '@testing-library/react-native'
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

jest.mock('../../../context/I18nContext', () => ({
  useI18n: () => ({
    t: (key) => {
      const labels = {
        welcome: 'Bienvenue',
        nextCourse: 'Prochain cours',
        average: 'Moyenne',
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

  it('renders stat cards', () => {
    const { getByText } = render(<ParentDashboardScreen />)
    expect(getByText('Prochain cours')).toBeTruthy()
    expect(getByText('Moyenne')).toBeTruthy()
    expect(getByText('Présences du mois')).toBeTruthy()
    expect(getByText('Dernier paiement')).toBeTruthy()
  })
})
