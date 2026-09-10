import React, { useRef } from 'react';
import { StatusBar, View, Text, StyleSheet } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { AuthProvider } from './src/context/AuthContext';
import { I18nProvider, useI18n } from './src/context/I18nContext';
import AppNavigator from './src/navigation/AppNavigator';
import { colors } from './src/theme/colors';
import { useNotificationHandler } from './src/services/notifications';
import { registerForPushNotifications } from './src/services/notifications';

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
          <AppContent />
        </AuthProvider>
      </I18nProvider>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.background },
});
