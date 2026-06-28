import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { useI18n } from '../../context/I18nContext';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

export default function ParentBulletinsScreen() {
  const { t } = useI18n();

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{t('bulletins')}</Text>
      <View style={styles.card}>
        <Text style={styles.placeholder}>{t('noData')}</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background, padding: spacing.md },
  title: { fontSize: fontSizes.xl, fontWeight: '700', color: colors.text, marginBottom: spacing.md },
  card: { backgroundColor: colors.surface, borderRadius: 12, padding: spacing.xl, alignItems: 'center' },
  placeholder: { fontSize: fontSizes.md, color: colors.textLight },
});
