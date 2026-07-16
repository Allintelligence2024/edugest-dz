import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';
import NotesScreen from '../../src/screens/enseignant/NotesScreen';
import AsyncStorage from '@react-native-async-storage/async-storage';

jest.mock('@react-native-async-storage/async-storage', () =>
  require('@react-native-async-storage/async-storage/jest/async-storage-mock')
);

jest.mock('expo-notifications', () => ({
  setNotificationHandler: jest.fn(),
  getPermissionsAsync: jest.fn().mockResolvedValue({ status: 'granted' }),
  requestPermissionsAsync: jest.fn().mockResolvedValue({ status: 'granted' }),
  getExpoPushTokenAsync: jest.fn().mockResolvedValue({ data: 'ExponentPushToken[xxx]' }),
  setNotificationChannelAsync: jest.fn(),
  addNotificationResponseReceivedListener: jest.fn(),
  removeNotificationSubscription: jest.fn(),
}));

jest.mock('expo-device', () => ({
  isDevice: true,
}));

const mockFetch = jest.fn(() =>
  Promise.resolve({
    json: () => Promise.resolve({ success: true, data: [] }),
  })
);
global.fetch = mockFetch;

describe('NotesScreen', () => {
  beforeEach(() => {
    AsyncStorage.clear();
    mockFetch.mockClear();
  });

  it('renders without crashing', () => {
    const { getByText } = render(<NotesScreen />);
    expect(getByText('📝 Saisie de notes')).toBeTruthy();
  });

  it('shows group selection prompt', () => {
    const { getByText } = render(<NotesScreen />);
    expect(getByText('Sélectionnez un groupe :')).toBeTruthy();
  });

  it('loads groupes on mount', async () => {
    mockFetch.mockResolvedValueOnce({
      json: () => Promise.resolve({
        success: true,
        data: { data: [{ id: '1', nom: 'Groupe A', matiere: { nom_fr: 'Math' } }] },
      }),
    });

    const { getByText } = render(<NotesScreen />);
    await waitFor(() => {
      expect(getByText('Groupe A')).toBeTruthy();
    });
  });
});
