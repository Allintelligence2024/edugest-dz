import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { useAuth } from '../../context/AuthContext';
import { useI18n } from '../../context/I18nContext';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

export default function ProfileScreen() {
  const { user, logout } = useAuth();
  const { t, lang, changeLang, languages } = useI18n();

  return (
    <View style={styles.container}>
      <View style={styles.avatar}>
        <Text style={styles.avatarText}>{user?.nom?.[0]}{user?.prenom?.[0]}</Text>
      </View>
      <Text style={styles.name}>{user?.prenom} {user?.nom}</Text>
      <Text style={styles.email}>{user?.email}</Text>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>{t('language')}</Text>
        <View style={styles.langRow}>
          {Object.entries(languages).map(([code, { label }]) => (
            <TouchableOpacity
              key={code}
              style={[styles.langBtn, lang === code && styles.langBtnActive]}
              onPress={() => changeLang(code)}
            >
              <Text style={[styles.langText, lang === code && styles.langTextActive]}>{label}</Text>
            </TouchableOpacity>
          ))}
        </View>
      </View>

      <TouchableOpacity style={styles.logoutBtn} onPress={logout}>
        <Text style={styles.logoutText}>{t('logout')}</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background, padding: spacing.lg, alignItems: 'center' },
  avatar: { width: 80, height: 80, borderRadius: 40, backgroundColor: colors.primary, justifyContent: 'center', alignItems: 'center', marginTop: spacing.xl },
  avatarText: { fontSize: fontSizes.xxl, fontWeight: '700', color: colors.white },
  name: { fontSize: fontSizes.xl, fontWeight: '700', color: colors.text, marginTop: spacing.md },
  email: { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: spacing.xs },
  section: { width: '100%', marginTop: spacing.xl },
  sectionTitle: { fontSize: fontSizes.md, fontWeight: '600', color: colors.text, marginBottom: spacing.sm },
  langRow: { flexDirection: 'row', gap: spacing.sm },
  langBtn: { flex: 1, paddingVertical: spacing.sm, borderRadius: 10, borderWidth: 1, borderColor: colors.border, alignItems: 'center' },
  langBtnActive: { borderColor: colors.primary, backgroundColor: colors.primary + '10' },
  langText: { fontSize: fontSizes.sm, color: colors.textSecondary },
  langTextActive: { color: colors.primary, fontWeight: '600' },
  logoutBtn: { width: '100%', paddingVertical: spacing.md, borderRadius: 12, borderWidth: 1, borderColor: colors.danger, alignItems: 'center', marginTop: spacing.xl },
  logoutText: { fontSize: fontSizes.md, fontWeight: '600', color: colors.danger },
});
