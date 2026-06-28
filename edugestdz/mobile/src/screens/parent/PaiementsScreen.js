import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, FlatList, ActivityIndicator } from 'react-native';
import { useAuth } from '../../context/AuthContext';
import { useI18n } from '../../context/I18nContext';
import { paiementsApi } from '../../api/endpoints';
import { withCache } from '../../services/cache';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

const STATUS_STYLES = { paye: { color: colors.success, label: 'paid' }, impaye: { color: colors.danger, label: 'unpaid' }, en_attente: { color: colors.warning, label: 'pending' } };

export default function PaiementsScreen() {
  const { user } = useAuth();
  const { t } = useI18n();
  const [paiements, setPaiements] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      try {
        const eleveId = user?.eleve_id;
        if (!eleveId) { setLoading(false); return; }
        const data = await withCache(`paiements_${eleveId}`, () => paiementsApi.byEleve(eleveId, { per_page: 20 }));
        setPaiements(data?.data || []);
      } catch {} finally { setLoading(false); }
    })();
  }, []);

  const totalPaye = paiements.filter((p) => p.statut === 'paye').reduce((s, p) => s + parseFloat(p.montant || 0), 0);

  if (loading) return <ActivityIndicator style={styles.center} size="large" color={colors.primary} />;

  return (
    <View style={styles.container}>
      <View style={styles.totalCard}>
        <Text style={styles.totalLabel}>{t('paid')}</Text>
        <Text style={styles.totalValue}>{totalPaye.toLocaleString()} DZD</Text>
      </View>
      <FlatList
        data={paiements}
        keyExtractor={(item) => item.id}
        renderItem={({ item }) => {
          const st = STATUS_STYLES[item.statut] || STATUS_STYLES.en_attente;
          return (
            <View style={styles.card}>
              <View style={styles.cardHeader}>
                <Text style={styles.libelle}>{item.libelle || 'Paiement'}</Text>
                <Text style={[styles.montant, { color: st.color }]}>{parseFloat(item.montant || 0).toLocaleString()} DZD</Text>
              </View>
              <Text style={styles.date}>{item.date || item.created_at?.slice(0, 10)}</Text>
              <View style={[styles.badge, { backgroundColor: st.color + '20' }]}>
                <Text style={[styles.badgeText, { color: st.color }]}>{t(st.label)}</Text>
              </View>
            </View>
          );
        }}
        ListEmptyComponent={<Text style={styles.empty}>{t('noData')}</Text>}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background, padding: spacing.md },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  totalCard: { backgroundColor: colors.success, borderRadius: 16, padding: spacing.lg, alignItems: 'center', marginBottom: spacing.md },
  totalLabel: { fontSize: fontSizes.sm, color: colors.white, opacity: 0.9 },
  totalValue: { fontSize: fontSizes.xxl, fontWeight: '800', color: colors.white, marginTop: spacing.xs },
  card: { backgroundColor: colors.surface, borderRadius: 12, padding: spacing.md, marginBottom: spacing.sm },
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  libelle: { fontSize: fontSizes.md, fontWeight: '600', color: colors.text, flex: 1 },
  montant: { fontSize: fontSizes.md, fontWeight: '700' },
  date: { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: spacing.xs },
  badge: { alignSelf: 'flex-start', borderRadius: 8, paddingHorizontal: spacing.sm, paddingVertical: 2, marginTop: spacing.xs },
  badgeText: { fontSize: fontSizes.xs, fontWeight: '600' },
  empty: { textAlign: 'center', color: colors.textLight, marginTop: spacing.xl },
});
