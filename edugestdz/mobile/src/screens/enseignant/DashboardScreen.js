import React from 'react';
import { Text, StyleSheet } from 'react-native';
import { useI18n } from '../../context/I18nContext';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

export default function EnseignantDashboardScreen() {
  const { t } = useI18n();
  return <Text style={styles.placeholder}>{t('dashboard')}</Text>;
}

const styles = StyleSheet.create({
  placeholder: { flex: 1, textAlign: 'center', textAlignVertical: 'center', color: colors.textLight, fontSize: fontSizes.md, padding: spacing.xl },
});
