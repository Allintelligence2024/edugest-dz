import React from 'react'
import { render, fireEvent, waitFor } from '@testing-library/react-native'
import { NavigationContainer } from '@react-navigation/native'
import LoginScreen from '../../../screens/auth/LoginScreen'

const mockLogin = jest.fn()
const mockNavigate = jest.fn()

jest.mock('../../../context/AuthContext', () => ({
  useAuth: () => ({
    login: mockLogin,
    isAuthenticated: false,
    isLoading: false,
    user: null,
  }),
}))

jest.mock('../../../context/I18nContext', () => ({
  useI18n: () => ({
    t: (key) => {
      const translations = {
        loginTitle: 'Connexion EduGest',
        emailLabel: 'Adresse email',
        passwordLabel: 'Mot de passe',
        loginButton: 'Se connecter',
        loginError: 'Email ou mot de passe incorrect',
        fieldRequired: 'Ce champ est obligatoire',
        sessionExpired: 'Session expirée',
      }
      return translations[key] || key
    },
    locale: 'fr',
  }),
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

function renderWithNavigation(component) {
  return render(
    <NavigationContainer>
      {component}
    </NavigationContainer>
  )
}

describe('LoginScreen', () => {
  beforeEach(() => {
    mockLogin.mockClear()
    mockNavigate.mockClear()
  })

  it('renders login form with all elements', () => {
    const { getByText, getByPlaceholderText } = renderWithNavigation(<LoginScreen />)

    expect(getByText('EduGest DZ')).toBeTruthy()
    expect(getByPlaceholderText('admin@edugestdz.local')).toBeTruthy()
    expect(getByPlaceholderText('••••••••')).toBeTruthy()
    expect(getByText('Se connecter')).toBeTruthy()
  })

  it('calls login with email and password on submit', async () => {
    mockLogin.mockResolvedValueOnce({ success: true })

    const { getByPlaceholderText, getByText } = renderWithNavigation(<LoginScreen />)

    fireEvent.changeText(getByPlaceholderText('admin@edugestdz.local'), 'admin@test.com')
    fireEvent.changeText(getByPlaceholderText('••••••••'), 'password123')
    fireEvent.press(getByText('Se connecter'))

    await waitFor(() => {
      expect(mockLogin).toHaveBeenCalledWith('admin@test.com', 'password123')
    })
  })

  it('shows error message on login failure', async () => {
    const Alert = require('react-native').Alert
    jest.spyOn(Alert, 'alert')
    mockLogin.mockRejectedValueOnce(new Error('Email ou mot de passe incorrect'))

    const { getByPlaceholderText, getByText } = renderWithNavigation(<LoginScreen />)

    fireEvent.changeText(getByPlaceholderText('admin@edugestdz.local'), 'admin@test.com')
    fireEvent.changeText(getByPlaceholderText('••••••••'), 'wrongpassword')
    fireEvent.press(getByText('Se connecter'))

    await waitFor(() => {
      expect(Alert.alert).toHaveBeenCalledWith('error', 'Email ou mot de passe incorrect')
    })
  })

  it('does not call login when fields are empty', () => {
    const { getByText } = renderWithNavigation(<LoginScreen />)

    fireEvent.press(getByText('Se connecter'))

    expect(mockLogin).not.toHaveBeenCalled()
  })

  it('shows validation error for empty email', async () => {
    const { getByPlaceholderText, getByText } = renderWithNavigation(<LoginScreen />)

    fireEvent.changeText(getByPlaceholderText('admin@edugestdz.local'), '')
    fireEvent.changeText(getByPlaceholderText('••••••••'), 'password123')
    fireEvent.press(getByText('Se connecter'))

    await waitFor(() => {
      expect(mockLogin).not.toHaveBeenCalled()
    })
  })
})
