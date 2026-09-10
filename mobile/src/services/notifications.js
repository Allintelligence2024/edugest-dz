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
      if (data?.screen && navigationRef?.current) {
        navigationRef.current.navigate(data.screen, data.params || {});
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
