import React, { useState, useEffect } from 'react';
import {
  View, Text, StyleSheet, ScrollView,
  TouchableOpacity, ActivityIndicator, RefreshControl,
} from 'react-native';
import { useAuth } from '../../context/AuthContext';
import { adminApi } from '../../api/endpoints';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

export default function AdminDashboardScreen({ navigation }) {
  const { user, tenant } = useAuth();
  const [finance, setFinance]     = useState(null);
  const [loading, setLoading]     = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchData = async () => {
    try {
      const res = await adminApi.dashboard.finance();
      setFinance(res.data?.data);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => { fetchData(); }, []);

  if (loading) return <View style={styles.center}><ActivityIndicator size="large" color={colors.primary} /></View>;

  const kpis = [
    { label: 'CA ce mois',   value: finance?.ca_mois     ? `${Number(finance.ca_mois).toLocaleString()} DA`    : '—', color: '#10b981', icon: '💰' },
    { label: 'Impayés',      value: finance?.impayes     ? `${Number(finance.impayes).toLocaleString()} DA`     : '—', color: '#ef4444', icon: '⚠️' },
    { label: 'CA annuel',    value: finance?.ca_annee    ? `${Number(finance.ca_annee).toLocaleString()} DA`    : '—', color: '#6366f1', icon: '📊' },
    { label: 'Fact. impayées', value: finance?.nb_impayes ?? '—',                                                      color: '#f59e0b', icon: '📄' },
  ];

  return (
    <ScrollView
      style={styles.container}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); fetchData(); }} />}
    >
      <View style={styles.header}>
        <Text style={styles.greeting}>Bonjour, {user?.prenom} 👋</Text>
        <Text style={styles.tenant}>{tenant?.nom_etablissement}</Text>
      </View>

      <View style={styles.kpiGrid}>
        {kpis.map((k, i) => (
          <View key={i} style={[styles.kpiCard, { backgroundColor: k.color }]}>
            <Text style={styles.kpiIcon}>{k.icon}</Text>
            <Text style={styles.kpiValue}>{k.value}</Text>
            <Text style={styles.kpiLabel}>{k.label}</Text>
          </View>
        ))}
      </View>

      <View style={styles.menuGrid}>
        {[
          { label: '👦 Élèves',     screen: 'Eleves',   color: '#6366f1' },
          { label: '✅ Absences',   screen: 'Absences', color: '#ef4444' },
          { label: '💰 Finance',    screen: 'Finance',  color: '#10b981' },
          { label: '📊 Rapports',   screen: 'Finance',  color: '#f59e0b' },
        ].map((m, i) => (
          <TouchableOpacity
            key={i}
            style={[styles.menuCard, { borderLeftColor: m.color }]}
            onPress={() => navigation.navigate(m.screen)}
          >
            <Text style={styles.menuLabel}>{m.label}</Text>
          </TouchableOpacity>
        ))}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container:  { flex: 1, backgroundColor: colors.background },
  center:     { flex: 1, justifyContent: 'center', alignItems: 'center' },
  header:     { padding: spacing.lg, paddingBottom: spacing.sm },
  greeting:   { fontSize: fontSizes.xl, fontWeight: '700', color: colors.text },
  tenant:     { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: 4 },
  kpiGrid:    { flexDirection: 'row', flexWrap: 'wrap', padding: spacing.md, gap: spacing.sm },
  kpiCard:    { width: '48%', borderRadius: 14, padding: spacing.md },
  kpiIcon:    { fontSize: 22 },
  kpiValue:   { fontSize: fontSizes.xl, fontWeight: '800', color: '#fff', marginTop: 4 },
  kpiLabel:   { fontSize: fontSizes.xs, color: '#fff', opacity: 0.85, marginTop: 4 },
  menuGrid:   { padding: spacing.md, gap: spacing.sm },
  menuCard:   { backgroundColor: colors.card, borderRadius: 14, padding: spacing.md, borderLeftWidth: 4 },
  menuLabel:  { fontSize: fontSizes.md, fontWeight: '600', color: colors.text },
});
