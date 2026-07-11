import React, { useState, useEffect } from 'react';
import {
  View, Text, ScrollView, TouchableOpacity,
  StyleSheet, ActivityIndicator, RefreshControl, Alert,
} from 'react-native';
import { useAuth } from '../../context/AuthContext';
import { adminApi } from '../../api/endpoints';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

const NIVEAU_CONFIG = {
  critique: { bg: 'rgba(239,68,68,0.15)', border: '#ef4444', text: '#f87171', label: '🔴 Critique' },
  eleve:    { bg: 'rgba(249,115,22,0.15)', border: '#f97316', text: '#fb923c', label: '🟠 Élevé' },
  modere:   { bg: 'rgba(234,179,8,0.15)',  border: '#eab308', text: '#ca8a04', label: '🟡 Modéré' },
  faible:   { bg: 'rgba(34,197,94,0.15)',  border: '#22c55e', text: '#16a34a', label: '🟢 Faible' },
};

export default function AdminIAScreen() {
  const { token } = useAuth();
  const [classement, setClassement] = useState([]);
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [filtre, setFiltre] = useState('tous');

  const charger = async () => {
    try {
      const res = await adminApi.predictions.classement(token);
      setClassement(res?.classement ?? []);
      setStats(res?.stats ?? null);
    } catch (e) {
      Alert.alert('Erreur', 'Impossible de charger les prédictions IA');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => { charger(); }, []);

  const filtered = filtre === 'tous'
    ? classement
    : classement.filter(e => e.niveau_risque === filtre);

  if (loading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={styles.loadingText}>Calcul des prédictions IA...</Text>
      </View>
    );
  }

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); charger(); }} tintColor={colors.primary} />
      }
    >
      <View style={styles.header}>
        <Text style={styles.headerTitle}>🧠 Prédiction IA</Text>
        <Text style={styles.headerSub}>Risque échec scolaire · Modèle PHP local</Text>
      </View>

      {stats && (
        <View style={styles.statsRow}>
          {[
            { label: 'Critique', val: stats.critique ?? 0, color: '#ef4444' },
            { label: 'Élevé',    val: stats.eleve    ?? 0, color: '#f97316' },
            { label: 'Modéré',   val: stats.modere   ?? 0, color: '#eab308' },
            { label: 'Faible',   val: stats.faible   ?? 0, color: '#22c55e' },
          ].map(s => (
            <TouchableOpacity
              key={s.label}
              style={[styles.statCard, { borderTopColor: s.color }]}
              onPress={() => setFiltre(s.label.toLowerCase() === 'élevé' ? 'eleve' : s.label.toLowerCase())}
            >
              <Text style={[styles.statVal, { color: s.color }]}>{s.val}</Text>
              <Text style={styles.statLabel}>{s.label}</Text>
            </TouchableOpacity>
          ))}
        </View>
      )}

      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.filtresRow} contentContainerStyle={styles.filtresContent}>
        {['tous', 'critique', 'eleve', 'modere', 'faible'].map(f => (
          <TouchableOpacity
            key={f}
            onPress={() => setFiltre(f)}
            style={[styles.filtreBtn, filtre === f && styles.filtreBtnActive]}
          >
            <Text style={[styles.filtreTxt, filtre === f && styles.filtreTxtActive]}>
              {f === 'tous' ? `Tous (${classement.length})` : (NIVEAU_CONFIG[f]?.label ?? f)}
            </Text>
          </TouchableOpacity>
        ))}
      </ScrollView>

      <View style={styles.liste}>
        {filtered.length === 0 ? (
          <View style={styles.emptyState}>
            <Text style={styles.emptyIcon}>🎓</Text>
            <Text style={styles.emptyText}>Aucun élève dans cette catégorie</Text>
            <Text style={styles.emptySub}>Lancez un recalcul depuis le tableau de bord web</Text>
          </View>
        ) : (
          filtered.map((eleve, idx) => {
            const nc = NIVEAU_CONFIG[eleve.niveau_risque] ?? NIVEAU_CONFIG.faible;
            const facteurs = typeof eleve.facteurs_risque === 'string'
              ? JSON.parse(eleve.facteurs_risque)
              : (eleve.facteurs_risque ?? []);

            return (
              <View key={eleve.eleve_id ?? idx} style={[styles.eleveCard, { borderLeftColor: nc.border }]}>
                <View style={styles.eleveTop}>
                  <View style={styles.eleveInfo}>
                    <Text style={styles.eleveNom}>{eleve.eleve_nom}</Text>
                    {facteurs[0] && (
                      <Text style={styles.facteur} numberOfLines={1}>
                        {facteurs[0].icone ?? '⚠️'} {facteurs[0].label}
                      </Text>
                    )}
                  </View>
                  <View style={styles.probaBox}>
                    <Text style={[styles.probaVal, { color: nc.border }]}>
                      {Math.round(eleve.probabilite_echec ?? 0)}%
                    </Text>
                    <View style={[styles.niveauBadge, { backgroundColor: nc.bg, borderColor: nc.border }]}>
                      <Text style={[styles.niveauText, { color: nc.text }]}>{nc.label}</Text>
                    </View>
                  </View>
                </View>
              </View>
            );
          })
        )}
      </View>

      <View style={styles.disclaimer}>
        <Text style={styles.disclaimerText}>
          🔒 Modèle IA local · Données non transmises · Conforme loi 18-07
        </Text>
      </View>

      <View style={styles.bottomSpacer} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container:      { flex: 1, backgroundColor: colors.background },
  centered:       { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.background },
  loadingText:    { color: colors.textSecondary, marginTop: 12, fontSize: fontSizes.sm },
  header:         { padding: spacing.lg, paddingTop: spacing.sm },
  headerTitle:    { fontSize: fontSizes.xl, fontWeight: '900', color: colors.text },
  headerSub:      { fontSize: fontSizes.xs, color: colors.textSecondary, marginTop: 4 },
  statsRow:       { flexDirection: 'row', paddingHorizontal: 12, gap: 8, marginBottom: 14 },
  statCard:       { flex: 1, backgroundColor: colors.card, borderRadius: 12, padding: 12, alignItems: 'center', borderTopWidth: 3, borderWidth: 1, borderColor: colors.border },
  statVal:        { fontSize: 22, fontWeight: '900' },
  statLabel:      { fontSize: 10, color: colors.textSecondary, marginTop: 2 },
  filtresRow:     { marginBottom: 12 },
  filtresContent: { paddingHorizontal: 16, gap: 8, flexDirection: 'row' },
  filtreBtn:      { paddingHorizontal: 14, paddingVertical: 7, borderRadius: 20, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.border },
  filtreBtnActive:{ backgroundColor: colors.primary, borderColor: colors.primary },
  filtreTxt:      { fontSize: 12, fontWeight: '600', color: colors.textSecondary },
  filtreTxtActive:{ color: '#fff' },
  liste:          { paddingHorizontal: 16, gap: 10, marginBottom: 16 },
  emptyState:     { alignItems: 'center', paddingVertical: 40, backgroundColor: colors.card, borderRadius: 16, borderWidth: 1, borderColor: colors.border },
  emptyIcon:      { fontSize: 40, marginBottom: 10 },
  emptyText:      { fontSize: fontSizes.sm, fontWeight: '700', color: colors.textSecondary },
  emptySub:       { fontSize: fontSizes.xs, color: colors.textSecondary, marginTop: 4, textAlign: 'center', paddingHorizontal: 20 },
  eleveCard:      { backgroundColor: colors.card, borderRadius: 14, padding: 14, borderWidth: 1, borderColor: colors.border, borderLeftWidth: 4 },
  eleveTop:       { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' },
  eleveInfo:      { flex: 1, marginRight: 10 },
  eleveNom:       { fontSize: 14, fontWeight: '800', color: colors.text, marginBottom: 4 },
  facteur:        { fontSize: 11, color: colors.textSecondary },
  probaBox:       { alignItems: 'flex-end', flexShrink: 0 },
  probaVal:       { fontSize: 22, fontWeight: '900' },
  niveauBadge:    { borderRadius: 10, paddingHorizontal: 8, paddingVertical: 2, borderWidth: 1, marginTop: 4 },
  niveauText:     { fontSize: 10, fontWeight: '700' },
  disclaimer:     { marginHorizontal: 16, marginBottom: 8 },
  disclaimerText: { fontSize: 10, color: colors.textSecondary, textAlign: 'center' },
  bottomSpacer:   { height: 40 },
});
