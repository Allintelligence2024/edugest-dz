import React, { useEffect, useRef } from 'react';
import { Platform } from 'react-native';
import * as Notifications from 'expo-notifications';
import * as Device from 'expo-device';
import api from '../api/axios';
import { storePushToken, getPushToken } from './storage';

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge: true,
  }),
});

const SCREEN_MAP = {
  absences:    'Absences',
  notes:       'Notes',
  factures:    'Paiements',
  planning:    'Planning',
  messages:    'Messages',
  presences:   'Presences',
  bulletins:   'Bulletins',
  notifications: 'Notifications',
  marketplace: 'Marketplace',
  profil:      'Profile',
};

function resolveScreen(screenKey) {
  if (!screenKey) return { screen: 'Dashboard', params: {} };
  const mapped = SCREEN_MAP[screenKey.toLowerCase()] || screenKey;
  return { screen: mapped, params: {} };
}

export async function registerForPushNotifications() {
  if (!Device.isDevice) {
    console.warn('Push notifications require a physical device');
    return null;
  }

  const { status: existingStatus } = await Notifications.getPermissionsAsync();
  let finalStatus = existingStatus;
  if (existingStatus !== 'granted') {
    const { status } = await Notifications.requestPermissionsAsync();
    finalStatus = status;
  }
  if (finalStatus !== 'granted') {
    console.warn('Push notification permission denied');
    return null;
  }

  const tokenData = await Notifications.getExpoPushTokenAsync();
  const token = tokenData.data;
  await storePushToken(token);

  try {
    await api.post('/device-tokens', {
      token,
      platform: Platform.OS,
    });
  } catch (err) {
    console.warn('Failed to register push token on server:', err.message);
  }

  if (Platform.OS === 'android') {
    await Notifications.setNotificationChannelAsync('default', {
      name: 'default',
      importance: Notifications.AndroidImportance.MAX,
    });
  }

  return token;
}

export function useNotificationHandler(navigationRef) {
  const responseListener = useRef();

  useEffect(() => {
    const handleNotification = async (response) => {
      const data = response.notification?.request?.content?.data;
      if (!navigationRef?.current) return;

      try {
        const { screen, params } = resolveScreen(data?.screen);
        navigationRef.current.navigate(screen, { ...params, ...(data?.params || {}) });
      } catch {
        try {
          navigationRef.current.navigate('Dashboard');
        } catch {
          console.warn('Navigation fallback failed');
        }
      }
    };

    responseListener.current = Notifications.addNotificationResponseReceivedListener(handleNotification);
    return () => {
      if (responseListener.current) {
        Notifications.removeNotificationSubscription(responseListener.current);
      }
    };
  }, [navigationRef]);
}
