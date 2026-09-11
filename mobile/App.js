import React, { useRef } from 'react';
import { StatusBar, View, Text, StyleSheet } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import * as Sentry from 'sentry-expo';
import { AuthProvider } from './src/context/AuthContext';
import { EnfantProvider } from './src/context/EnfantContext';
import { I18nProvider, useI18n } from './src/context/I18nContext';
import AppNavigator from './src/navigation/AppNavigator';
import { colors } from './src/theme/colors';
import { useNotificationHandler, registerForPushNotifications } from './src/services/notifications';

// Sprint 6 § 4 — instrumentation Sentry de la version mobile.
//
// EXPO_PUBLIC_SENTRY_DSN est injecté au bundle à la compilation (Expo
// SDK 49+) : défini seulement sur les builds EAS de production. Sans DSN
// (développement local, Expo Go), on n'appelle même pas init — coût nul,
// aucun envoi. Les crashes natifs et erreurs JS sont remontés
// automatiquement une fois la DSN configurée dans les env EAS.
if (process.env.EXPO_PUBLIC_SENTRY_DSN) {
  try {
    Sentry.init({
      dsn: process.env.EXPO_PUBLIC_SENTRY_DSN,
      enableInExpoDevelopment: false,
      debug: false,
    });
  } catch (e) {
    // L'observabilité ne doit jamais empêcher l'app de démarrer.
    console.warn('Sentry: initialisation ignorée', e?.message);
  }
}

function AppContent() {
  const navigationRef = useRef();
  const { isRTL } = useI18n();
  useNotificationHandler(navigationRef);

  React.useEffect(() => {
    registerForPushNotifications();
  }, []);

  return (
    <NavigationContainer ref={navigationRef}>
      <StatusBar barStyle="light-content" backgroundColor={colors.primary} />
      <View style={[styles.root, { direction: isRTL ? 'rtl' : 'ltr' }]}>
        <AppNavigator />
      </View>
    </NavigationContainer>
  );
}

export default function App() {
  return (
    <SafeAreaProvider>
      <I18nProvider>
        <AuthProvider>
          <EnfantProvider>
            <AppContent />
          </EnfantProvider>
        </AuthProvider>
      </I18nProvider>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.background },
});
