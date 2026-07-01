import React from 'react';
import { View, ActivityIndicator, Text } from 'react-native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { useAuth } from '../context/AuthContext';
import { useI18n } from '../context/I18nContext';
import { colors } from '../theme/colors';

// Auth
import LoginScreen from '../screens/auth/LoginScreen';

// Parent (existants)
import ParentDashboard  from '../screens/parent/DashboardScreen';
import ParentPlanning   from '../screens/parent/PlanningScreen';
import ParentNotes      from '../screens/parent/NotesScreen';
import ParentPresences  from '../screens/parent/PresencesScreen';
import ParentPaiements  from '../screens/parent/PaiementsScreen';
import ParentMessages   from '../screens/parent/MessagesScreen';
import ParentBulletins  from '../screens/parent/BulletinsScreen';
import ParentProfile    from '../screens/parent/ProfileScreen';

// Enseignant (nouveaux)
import EnseignantDashboard  from '../screens/enseignant/DashboardScreen';
import EnseignantPlanning   from '../screens/enseignant/PlanningScreen';
import EnseignantPresences  from '../screens/enseignant/PresencesScreen';
import EnseignantNotes      from '../screens/enseignant/NotesScreen';

// Admin (nouveaux)
import AdminDashboard   from '../screens/admin/DashboardScreen';
import AdminEleves      from '../screens/admin/ElevesScreen';
import AdminAbsences    from '../screens/admin/AbsencesScreen';

const AuthStack      = createNativeStackNavigator();
const ParentTab      = createBottomTabNavigator();
const EnseignantTab  = createBottomTabNavigator();
const AdminTab       = createBottomTabNavigator();
const EnseignantStack= createNativeStackNavigator();

function tabIcon(name, focused) {
  const map = {
    Dashboard:  focused ? '🏠' : '🏡',
    Planning:   focused ? '📅' : '📆',
    Notes:      focused ? '📝' : '📄',
    Presences:  focused ? '✅' : '☑️',
    Paiements:  focused ? '💰' : '💳',
    Messages:   focused ? '💬' : '🗨️',
    Bulletins:  focused ? '📊' : '📋',
    Profile:    focused ? '👤' : '👥',
    MesGroupes: focused ? '📚' : '📖',
    Eleves:     focused ? '👦' : '👤',
    Absences:   focused ? '✅' : '☑️',
    Finance:    focused ? '💰' : '💳',
  };
  return map[name] || '📌';
}

const tabScreenOptions = (route) => ({
  tabBarIcon: ({ focused }) => <Text style={{ fontSize: 20 }}>{tabIcon(route.name, focused)}</Text>,
  tabBarActiveTintColor: colors.primary,
  tabBarInactiveTintColor: '#94a3b8',
  headerStyle: { backgroundColor: colors.primary },
  headerTintColor: '#fff',
});

// ── Parent Tabs ──
function ParentTabs() {
  const { isRTL } = useI18n();
  return (
    <ParentTab.Navigator screenOptions={({ route }) => tabScreenOptions(route)}>
      <ParentTab.Screen name="Dashboard" component={ParentDashboard}  options={{ title: 'Accueil' }} />
      <ParentTab.Screen name="Planning"  component={ParentPlanning}   options={{ title: 'Planning' }} />
      <ParentTab.Screen name="Notes"     component={ParentNotes}      options={{ title: 'Notes' }} />
      <ParentTab.Screen name="Presences" component={ParentPresences}  options={{ title: 'Présences' }} />
      <ParentTab.Screen name="Paiements" component={ParentPaiements}  options={{ title: 'Paiements' }} />
      <ParentTab.Screen name="Messages"  component={ParentMessages}   options={{ title: 'Messages' }} />
      <ParentTab.Screen name="Bulletins" component={ParentBulletins}  options={{ title: 'Bulletins' }} />
      <ParentTab.Screen name="Profile"   component={ParentProfile}    options={{ title: 'Profil' }} />
    </ParentTab.Navigator>
  );
}

// ── Enseignant Tabs + Stack (pour Presences qui a des params) ──
function EnseignantTabs() {
  return (
    <EnseignantTab.Navigator screenOptions={({ route }) => tabScreenOptions(route)}>
      <EnseignantTab.Screen name="Dashboard" component={EnseignantDashboard} options={{ title: 'Accueil' }} />
      <EnseignantTab.Screen name="Planning"  component={EnseignantPlanning}  options={{ title: 'Planning' }} />
      <EnseignantTab.Screen name="Notes"     component={EnseignantNotes}     options={{ title: 'Notes' }} />
    </EnseignantTab.Navigator>
  );
}

function EnseignantNavigator() {
  return (
    <EnseignantStack.Navigator screenOptions={{ headerStyle: { backgroundColor: colors.primary }, headerTintColor: '#fff' }}>
      <EnseignantStack.Screen name="EnseignantTabs" component={EnseignantTabs} options={{ headerShown: false }} />
      <EnseignantStack.Screen name="Presences"      component={EnseignantPresences} options={{ title: 'Présences' }} />
    </EnseignantStack.Navigator>
  );
}

// ── Admin Tabs ──
function AdminTabs() {
  return (
    <AdminTab.Navigator screenOptions={({ route }) => tabScreenOptions(route)}>
      <AdminTab.Screen name="Dashboard" component={AdminDashboard}  options={{ title: 'Tableau de bord' }} />
      <AdminTab.Screen name="Eleves"    component={AdminEleves}     options={{ title: 'Élèves' }} />
      <AdminTab.Screen name="Absences"  component={AdminAbsences}   options={{ title: 'Absences' }} />
    </AdminTab.Navigator>
  );
}

// ── Auth ──
function AuthNavigator() {
  return (
    <AuthStack.Navigator screenOptions={{ headerShown: false }}>
      <AuthStack.Screen name="Login" component={LoginScreen} />
    </AuthStack.Navigator>
  );
}

// ── Root ──
export default function AppNavigator() {
  const { isAuthenticated, isLoading, user } = useAuth();

  if (isLoading) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.background }}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  if (!isAuthenticated) return <AuthNavigator />;

  const role = user?.role || user?.role_nom || '';
  if (role === 'enseignant') return <EnseignantNavigator />;
  if (['admin', 'admin_centre', 'secretaire', 'super_admin'].includes(role)) return <AdminTabs />;
  return <ParentTabs />;
}
