import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { useI18n } from '../context/I18nContext';
import { colors } from '../theme/colors';
import LoginScreen from '../screens/auth/LoginScreen';
import ParentDashboard from '../screens/parent/DashboardScreen';
import ParentPlanning from '../screens/parent/PlanningScreen';
import ParentNotes from '../screens/parent/NotesScreen';
import ParentPresences from '../screens/parent/PresencesScreen';
import ParentPaiements from '../screens/parent/PaiementsScreen';
import ParentMessages from '../screens/parent/MessagesScreen';
import ParentBulletins from '../screens/parent/BulletinsScreen';
import ParentProfile from '../screens/parent/ProfileScreen';
import EnseignantDashboard from '../screens/enseignant/DashboardScreen';

const AuthStack = createNativeStackNavigator();
const ParentTab = createBottomTabNavigator();
const EnseignantStack = createNativeStackNavigator();
const RootStack = createNativeStackNavigator();

function getTabIcon(routeName, focused) {
  const icons = {
    Dashboard: focused ? '🏠' : '🏡',
    Planning: focused ? '📅' : '📆',
    Notes: focused ? '📝' : '📄',
    Presences: focused ? '✅' : '☑️',
    Paiements: focused ? '💰' : '💳',
    Messages: focused ? '💬' : '🗨️',
    Bulletins: focused ? '📊' : '📋',
    Profile: focused ? '👤' : '👥',
  };
  return icons[routeName] || '📌';
}

function ParentTabs() {
  const { isRTL } = useI18n();
  return (
    <ParentTab.Navigator
      screenOptions={({ route }) => ({
        tabBarIcon: ({ focused }) => {
          const React = require('react');
          const { Text } = require('react-native');
          return <Text style={{ fontSize: 20 }}>{getTabIcon(route.name, focused)}</Text>;
        },
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.textLight,
        headerStyle: { backgroundColor: colors.primary },
        headerTintColor: colors.white,
        tabBarStyle: { direction: isRTL ? 'rtl' : 'ltr' },
      })}
    >
      <ParentTab.Screen name="Dashboard" component={ParentDashboard} options={{ tabBarLabel: 'Accueil' }} />
      <ParentTab.Screen name="Planning" component={ParentPlanning} options={{ tabBarLabel: 'Planning' }} />
      <ParentTab.Screen name="Notes" component={ParentNotes} options={{ tabBarLabel: 'Notes' }} />
      <ParentTab.Screen name="Presences" component={ParentPresences} options={{ tabBarLabel: 'Présences' }} />
      <ParentTab.Screen name="Paiements" component={ParentPaiements} options={{ tabBarLabel: 'Paiements' }} />
      <ParentTab.Screen name="Messages" component={ParentMessages} options={{ tabBarLabel: 'Messages' }} />
      <ParentTab.Screen name="Profile" component={ParentProfile} options={{ tabBarLabel: 'Profil' }} />
    </ParentTab.Navigator>
  );
}

function AuthNavigator() {
  return (
    <AuthStack.Navigator screenOptions={{ headerShown: false }}>
      <AuthStack.Screen name="Login" component={LoginScreen} />
    </AuthStack.Navigator>
  );
}

function EnseignantNavigator() {
  return (
    <EnseignantStack.Navigator>
      <EnseignantStack.Screen name="Dashboard" component={EnseignantDashboard} />
    </EnseignantStack.Navigator>
  );
}

export default function AppNavigator() {
  const { isAuthenticated, isLoading, user } = require('../context/AuthContext').useAuth();
  const { View, ActivityIndicator } = require('react-native');

  if (isLoading) {
    return <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.background }}>
      <ActivityIndicator size="large" color={colors.primary} />
    </View>;
  }

  return (
    <RootStack.Navigator screenOptions={{ headerShown: false }}>
      {!isAuthenticated ? (
        <RootStack.Screen name="Auth" component={AuthNavigator} />
      ) : user?.role === 'enseignant' ? (
        <RootStack.Screen name="Enseignant" component={EnseignantNavigator} />
      ) : (
        <RootStack.Screen name="Parent" component={ParentTabs} />
      )}
    </RootStack.Navigator>
  );
}
