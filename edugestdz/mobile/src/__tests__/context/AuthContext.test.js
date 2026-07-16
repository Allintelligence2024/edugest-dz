import React from 'react'
import { render, fireEvent, waitFor, act } from '@testing-library/react-native'
import { AuthProvider, useAuth } from '../../context/AuthContext'
import { Text, TouchableOpacity } from 'react-native'

const mockLogin = jest.fn()
const mockLogout = jest.fn()

jest.mock('../../api/endpoints', () => ({
  __esModule: true,
  authApi: { login: (...args) => mockLogin(...args), logout: (...args) => mockLogout(...args) },
}))

jest.mock('../../api/axios', () => ({
  __esModule: true,
  default: {
    defaults: { headers: { common: {} } },
    interceptors: {
      request: { use: jest.fn() },
      response: { use: jest.fn() },
    },
  },
  TOKEN_KEY: 'access_token',
  REFRESH_KEY: 'refresh_token',
}))

jest.mock('expo-secure-store', () => ({
  __esModule: true,
  setItemAsync: jest.fn(() => Promise.resolve()),
  getItemAsync: jest.fn(() => Promise.resolve(null)),
  deleteItemAsync: jest.fn(() => Promise.resolve()),
}))

function TestComponent() {
  const { user, isAuthenticated, isLoading, login, logout } = useAuth()
  const [error, setError] = React.useState(null)
  return (
    <>
      <Text testID="loading">{isLoading ? 'loading' : 'loaded'}</Text>
      <Text testID="auth">{isAuthenticated ? 'authenticated' : 'not-authenticated'}</Text>
      <Text testID="user">{user ? JSON.stringify(user) : 'no-user'}</Text>
      <Text testID="error">{error || 'no-error'}</Text>
      <TouchableOpacity
        testID="login-btn"
        onPress={() => login('admin@test.com', 'password123').catch(e => setError(e.message))}
      >
        Login
      </TouchableOpacity>
      <TouchableOpacity testID="logout-btn" onPress={() => logout()}>
        Logout
      </TouchableOpacity>
    </>
  )
}

describe('AuthContext', () => {
  beforeEach(() => {
  })

  it('provides initial unauthenticated state', async () => {
    const { getByTestId } = render(
      <AuthProvider>
        <TestComponent />
      </AuthProvider>
    )

    await waitFor(() => {
      expect(getByTestId('loading').children[0]).toBe('loaded')
    })
    expect(getByTestId('auth').children[0]).toBe('not-authenticated')
    expect(getByTestId('user').children[0]).toBe('no-user')
  })

  it('updates state after successful login', async () => {
    mockLogin.mockResolvedValueOnce({
      success: true,
      access_token: 'test-token',
      user: { id: 1, prenom: 'Test', nom: 'User', role: 'parent' },
      tenant: { id: 1, nom: 'Test School' },
    })

    const { getByTestId } = render(
      <AuthProvider>
        <TestComponent />
      </AuthProvider>
    )

    await waitFor(() => {
      expect(getByTestId('loading').children[0]).toBe('loaded')
    })

    await act(async () => {
      fireEvent.press(getByTestId('login-btn'))
    })

    await waitFor(() => {
      expect(getByTestId('auth').children[0]).toBe('authenticated')
    })

    const userText = getByTestId('user').children[0]
    expect(userText).toContain('"prenom":"Test"')
    expect(userText).toContain('"role":"parent"')
  })

  it('does not authenticate when login fails', async () => {
    mockLogin.mockRejectedValueOnce(new Error('Invalid credentials'))

    const { getByTestId } = render(
      <AuthProvider>
        <TestComponent />
      </AuthProvider>
    )

    await waitFor(() => {
      expect(getByTestId('loading').children[0]).toBe('loaded')
    })

    await act(async () => {
      fireEvent.press(getByTestId('login-btn'))
    })

    await waitFor(() => {
      expect(getByTestId('auth').children[0]).toBe('not-authenticated')
    })
  })

  it('resets state after logout', async () => {
    mockLogin.mockResolvedValueOnce({
      success: true,
      access_token: 'test-token',
      user: { id: 1, prenom: 'Test', nom: 'User', role: 'parent' },
      tenant: { id: 1, nom: 'Test School' },
    })

    const { getByTestId } = render(
      <AuthProvider>
        <TestComponent />
      </AuthProvider>
    )

    await waitFor(() => {
      expect(getByTestId('loading').children[0]).toBe('loaded')
    })

    await act(async () => {
      fireEvent.press(getByTestId('login-btn'))
    })

    await waitFor(() => {
      expect(getByTestId('auth').children[0]).toBe('authenticated')
    })

    await act(async () => {
      fireEvent.press(getByTestId('logout-btn'))
    })

    await waitFor(() => {
      expect(getByTestId('auth').children[0]).toBe('not-authenticated')
    })
  })
})
