import React, { useState, useEffect } from 'react';
import {
  View, Text, StyleSheet, ScrollView,
  TouchableOpacity, ActivityIndicator, RefreshControl,
} from 'react-native';
import { useAuth } from '../../context/AuthContext';
import { useI18n } from '../../context/I18nContext';
import { enseignantApi } from '../../api/endpoints';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

export default function EnseignantDashboardScreen({ navigation }) {
  const { user } = useAuth();
  const { t } = useI18n();
  const [stats, setStats]         = useState(null);
  const [pointage, setPointage]   = useState(null);
  const [loading, setLoading]     = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchData = async () => {
    try {
      const [statsRes, pointageRes] = await Promise.all([
        enseignantApi.statistiques(user?.enseignant_id || user?.id),
        enseignantApi.pointage.aujourdhui(),
      ]);
      setStats(statsRes.data?.data);
      const monPointage = pointageRes.data?.data?.enseignants
        ?.find(e => e.enseignant?.id === (user?.enseignant_id || user?.id));
      setPointage(monPointage);
    } catch (e) {
      console.error('Dashboard enseignant:', e);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => { fetchData(); }, []);

  const handlePointage = async (type) => {
    try {
      const id = user?.enseignant_id || user?.id;
      if (type === 'arrivee') {
        await enseignantApi.pointage.arrivee(id, {});
      } else {
        await enseignantApi.pointage.depart(id, {});
      }
      fetchData();
    } catch (e) {
      console.error('Pointage:', e);
    }
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  const estPointe = !!pointage?.heure_arrivee;
  const aDepart   = !!pointage?.heure_depart;

  return (
    <ScrollView
      style={styles.container}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); fetchData(); }} />}
    >
      <View style={styles.header}>
        <Text style={styles.greeting}>Bonjour, {user?.prenom} 👋</Text>
        <Text style={styles.date}>{new Date().toLocaleDateString('fr-DZ', { weekday: 'long', day: 'numeric', month: 'long' })}</Text>
      </View>

      <View style={styles.pointageCard}>
        <Text style={styles.cardTitle}>⏰ Pointage aujourd'hui</Text>
        <Text style={styles.pointageStatut}>
          {estPointe ? `✅ Arrivé à ${pointage?.heure_arrivee?.slice(0, 5)}` : '⏳ Non pointé'}
          {aDepart ? `  ·  Parti à ${pointage?.heure_depart?.slice(0, 5)}` : ''}
        </Text>
        <View style={styles.pointageButtons}>
          {!estPointe && (
            <TouchableOpacity style={[styles.btn, { backgroundColor: colors.success }]} onPress={() => handlePointage('arrivee')}>
              <Text style={styles.btnText}>📍 Pointer arrivée</Text>
            </TouchableOpacity>
          )}
          {estPointe && !aDepart && (
            <TouchableOpacity style={[styles.btn, { backgroundColor: colors.warning }]} onPress={() => handlePointage('depart')}>
              <Text style={styles.btnText}>🚪 Pointer départ</Text>
            </TouchableOpacity>
          )}
        </View>
      </View>

      <View style={styles.statsRow}>
        {[
          { label: 'Groupes actifs', value: stats?.cours_actifs ?? '—', color: '#6366f1' },
          { label: 'Paie du mois', value: stats?.paie_mois ? `${Number(stats.paie_mois).toLocaleString()} DA` : '—', color: '#10b981' },
        ].map((s, i) => (
          <View key={i} style={[styles.statCard, { backgroundColor: s.color }]}>
            <Text style={styles.statValue}>{s.value}</Text>
            <Text style={styles.statLabel}>{s.label}</Text>
          </View>
        ))}
      </View>

      <View style={styles.quickNav}>
        {[
          { label: '📅 Mon planning',  screen: 'Planning' },
          { label: '📚 Mes groupes',   screen: 'MesGroupes' },
          { label: '✅ Présences',     screen: 'Presences' },
          { label: '📝 Notes',         screen: 'Notes' },
        ].map((item, i) => (
          <TouchableOpacity key={i} style={styles.navCard} onPress={() => navigation.navigate(item.screen)}>
            <Text style={styles.navLabel}>{item.label}</Text>
          </TouchableOpacity>
        ))}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container:      { flex: 1, backgroundColor: colors.background },
  center:         { flex: 1, justifyContent: 'center', alignItems: 'center' },
  header:         { padding: spacing.lg, paddingBottom: spacing.sm },
  greeting:       { fontSize: fontSizes.xl, fontWeight: '700', color: colors.text },
  date:           { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: 4 },
  pointageCard:   { margin: spacing.md, backgroundColor: colors.card, borderRadius: 16, padding: spacing.md },
  cardTitle:      { fontSize: fontSizes.md, fontWeight: '700', color: colors.text, marginBottom: 8 },
  pointageStatut: { fontSize: fontSizes.sm, color: colors.textSecondary, marginBottom: 12 },
  pointageButtons:{ flexDirection: 'row', gap: 8 },
  btn:            { flex: 1, borderRadius: 10, paddingVertical: 10, alignItems: 'center' },
  btnText:        { color: '#fff', fontWeight: '600', fontSize: fontSizes.sm },
  statsRow:       { flexDirection: 'row', paddingHorizontal: spacing.md, gap: spacing.sm, marginBottom: spacing.md },
  statCard:       { flex: 1, borderRadius: 14, padding: spacing.md },
  statValue:      { fontSize: fontSizes.xl, fontWeight: '800', color: '#fff' },
  statLabel:      { fontSize: fontSizes.xs, color: '#fff', opacity: 0.85, marginTop: 4 },
  quickNav:       { flexDirection: 'row', flexWrap: 'wrap', paddingHorizontal: spacing.md, gap: spacing.sm },
  navCard:        { width: '48%', backgroundColor: colors.card, borderRadius: 14, padding: spacing.md, alignItems: 'center' },
  navLabel:       { fontSize: fontSizes.sm, fontWeight: '600', color: colors.text, textAlign: 'center' },
});
