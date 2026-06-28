import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, FlatList, ActivityIndicator } from 'react-native';
import { useI18n } from '../../context/I18nContext';
import { messagesApi } from '../../api/endpoints';
import { withCache } from '../../services/cache';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

export default function MessagesScreen() {
  const { t } = useI18n();
  const [conversations, setConversations] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      try {
        const data = await withCache('messages', () => messagesApi.conversations());
        setConversations(data?.data || []);
      } catch {} finally { setLoading(false); }
    })();
  }, []);

  if (loading) return <ActivityIndicator style={styles.center} size="large" color={colors.primary} />;

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{t('messages')}</Text>
      <FlatList
        data={conversations}
        keyExtractor={(item) => item.id}
        renderItem={({ item }) => (
          <View style={styles.card}>
            <View style={styles.avatar}>
              <Text style={styles.avatarText}>{item.sujet?.[0] || '?'}</Text>
            </View>
            <View style={styles.body}>
              <Text style={styles.sujet}>{item.sujet || 'Sans sujet'}</Text>
              <Text style={styles.preview}>{item.dernier_message || ''}</Text>
              <Text style={styles.date}>{item.updated_at?.slice(0, 10)}</Text>
            </View>
            {item.non_lu > 0 && (
              <View style={styles.badge}>
                <Text style={styles.badgeText}>{item.non_lu}</Text>
              </View>
            )}
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
  card: { flexDirection: 'row', alignItems: 'center', backgroundColor: colors.surface, borderRadius: 12, padding: spacing.md, marginBottom: spacing.sm },
  avatar: { width: 44, height: 44, borderRadius: 22, backgroundColor: colors.primary, justifyContent: 'center', alignItems: 'center', marginRight: spacing.sm },
  avatarText: { fontSize: fontSizes.lg, fontWeight: '700', color: colors.white },
  body: { flex: 1 },
  sujet: { fontSize: fontSizes.md, fontWeight: '600', color: colors.text },
  preview: { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: 2 },
  date: { fontSize: fontSizes.xs, color: colors.textLight, marginTop: 2 },
  badge: { width: 22, height: 22, borderRadius: 11, backgroundColor: colors.danger, justifyContent: 'center', alignItems: 'center' },
  badgeText: { fontSize: fontSizes.xs, fontWeight: '700', color: colors.white },
  empty: { textAlign: 'center', color: colors.textLight, marginTop: spacing.xl },
});
