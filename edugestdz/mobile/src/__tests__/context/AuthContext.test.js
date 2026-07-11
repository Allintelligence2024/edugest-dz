import React from 'react'
import { render, fireEvent, waitFor, act } from '@testing-library/react-native'
import { AuthProvider, useAuth } from '../../../context/AuthContext'
import { Text, TouchableOpacity } from 'react-native'

jest.mock('../../../api/endpoints', () => ({
  authApi: {
    login: jest.fn(),
    logout: jest.fn(),
  },
}))

jest.mock('../../../services/storage', () => ({
  getItem: jest.fn(() => Promise.resolve(null)),
  setItem: jest.fn(() => Promise.resolve()),
  removeItem: jest.fn(() => Promise.resolve()),
}))

jest.mock('expo-secure-store', () => ({
  setItemAsync: jest.fn(() => Promise.resolve()),
  getItemAsync: jest.fn(() => Promise.resolve(null)),
  deleteItemAsync: jest.fn(() => Promise.resolve()),
}))

function TestComponent() {
  const { user, isAuthenticated, isLoading, login, logout } = useAuth()
  return (
    <>
      <Text testID="loading">{isLoading ? 'loading' : 'loaded'}</Text>
      <Text testID="auth">{isAuthenticated ? 'authenticated' : 'not-authenticated'}</Text>
      <Text testID="user">{user ? JSON.stringify(user) : 'no-user'}</Text>
      <TouchableOpacity
        testID="login-btn"
        onPress={() => login('admin@test.com', 'password123')}
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
    jest.clearAllMocks()
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
    const { authApi } = require('../../../api/endpoints')
    authApi.login.mockResolvedValueOnce({
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

  it('throws error when login fails', async () => {
    const { authApi } = require('../../../api/endpoints')
    authApi.login.mockRejectedValueOnce(new Error('Invalid credentials'))

    const { getByTestId } = render(
      <AuthProvider>
        <TestComponent />
      </AuthProvider>
    )

    await waitFor(() => {
      expect(getByTestId('loading').children[0]).toBe('loaded')
    })

    await expect(
      act(async () => {
        fireEvent.press(getByTestId('login-btn'))
      })
    ).rejects.toThrow('Invalid credentials')
  })

  it('resets state after logout', async () => {
    const { authApi } = require('../../../api/endpoints')
    authApi.login.mockResolvedValueOnce({
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
