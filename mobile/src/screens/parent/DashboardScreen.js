import React from 'react';
import { View, Text, StyleSheet, ScrollView } from 'react-native';
import { useAuth } from '../../context/AuthContext';
import { useI18n } from '../../context/I18nContext';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

export default function ParentDashboardScreen() {
  const { user, tenant } = useAuth();
  const { t } = useI18n();

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.welcome}>{t('welcome')}, {user?.prenom} {user?.nom}</Text>
      <Text style={styles.tenant}>{tenant?.nom}</Text>

      <View style={styles.statsGrid}>
        <View style={[styles.statCard, { backgroundColor: colors.primaryLight }]}>
          <Text style={styles.statValue}>-</Text>
          <Text style={styles.statLabel}>{t('nextCourse')}</Text>
        </View>
        <View style={[styles.statCard, { backgroundColor: colors.success }]}>
          <Text style={styles.statValue}>-</Text>
          <Text style={styles.statLabel}>{t('average')}</Text>
        </View>
        <View style={[styles.statCard, { backgroundColor: colors.info }]}>
          <Text style={styles.statValue}>-</Text>
          <Text style={styles.statLabel}>{t('monthPresences')}</Text>
        </View>
        <View style={[styles.statCard, { backgroundColor: colors.warning }]}>
          <Text style={styles.statValue}>-</Text>
          <Text style={styles.statLabel}>{t('lastPayment')}</Text>
        </View>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  content: { padding: spacing.md },
  welcome: { fontSize: fontSizes.xl, fontWeight: '700', color: colors.text },
  tenant: { fontSize: fontSizes.sm, color: colors.textSecondary, marginBottom: spacing.lg },
  statsGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  statCard: { width: '48%', borderRadius: 16, padding: spacing.md, marginBottom: spacing.sm },
  statValue: { fontSize: fontSizes.xxl, fontWeight: '800', color: colors.white },
  statLabel: { fontSize: fontSizes.sm, color: colors.white, opacity: 0.9, marginTop: spacing.xs },
});
