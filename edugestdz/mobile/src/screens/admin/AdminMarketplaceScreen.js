import React, { useState, useEffect } from 'react';
import {
  View, Text, ScrollView, StyleSheet,
  ActivityIndicator, RefreshControl, Alert,
} from 'react-native';
import { useAuth } from '../../context/AuthContext';
import { adminApi } from '../../api/endpoints';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

export default function AdminMarketplaceScreen() {
  const { token } = useAuth();
  const [stats, setStats] = useState(null);
  const [commissions, setCommissions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const charger = async () => {
    try {
      const [dashRes, commRes] = await Promise.all([
        adminApi.marketplace.dashboard(token),
        adminApi.marketplace.commissions(token),
      ]);
      setStats(dashRes?.data?.totaux ?? null);
      setCommissions(commRes?.data?.top_enseignants ?? []);
    } catch (e) {
      Alert.alert('Erreur', 'Impossible de charger les données marketplace');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => { charger(); }, []);

  const fmt = (n) => {
    if (n == null) return '—';
    return new Intl.NumberFormat('fr-DZ').format(Number(n));
  };

  if (loading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={styles.loadingText}>Chargement du marketplace...</Text>
      </View>
    );
  }

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl
          refreshing={refreshing}
          onRefresh={() => { setRefreshing(true); charger(); }}
          tintColor={colors.primary}
        />
      }
    >
      <View style={styles.header}>
        <Text style={styles.headerTitle}>🛒 Marketplace</Text>
        <Text style={styles.headerSub}>Revenus & Commissions</Text>
      </View>

      <View style={styles.kpiGrid}>
        <View style={[styles.kpiCard, styles.kpiBlue]}>
          <Text style={styles.kpiValue}>{fmt(stats?.nb_transactions ?? 0)}</Text>
          <Text style={styles.kpiLabel}>Transactions</Text>
        </View>
        <View style={[styles.kpiCard, styles.kpiGreen]}>
          <Text style={styles.kpiValue}>{fmt(stats?.ca_total ?? 0)}</Text>
          <Text style={styles.kpiLabel}>CA total (DA)</Text>
        </View>
        <View style={[styles.kpiCard, styles.kpiPurple]}>
          <Text style={styles.kpiValue}>{fmt(stats?.commissions_percues ?? 0)}</Text>
          <Text style={styles.kpiLabel}>Commissions (DA)</Text>
        </View>
        <View style={[styles.kpiCard, styles.kpiTeal]}>
          <Text style={styles.kpiValue}>{fmt(stats?.net_enseignants ?? 0)}</Text>
          <Text style={styles.kpiLabel}>Net Enseignants</Text>
        </View>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>🏆 Top Enseignants</Text>
        {commissions.length === 0 ? (
          <View style={styles.emptyState}>
            <Text style={styles.emptyIcon}>📊</Text>
            <Text style={styles.emptyText}>Aucune transaction encore</Text>
            <Text style={styles.emptySub}>Les données apparaîtront après les premières réservations</Text>
          </View>
        ) : (
          commissions.map((ens, idx) => (
            <View key={idx} style={styles.ensRow}>
              <View style={styles.ensRank}>
                <Text style={styles.ensRankText}>#{idx + 1}</Text>
              </View>
              <View style={styles.ensInfo}>
                <Text style={styles.ensNom}>{ens.nom}</Text>
                <Text style={styles.ensSub}>{ens.nb_cours} cours</Text>
              </View>
              <Text style={styles.ensRevenu}>{fmt(ens.total_net)} DA</Text>
            </View>
          ))
        )}
      </View>

      <View style={styles.infoBox}>
        <Text style={styles.infoText}>
          💡 Commission EduGest DZ : {stats?.taux_moyen ?? '7%'}
        </Text>
        <Text style={styles.infoSub}>
          Gratuit: 10% · Pro: 7% · Premium: 5%
        </Text>
      </View>

      <View style={styles.bottomSpacer} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container:   { flex: 1, backgroundColor: colors.background },
  centered:    { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.background },
  loadingText: { color: colors.textSecondary, marginTop: 12, fontSize: fontSizes.sm },
  header:      { padding: spacing.lg, paddingTop: spacing.sm },
  headerTitle: { fontSize: fontSizes.xl, fontWeight: '900', color: colors.text },
  headerSub:   { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: 4 },
  kpiGrid:     { flexDirection: 'row', flexWrap: 'wrap', paddingHorizontal: 12, gap: 8, marginBottom: 20 },
  kpiCard:     { flex: 1, minWidth: '45%', borderRadius: 14, padding: 16, alignItems: 'center', borderWidth: 1 },
  kpiBlue:     { backgroundColor: 'rgba(37,99,235,0.1)', borderColor: 'rgba(37,99,235,0.3)' },
  kpiGreen:    { backgroundColor: 'rgba(16,185,129,0.1)', borderColor: 'rgba(16,185,129,0.3)' },
  kpiPurple:   { backgroundColor: 'rgba(139,92,246,0.1)', borderColor: 'rgba(139,92,246,0.3)' },
  kpiTeal:     { backgroundColor: 'rgba(8,145,178,0.1)', borderColor: 'rgba(8,145,178,0.3)' },
  kpiValue:    { fontSize: 20, fontWeight: '900', color: colors.text },
  kpiLabel:    { fontSize: 11, color: colors.textSecondary, marginTop: 4, textAlign: 'center' },
  section:     { backgroundColor: colors.card, marginHorizontal: 16, borderRadius: 16, padding: 16, marginBottom: 16, borderWidth: 1, borderColor: colors.border },
  sectionTitle:{ fontSize: fontSizes.md, fontWeight: '800', color: colors.text, marginBottom: 14 },
  emptyState:  { alignItems: 'center', paddingVertical: 24 },
  emptyIcon:   { fontSize: 36, marginBottom: 8 },
  emptyText:   { fontSize: fontSizes.sm, fontWeight: '700', color: colors.textSecondary },
  emptySub:    { fontSize: fontSizes.xs, color: colors.textSecondary, marginTop: 4, textAlign: 'center' },
  ensRow:      { flexDirection: 'row', alignItems: 'center', paddingVertical: 10, borderBottomWidth: 1, borderBottomColor: colors.border },
  ensRank:     { width: 32, height: 32, borderRadius: 8, backgroundColor: 'rgba(37,99,235,0.15)', justifyContent: 'center', alignItems: 'center', marginRight: 12 },
  ensRankText: { fontSize: 12, fontWeight: '800', color: '#60a5fa' },
  ensInfo:     { flex: 1 },
  ensNom:      { fontSize: 14, fontWeight: '700', color: colors.text },
  ensSub:      { fontSize: 11, color: colors.textSecondary, marginTop: 2 },
  ensRevenu:   { fontSize: 14, fontWeight: '800', color: '#10b981' },
  infoBox:     { backgroundColor: 'rgba(37,99,235,0.06)', marginHorizontal: 16, borderRadius: 12, padding: 14, borderWidth: 1, borderColor: 'rgba(37,99,235,0.2)', marginBottom: 16 },
  infoText:    { fontSize: 13, fontWeight: '700', color: '#60a5fa' },
  infoSub:     { fontSize: 11, color: colors.textSecondary, marginTop: 4 },
  bottomSpacer:{ height: 40 },
});
