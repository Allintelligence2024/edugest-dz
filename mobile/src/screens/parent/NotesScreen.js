import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, FlatList, ActivityIndicator } from 'react-native';
import { useAuth } from '../../context/AuthContext';
import { useI18n } from '../../context/I18nContext';
import { notesApi } from '../../api/endpoints';
import { withCache } from '../../services/cache';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

export default function NotesScreen() {
  const { user } = useAuth();
  const { t } = useI18n();
  const [notes, setNotes] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      try {
        const eleveId = user?.eleve_id;
        if (!eleveId) { setLoading(false); return; }
        const data = await withCache(`notes_${eleveId}`, () => notesApi.byEleve(eleveId, { per_page: 50 }));
        setNotes(data?.data || []);
      } catch {} finally { setLoading(false); }
    })();
  }, []);

  const moyenne = notes.length > 0
    ? (notes.reduce((sum, n) => sum + parseFloat(n.valeur || 0), 0) / notes.length).toFixed(2)
    : '-';

  if (loading) return <ActivityIndicator style={styles.center} size="large" color={colors.primary} />;

  return (
    <View style={styles.container}>
      <View style={styles.moyenneCard}>
        <Text style={styles.moyenneLabel}>{t('average')}</Text>
        <Text style={styles.moyenneValue}>{moyenne}/20</Text>
      </View>
      <FlatList
        data={notes}
        keyExtractor={(item) => item.id}
        renderItem={({ item }) => (
          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <Text style={styles.matiere}>{item.evaluation?.matiere?.nom || 'Matière'}</Text>
              <Text style={[styles.note, { color: item.valeur >= 10 ? colors.success : colors.danger }]}>{item.valeur}/20</Text>
            </View>
            <Text style={styles.evaluation}>{item.evaluation?.titre || ''}</Text>
            <Text style={styles.date}>{item.created_at?.slice(0, 10)}</Text>
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
  moyenneCard: { backgroundColor: colors.primary, borderRadius: 16, padding: spacing.lg, alignItems: 'center', marginBottom: spacing.md },
  moyenneLabel: { fontSize: fontSizes.sm, color: colors.white, opacity: 0.9 },
  moyenneValue: { fontSize: fontSizes.title, fontWeight: '800', color: colors.white, marginTop: spacing.xs },
  card: { backgroundColor: colors.surface, borderRadius: 12, padding: spacing.md, marginBottom: spacing.sm },
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  matiere: { fontSize: fontSizes.md, fontWeight: '600', color: colors.text },
  note: { fontSize: fontSizes.lg, fontWeight: '700' },
  evaluation: { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: spacing.xs },
  date: { fontSize: fontSizes.xs, color: colors.textLight, marginTop: spacing.xs },
  empty: { textAlign: 'center', color: colors.textLight, marginTop: spacing.xl },
});
