import React, { useState, useEffect, useCallback } from 'react';
import {
  View, Text, StyleSheet, FlatList, ActivityIndicator, TouchableOpacity,
} from 'react-native';
import { useI18n } from '../../context/I18nContext';
import { enseignantApi } from '../../api/endpoints';
import { withCache } from '../../services/cache';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

const STATUS_COLORS = {
  present: { bg: colors.success + '20', text: colors.success, label: 'Présent' },
  absent: { bg: colors.danger + '20', text: colors.danger, label: 'Absent' },
  en_retard: { bg: colors.warning + '20', text: colors.warning, label: 'En retard' },
  non_pointe: { bg: colors.textLight + '30', text: colors.textSecondary, label: 'Non pointé' },
};

const FILTERS = ['tous', 'present', 'absent', 'en_retard', 'non_pointe'];

export default function AdminPointageScreen() {
  const { t } = useI18n();
  const [pointages, setPointages] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('tous');

  const fetchPointages = useCallback(async () => {
    try {
      const data = await withCache('pointage_admin_aujourdhui', () =>
        enseignantApi.pointage.aujourdhui(),
      );
      setPointages(data?.data || []);
    } catch {} finally { setLoading(false); }
  }, []);

  useEffect(() => { fetchPointages(); }, [fetchPointages]);

  const filtered = filter === 'tous'
    ? pointages
    : pointages.filter((p) => p.statut === filter);

  const stats = {
    total: pointages.length,
    present: pointages.filter((p) => p.statut === 'present').length,
    absent: pointages.filter((p) => p.statut === 'absent').length,
    en_retard: pointages.filter((p) => p.statut === 'en_retard').length,
  };

  if (loading) {
    return <ActivityIndicator style={styles.center} size="large" color={colors.primary} />;
  }

  const renderItem = ({ item }) => {
    const st = STATUS_COLORS[item.statut] || STATUS_COLORS.non_pointe;
    return (
      <View style={styles.card}>
        <View style={styles.cardRow}>
          <View style={styles.avatar}>
            <Text style={styles.avatarText}>
              {(item.prenom?.[0] || '')}{(item.nom?.[0] || '')}
            </Text>
          </View>
          <View style={styles.cardInfo}>
            <Text style={styles.cardName}>
              {item.prenom} {item.nom}
            </Text>
            {item.matiere && (
              <Text style={styles.cardMatiere}>{item.matiere}</Text>
            )}
            {item.heure_arrivee && (
              <Text style={styles.cardTime}>
                Arrivée: {item.heure_arrivee?.slice(0, 5)}
                {item.heure_depart ? ` | Départ: ${item.heure_depart?.slice(0, 5)}` : ''}
              </Text>
            )}
          </View>
          <View style={[styles.statusBadge, { backgroundColor: st.bg }]}>
            <Text style={[styles.statusText, { color: st.text }]}>{st.label}</Text>
          </View>
        </View>
      </View>
    );
  };

  return (
    <View style={styles.container}>
      <View style={styles.statsRow}>
        <View style={[styles.statCard, { borderLeftColor: colors.primary }]}>
          <Text style={styles.statValue}>{stats.total}</Text>
          <Text style={styles.statLabel}>Total</Text>
        </View>
        <View style={[styles.statCard, { borderLeftColor: colors.success }]}>
          <Text style={styles.statValue}>{stats.present}</Text>
          <Text style={styles.statLabel}>Présents</Text>
        </View>
        <View style={[styles.statCard, { borderLeftColor: colors.warning }]}>
          <Text style={styles.statValue}>{stats.en_retard}</Text>
          <Text style={styles.statLabel}>Retard</Text>
        </View>
        <View style={[styles.statCard, { borderLeftColor: colors.danger }]}>
          <Text style={styles.statValue}>{stats.absent}</Text>
          <Text style={styles.statLabel}>Absents</Text>
        </View>
      </View>

      <View style={styles.filterRow}>
        {FILTERS.map((f) => (
          <TouchableOpacity
            key={f}
            style={[styles.filterChip, filter === f && styles.filterChipActive]}
            onPress={() => setFilter(f)}
          >
            <Text style={[styles.filterText, filter === f && styles.filterTextActive]}>
              {f === 'tous' ? 'Tous' : STATUS_COLORS[f]?.label || f}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      <FlatList
        data={filtered}
        keyExtractor={(item) => String(item.id || item.enseignant_id)}
        renderItem={renderItem}
        ListEmptyComponent={<Text style={styles.empty}>Aucun pointage pour aujourd'hui.</Text>}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background, padding: spacing.md },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  statsRow: { flexDirection: 'row', gap: spacing.sm, marginBottom: spacing.md },
  statCard: {
    flex: 1, backgroundColor: colors.surface, borderRadius: 10, padding: spacing.sm,
    borderLeftWidth: 3,
  },
  statValue: { fontSize: fontSizes.lg, fontWeight: '800', color: colors.text },
  statLabel: { fontSize: fontSizes.xs, color: colors.textSecondary, marginTop: 2 },
  filterRow: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs, marginBottom: spacing.md },
  filterChip: {
    paddingHorizontal: spacing.md, paddingVertical: spacing.xs,
    borderRadius: 20, backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.border,
  },
  filterChipActive: { backgroundColor: colors.primary, borderColor: colors.primary },
  filterText: { fontSize: fontSizes.xs, color: colors.textSecondary, fontWeight: '600' },
  filterTextActive: { color: colors.white },
  card: {
    backgroundColor: colors.surface, borderRadius: 12, padding: spacing.md,
    marginBottom: spacing.sm,
  },
  cardRow: { flexDirection: 'row', alignItems: 'center' },
  avatar: {
    width: 40, height: 40, borderRadius: 20, backgroundColor: colors.primaryLight,
    justifyContent: 'center', alignItems: 'center', marginRight: spacing.sm,
  },
  avatarText: { color: colors.white, fontSize: fontSizes.sm, fontWeight: '700' },
  cardInfo: { flex: 1 },
  cardName: { fontSize: fontSizes.md, fontWeight: '600', color: colors.text },
  cardMatiere: { fontSize: fontSizes.sm, color: colors.textSecondary },
  cardTime: { fontSize: fontSizes.xs, color: colors.textLight, marginTop: 2 },
  statusBadge: { borderRadius: 8, paddingHorizontal: spacing.sm, paddingVertical: 2 },
  statusText: { fontSize: fontSizes.xs, fontWeight: '700' },
  empty: { textAlign: 'center', color: colors.textLight, marginTop: spacing.xl },
});
