import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, FlatList, ActivityIndicator } from 'react-native';
import { useAuth } from '../../context/AuthContext';
import { useI18n } from '../../context/I18nContext';
import { presencesApi } from '../../api/endpoints';
import { withCache } from '../../services/cache';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

const STATUS_COLORS = { present: colors.success, absent: colors.danger, justifie: colors.warning, retard: colors.info };

export default function PresencesScreen() {
  const { user } = useAuth();
  const { t } = useI18n();
  const [presences, setPresences] = useState([]);
  const [loading, setLoading] = useState(true);
  const [stats, setStats] = useState({ present: 0, absent: 0, total: 0 });

  useEffect(() => {
    (async () => {
      try {
        const eleveId = user?.eleve_id;
        if (!eleveId) { setLoading(false); return; }
        const data = await withCache(`presences_${eleveId}`, () => presencesApi.byEleve(eleveId, { per_page: 50 }));
        const items = data?.data || [];
        setPresences(items);
        setStats({
          total: items.length,
          present: items.filter((p) => p.statut === 'present').length,
          absent: items.filter((p) => p.statut === 'absent').length,
        });
      } catch {} finally { setLoading(false); }
    })();
  }, []);

  const taux = stats.total > 0 ? Math.round((stats.present / stats.total) * 100) : 0;

  if (loading) return <ActivityIndicator style={styles.center} size="large" color={colors.primary} />;

  return (
    <View style={styles.container}>
      <View style={styles.statsCard}>
        <Text style={styles.tauxLabel}>{t('monthPresences')}</Text>
        <Text style={styles.tauxValue}>{taux}%</Text>
        <View style={styles.statsRow}>
          <Text style={styles.stat}>{t('present')}: {stats.present}</Text>
          <Text style={styles.stat}>{t('absent')}: {stats.absent}</Text>
        </View>
      </View>
      <FlatList
        data={presences}
        keyExtractor={(item) => item.id}
        renderItem={({ item }) => (
          <View style={styles.card}>
            <View style={[styles.dot, { backgroundColor: STATUS_COLORS[item.statut] || colors.textLight }]} />
            <View style={styles.cardBody}>
              <Text style={styles.date}>{item.seance?.cours?.matiere?.nom || 'Séance'}</Text>
              <Text style={styles.sub}>{item.date} — {item.seance?.heure_debut || ''}</Text>
            </View>
            <Text style={[styles.status, { color: STATUS_COLORS[item.statut] || colors.textLight }]}>
              {t(item.statut === 'justifie' ? 'justified' : item.statut === 'retard' ? 'late' : item.statut)}
            </Text>
          </View>
        )}
        ListEmptyComponent={<Text style={styles.empty}>{t('noData')}</Text>}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background, padding: spacing.md },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  statsCard: { backgroundColor: colors.surface, borderRadius: 16, padding: spacing.lg, alignItems: 'center', marginBottom: spacing.md },
  tauxLabel: { fontSize: fontSizes.sm, color: colors.textSecondary },
  tauxValue: { fontSize: fontSizes.xxl, fontWeight: '800', color: colors.primary },
  statsRow: { flexDirection: 'row', gap: spacing.lg, marginTop: spacing.sm },
  stat: { fontSize: fontSizes.sm, color: colors.text },
  card: { flexDirection: 'row', alignItems: 'center', backgroundColor: colors.surface, borderRadius: 12, padding: spacing.md, marginBottom: spacing.sm },
  dot: { width: 12, height: 12, borderRadius: 6, marginRight: spacing.sm },
  cardBody: { flex: 1 },
  date: { fontSize: fontSizes.md, fontWeight: '600', color: colors.text },
  sub: { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: 2 },
  status: { fontSize: fontSizes.sm, fontWeight: '600' },
  empty: { textAlign: 'center', color: colors.textLight, marginTop: spacing.xl },
});
