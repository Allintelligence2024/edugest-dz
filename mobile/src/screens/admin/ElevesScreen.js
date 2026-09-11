import React, { useState, useEffect, useCallback } from 'react';
import {
  View, Text, StyleSheet, FlatList, TextInput,
  TouchableOpacity, ActivityIndicator,
} from 'react-native';
import { adminApi } from '../../api/endpoints';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

export default function AdminElevesScreen({ navigation }) {
  const [eleves, setEleves]   = useState([]);
  const [search, setSearch]   = useState('');
  const [loading, setLoading] = useState(true);
  const [page, setPage]       = useState(1);
  const [total, setTotal]     = useState(0);

  const fetchEleves = useCallback(async (s = search, p = 1) => {
    setLoading(true);
    try {
      const res = await adminApi.eleves.list({ search: s, per_page: 20, page: p });
      if (p === 1) setEleves(res.data?.data || []);
      else setEleves(prev => [...prev, ...(res.data?.data || [])]);
      setTotal(res.data?.meta?.total || 0);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchEleves(); }, []);

  const onSearch = (text) => {
    setSearch(text);
    setPage(1);
    if (text.length === 0 || text.length >= 2) fetchEleves(text, 1);
  };

  return (
    <View style={styles.container}>
      <View style={styles.searchBar}>
        <TextInput
          style={styles.searchInput}
          placeholder="🔍 Rechercher un élève..."
          value={search}
          onChangeText={onSearch}
          placeholderTextColor={colors.textLight}
        />
      </View>

      <Text style={styles.count}>{total} élève(s)</Text>

      {loading && page === 1 ? (
        <View style={styles.center}><ActivityIndicator size="large" color={colors.primary} /></View>
      ) : (
        <FlatList
          data={eleves}
          keyExtractor={item => item.id}
          contentContainerStyle={{ padding: spacing.md }}
          renderItem={({ item }) => (
            <TouchableOpacity
              style={styles.eleveCard}
              onPress={() => navigation.navigate('EleveDetail', { eleveId: item.id, nom: `${item.prenom} ${item.nom}` })}
            >
              <View style={styles.avatar}>
                <Text style={styles.avatarText}>{item.prenom?.[0]}{item.nom?.[0]}</Text>
              </View>
              <View style={styles.eleveInfo}>
                <Text style={styles.eleveName}>{item.prenom} {item.nom}</Text>
                <Text style={styles.eleveLevel}>{item.niveau_scolaire} · {item.numero_inscription}</Text>
              </View>
              <View style={[styles.statutDot, { backgroundColor: item.statut === 'actif' ? '#10b981' : '#ef4444' }]} />
            </TouchableOpacity>
          )}
          onEndReached={() => {
            if (eleves.length < total) {
              const nextPage = page + 1;
              setPage(nextPage);
              fetchEleves(search, nextPage);
            }
          }}
          onEndReachedThreshold={0.3}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container:  { flex: 1, backgroundColor: colors.background },
  center:     { flex: 1, justifyContent: 'center', alignItems: 'center' },
  searchBar:  { padding: spacing.md, paddingBottom: 0 },
  searchInput:{ backgroundColor: colors.card, borderRadius: 12, padding: 12, fontSize: fontSizes.sm, color: colors.text },
  count:      { paddingHorizontal: spacing.lg, paddingVertical: 6, fontSize: fontSizes.xs, color: colors.textSecondary },
  eleveCard:  { flexDirection: 'row', alignItems: 'center', backgroundColor: colors.card, borderRadius: 14, padding: spacing.md, marginBottom: spacing.sm },
  avatar:     { width: 44, height: 44, borderRadius: 22, backgroundColor: colors.primary + '20', justifyContent: 'center', alignItems: 'center', marginRight: 12 },
  avatarText: { fontSize: fontSizes.md, fontWeight: '700', color: colors.primary },
  eleveInfo:  { flex: 1 },
  eleveName:  { fontSize: fontSizes.md, fontWeight: '600', color: colors.text },
  eleveLevel: { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: 2 },
  statutDot:  { width: 10, height: 10, borderRadius: 5 },
});
