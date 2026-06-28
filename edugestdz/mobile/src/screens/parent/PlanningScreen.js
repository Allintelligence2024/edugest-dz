import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, FlatList, ActivityIndicator } from 'react-native';
import { useI18n } from '../../context/I18nContext';
import { planningApi } from '../../api/endpoints';
import { withCache } from '../../services/cache';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

export default function PlanningScreen() {
  const { t } = useI18n();
  const [seances, setSeances] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      try {
        const data = await withCache('parent_planning', () => planningApi.list({ per_page: 20 }));
        setSeances(data?.data || []);
      } catch {} finally {
        setLoading(false);
      }
    })();
  }, []);

  if (loading) return <ActivityIndicator style={styles.center} size="large" color={colors.primary} />;

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{t('planning')}</Text>
      <FlatList
        data={seances}
        keyExtractor={(item) => item.id}
        renderItem={({ item }) => (
          <View style={styles.card}>
            <Text style={styles.coursName}>{item.cours?.matiere?.nom || 'Cours'}</Text>
            <Text style={styles.detail}>{item.date} — {item.heure_debut} à {item.heure_fin}</Text>
            <Text style={styles.detail}>{item.salle?.nom || ''}</Text>
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
  title: { fontSize: fontSizes.xl, fontWeight: '700', color: colors.text, marginBottom: spacing.md },
  card: { backgroundColor: colors.surface, borderRadius: 12, padding: spacing.md, marginBottom: spacing.sm, borderLeftWidth: 4, borderLeftColor: colors.primary },
  coursName: { fontSize: fontSizes.md, fontWeight: '600', color: colors.text },
  detail: { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: spacing.xs },
  empty: { textAlign: 'center', color: colors.textLight, marginTop: spacing.xl },
});
