import React, { useState, useEffect, useCallback } from 'react';
import {
  View, Text, TextInput, StyleSheet, FlatList, ActivityIndicator,
  TouchableOpacity, Linking,
} from 'react-native';
import api from '../../api/axios';
import { withCache } from '../../services/cache';
import { useI18n } from '../../context/I18nContext';
import { useAuth } from '../../context/AuthContext';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

export default function MarketplaceScreen() {
  const { t } = useI18n();
  const { user } = useAuth();
  const [query, setQuery] = useState('');
  const [results, setResults] = useState([]);
  const [featured, setFeatured] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searching, setSearching] = useState(false);

  const fetchFeatured = useCallback(async () => {
    try {
      const data = await withCache('marketplace_featured', () =>
        api.get('/marketplace/featured'),
      );
      setFeatured(data?.data || []);
    } catch {} finally { setLoading(false); }
  }, []);

  useEffect(() => { fetchFeatured(); }, [fetchFeatured]);

  const handleSearch = async () => {
    if (!query.trim()) return;
    setSearching(true);
    try {
      const data = await api.get('/marketplace/recherche', {
        params: { q: query.trim(), tenant_id: user?.tenant_id },
      });
      setResults(data?.data || []);
    } catch {} finally { setSearching(false); }
  };

  const handleViewCentre = (tenant) => {
    const url = `edugest://centre/${tenant.id}`;
    Linking.canOpenURL(url).then((ok) => {
      if (ok) Linking.openURL(url);
    });
  };

  if (loading) {
    return <ActivityIndicator style={styles.center} size="large" color={colors.primary} />;
  }

  const renderCentre = ({ item }) => (
    <TouchableOpacity style={styles.card} onPress={() => handleViewCentre(item)}>
      <Text style={styles.cardTitle}>{item.nom || item.raison_sociale}</Text>
      {item.adresse && <Text style={styles.cardSub}>{item.adresse}</Text>}
      <View style={styles.cardTags}>
        {item.specialites?.map((s, i) => (
          <View key={i} style={styles.tag}><Text style={styles.tagText}>{s}</Text></View>
        ))}
      </View>
      <Text style={styles.cardContact}>{item.email || item.telephone}</Text>
    </TouchableOpacity>
  );

  return (
    <View style={styles.container}>
      <View style={styles.searchRow}>
        <TextInput
          style={styles.input}
          placeholder="Rechercher un centre..."
          placeholderTextColor={colors.textLight}
          value={query}
          onChangeText={setQuery}
          onSubmitEditing={handleSearch}
          returnKeyType="search"
        />
        <TouchableOpacity style={styles.searchButton} onPress={handleSearch} disabled={searching}>
          <Text style={styles.searchButtonText}>{searching ? '...' : 'Rechercher'}</Text>
        </TouchableOpacity>
      </View>

      {results.length > 0 ? (
        <>
          <Text style={styles.sectionTitle}>Résultats ({results.length})</Text>
          <FlatList
            data={results}
            keyExtractor={(item) => String(item.id)}
            renderItem={renderCentre}
          />
        </>
      ) : (
        <>
          <Text style={styles.sectionTitle}>Centres à la une</Text>
          <FlatList
            data={featured}
            keyExtractor={(item) => String(item.id)}
            renderItem={renderCentre}
            ListEmptyComponent={<Text style={styles.empty}>Aucun centre disponible.</Text>}
          />
        </>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background, padding: spacing.md },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  searchRow: { flexDirection: 'row', marginBottom: spacing.md, gap: spacing.sm },
  input: {
    flex: 1, backgroundColor: colors.surface, borderRadius: 12, paddingHorizontal: spacing.md,
    fontSize: fontSizes.md, color: colors.text, borderWidth: 1, borderColor: colors.border,
  },
  searchButton: {
    backgroundColor: colors.primary, borderRadius: 12, paddingHorizontal: spacing.lg,
    justifyContent: 'center',
  },
  searchButtonText: { color: colors.white, fontWeight: '700', fontSize: fontSizes.sm },
  sectionTitle: {
    fontSize: fontSizes.lg, fontWeight: '700', color: colors.text, marginBottom: spacing.sm,
  },
  card: {
    backgroundColor: colors.surface, borderRadius: 12, padding: spacing.md,
    marginBottom: spacing.sm,
  },
  cardTitle: { fontSize: fontSizes.md, fontWeight: '700', color: colors.text },
  cardSub: { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: 2 },
  cardTags: { flexDirection: 'row', flexWrap: 'wrap', marginTop: spacing.sm, gap: spacing.xs },
  tag: {
    backgroundColor: colors.primary + '15', borderRadius: 6,
    paddingHorizontal: spacing.sm, paddingVertical: 2,
  },
  tagText: { fontSize: fontSizes.xs, color: colors.primary, fontWeight: '600' },
  cardContact: { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: spacing.xs },
  empty: { textAlign: 'center', color: colors.textLight, marginTop: spacing.xl },
});
