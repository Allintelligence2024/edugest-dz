import React, { useState, useEffect, useCallback } from 'react';
import { View, Text, FlatList, ActivityIndicator, StyleSheet } from 'react-native';
import { presencesApi } from '../../api/endpoints';
import { useEnfants } from '../../context/EnfantContext';
import { colors, spacing, fontSizes } from '../../theme';

const STATUT_STYLE = {
  'présent': { emoji: '✅', bg: '#d1fae5', fg: '#065f46' },
  'absent':  { emoji: '❌', bg: '#fee2e2', fg: '#991b1b' },
  'retard':  { emoji: '⏰', bg: '#fef3c7', fg: '#92400e' },
  'excusé':  { emoji: '📝', bg: '#e0e7ff', fg: '#3730a3' },
};

/**
 * PILOTE P1 — Présences parent réécrit sur le contrat réel :
 * GET /eleves/{id}/presences → data.[{statut, motif, heure_arrivee, created_at,
 * seance.{cours.{groupe.{matiere.{nom_fr}}, enseignant}}}], meta.stats.
 */
export default function PresencesScreen() {
  const { enfantActif, loading: loadingEnfant } = useEnfants();
  const [items, setItems] = useState([]);
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);

  const charger = useCallback(async () => {
    if (!enfantActif) return;
    setLoading(true);
    try {
      const res = await presencesApi.byEleve(enfantActif.id, { per_page: 50 });
      setItems(res?.data ?? []);
      setStats(res?.meta?.stats ?? null);
    } catch (e) {
      console.error(e);
      setItems([]);
      setStats(null);
    } finally {
      setLoading(false);
    }
  }, [enfantActif?.id]);

  useEffect(() => {
    if (!loadingEnfant) {
      if (enfantActif) charger();
      else setLoading(false);
    }
  }, [loadingEnfant, enfantActif?.id, charger]);

  if (loadingEnfant || loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  if (!enfantActif) {
    return (
      <View style={styles.center}>
        <Text style={styles.emptyText}>Aucun enfant rattaché à ce compte.</Text>
      </View>
    );
  }

  const renderItem = ({ item }) => {
    const st = STATUT_STYLE[item.statut] ?? { emoji: '❓', bg: colors.border, fg: colors.text };
    const matiere = item.seance?.cours?.groupe?.matiere?.nom_fr ?? 'Matière';
    const ens = item.seance?.cours?.enseignant;
    return (
      <View style={styles.card}>
        <View style={styles.row}>
          <View style={styles.info}>
            <Text style={styles.matiere}>{matiere}</Text>
            <Text style={styles.date}>
              {(item.created_at ?? '').slice(0, 10)}
              {item.heure_arrivee ? ` — arrivée ${String(item.heure_arrivee).slice(0, 5)}` : ''}
            </Text>
            {ens ? <Text style={styles.ens}>{ens.prenom} {ens.nom}</Text> : null}
            {item.motif ? <Text style={styles.motif}>💬 {item.motif}</Text> : null}
          </View>
          <View style={[styles.badge, { backgroundColor: st.bg }]}>
            <Text style={[styles.badgeText, { color: st.fg }]}>{st.emoji} {item.statut}</Text>
          </View>
        </View>
      </View>
    );
  };

  return (
    <View style={styles.container}>
      {stats && (
        <View style={styles.header}>
          <Text testID="presences-taux" style={styles.taux}>{stats.taux ?? '—'}%</Text>
          <Text style={styles.sousTitre}>
            {stats.presents ?? 0} présent(s) • {stats.absents ?? 0} absent(s) • {enfantActif.prenom}
          </Text>
        </View>
      )}
      <FlatList
        data={items}
        keyExtractor={(item, i) => String(item.id ?? i)}
        renderItem={renderItem}
        contentContainerStyle={styles.list}
        ListEmptyComponent={<Text style={styles.emptyText}>Aucune présence enregistrée.</Text>}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  center:    { flex: 1, justifyContent: 'center', alignItems: 'center' },
  header:    { alignItems: 'center', paddingVertical: spacing.md },
  taux:      { fontSize: 36, fontWeight: '800', color: colors.primary },
  sousTitre: { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: 2 },
  list:      { padding: spacing.md, paddingTop: 0 },
  card:      { backgroundColor: colors.card, borderRadius: 14, padding: spacing.md, marginBottom: spacing.sm },
  row:       { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' },
  info:      { flex: 1, marginRight: 8 },
  matiere:   { fontSize: fontSizes.md, fontWeight: '700', color: colors.text },
  date:      { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: 2 },
  ens:       { fontSize: fontSizes.xs, color: colors.textSecondary },
  motif:     { fontSize: fontSizes.xs, color: colors.textSecondary, fontStyle: 'italic', marginTop: 2 },
  badge:     { borderRadius: 20, paddingHorizontal: 10, paddingVertical: 4 },
  badgeText: { fontSize: fontSizes.xs, fontWeight: '700' },
  emptyText: { fontSize: fontSizes.md, color: colors.textSecondary, textAlign: 'center', marginTop: 40 },
});
