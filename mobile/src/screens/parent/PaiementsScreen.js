import React, { useState, useEffect, useCallback } from 'react';
import { View, Text, ScrollView, ActivityIndicator, StyleSheet } from 'react-native';
import { paiementsApi } from '../../api/endpoints';
import { useEnfants } from '../../context/EnfantContext';
import { colors, spacing, fontSizes } from '../../theme';

const STATUT_STYLE = {
  'payée':               { emoji: '✅', bg: '#d1fae5', fg: '#065f46' },
  'partiellement_payée': { emoji: '🟡', bg: '#fef3c7', fg: '#92400e' },
  'émise':               { emoji: '📄', bg: '#dbeafe', fg: '#1e3a8a' },
  'en_retard':           { emoji: '🔴', bg: '#fee2e2', fg: '#991b1b' },
  'annulée':             { emoji: '🚫', bg: '#f3f4f6', fg: '#6b7280' },
};

function fmt(n) {
  const v = Number(n);
  return Number.isFinite(v) ? `${v.toLocaleString('fr-FR')} DA` : '—';
}

/**
 * PILOTE P1-C4 — Paiements parent réécrit sur le contrat réel :
 * GET /eleves/{id}/paiements → data.{factures[], financier{total_paye, total_dette,
 * nb_factures, nb_impayes}}. Bouton SATIM/CIB supprimé : paiement au secrétariat.
 */
export default function PaiementsScreen() {
  const { enfantActif, loading: loadingEnfant } = useEnfants();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  const charger = useCallback(async () => {
    if (!enfantActif) return;
    setLoading(true);
    try {
      const res = await paiementsApi.byEleve(enfantActif.id);
      setData(res?.data ?? null);
    } catch (e) {
      console.error(e);
      setData(null);
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

  const factures = data?.factures ?? [];
  const fin = data?.financier ?? {};

  return (
    <ScrollView style={styles.container}>
      <View style={styles.resume}>
        <View style={styles.resumeCol}>
          <Text style={styles.resumeLabel}>Reste dû</Text>
          <Text testID="paiements-dette" style={[styles.resumeMontant, styles.dette]}>
            {fmt(fin.total_dette)}
          </Text>
        </View>
        <View style={styles.resumeCol}>
          <Text style={styles.resumeLabel}>Payé</Text>
          <Text style={[styles.resumeMontant, styles.paye]}>{fmt(fin.total_paye)}</Text>
        </View>
        <View style={styles.resumeCol}>
          <Text style={styles.resumeLabel}>Impayées</Text>
          <Text style={styles.resumeMontant}>{fin.nb_impayes ?? 0}</Text>
        </View>
      </View>

      <View style={styles.secretariat}>
        <Text style={styles.secretariatText}>
          💳 Le paiement en ligne n'est pas activé pendant le pilote. Merci de régler au secrétariat
          de l'établissement.
        </Text>
      </View>

      {factures.length === 0 ? (
        <View style={styles.empty}>
          <Text style={styles.emptyText}>Aucune facture pour {enfantActif.prenom}.</Text>
        </View>
      ) : (
        factures.map((f) => {
          const st = STATUT_STYLE[f.statut] ?? { emoji: '📄', bg: colors.border, fg: colors.text };
          const nbVersements = (f.paiements ?? []).length;
          return (
            <View key={f.id} testID={`facture-${f.id}`} style={styles.card}>
              <View style={styles.cardHeader}>
                <Text style={styles.numero}>{f.numero_facture}</Text>
                <View style={[styles.badge, { backgroundColor: st.bg }]}>
                  <Text style={[styles.badgeText, { color: st.fg }]}>
                    {st.emoji} {(f.statut ?? '').replace(/_/g, ' ')}
                  </Text>
                </View>
              </View>
              <Text style={styles.dates}>
                Émise le {(f.date_emission ?? '').slice(0, 10)}
                {f.date_echeance ? ` • Échéance ${(f.date_echeance ?? '').slice(0, 10)}` : ''}
              </Text>
              <View style={styles.cardFooter}>
                <Text style={styles.montant}>{fmt(f.total_ttc)}</Text>
                <Text style={styles.versements}>
                  {nbVersements === 0 ? 'Aucun versement' : `${nbVersements} versement(s)`}
                </Text>
              </View>
            </View>
          );
        })
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container:  { flex: 1, backgroundColor: colors.background, padding: spacing.md },
  center:     { flex: 1, justifyContent: 'center', alignItems: 'center' },
  resume:     { flexDirection: 'row', backgroundColor: colors.card, borderRadius: 14, padding: spacing.md, marginBottom: spacing.sm },
  resumeCol:  { flex: 1, alignItems: 'center' },
  resumeLabel:{ fontSize: fontSizes.xs, color: colors.textSecondary },
  resumeMontant: { fontSize: fontSizes.md, fontWeight: '800', color: colors.text, marginTop: 2 },
  dette:      { color: colors.danger },
  paye:       { color: colors.success },
  secretariat:{ backgroundColor: '#dbeafe', borderRadius: 12, padding: spacing.md, marginBottom: spacing.sm },
  secretariatText: { fontSize: fontSizes.sm, color: '#1e3a8a' },
  empty:      { alignItems: 'center', paddingTop: 40 },
  emptyText:  { fontSize: fontSizes.md, color: colors.textSecondary },
  card:       { backgroundColor: colors.card, borderRadius: 14, padding: spacing.md, marginBottom: spacing.sm },
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  numero:     { fontSize: fontSizes.md, fontWeight: '700', color: colors.text },
  badge:      { borderRadius: 20, paddingHorizontal: 10, paddingVertical: 4 },
  badgeText:  { fontSize: fontSizes.xs, fontWeight: '700' },
  dates:      { fontSize: fontSizes.xs, color: colors.textSecondary, marginTop: 4 },
  cardFooter: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 8 },
  montant:    { fontSize: fontSizes.lg, fontWeight: '800', color: colors.text },
  versements: { fontSize: fontSizes.xs, color: colors.textSecondary },
});
