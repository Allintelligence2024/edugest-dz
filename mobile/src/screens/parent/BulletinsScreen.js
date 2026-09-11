import React, { useState, useEffect, useCallback } from 'react';
import { View, Text, ScrollView, TouchableOpacity, ActivityIndicator, Linking, Alert, StyleSheet } from 'react-native';
import api from '../../api/axios';
import { bulletinsApi } from '../../api/endpoints';
import { useEnfants } from '../../context/EnfantContext';
import { colors, spacing, fontSizes } from '../../theme';

const TRIMESTRE_LABEL = { T1: '1er trimestre', T2: '2ᵉ trimestre', T3: '3ᵉ trimestre' };

/**
 * PILOTE P1-C2 — Bulletins parent : liste réelle + PDF via URL publique /storage.
 * GET /eleves/{id}/bulletins → data.{bulletins[{trimestre, annee_scolaire,
 * moyenne_generale, rang, effectif_classe, appreciation_gen, fichier_url, groupe}]}.
 * Post-pilote : URLs signées (P4 : storage:link + nginx).
 */
export default function BulletinsScreen() {
  const { enfantActif, loading: loadingEnfant } = useEnfants();
  const [bulletins, setBulletins] = useState([]);
  const [loading, setLoading] = useState(true);

  const charger = useCallback(async () => {
    if (!enfantActif) return;
    setLoading(true);
    try {
      const res = await bulletinsApi.byEleve(enfantActif.id);
      setBulletins(res?.data?.bulletins ?? []);
    } catch (e) {
      console.error(e);
      setBulletins([]);
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

  const ouvrirPdf = useCallback(async (b) => {
    if (!b.fichier_url) {
      Alert.alert('PDF indisponible', 'Le PDF de ce bulletin n\'a pas encore été généré.');
      return;
    }
    const base = (api.defaults.baseURL ?? '').replace(/\/api\/v1\/?$/, '');
    const url = `${base}/storage/${b.fichier_url}`;
    try {
      const ok = await Linking.canOpenURL(url);
      if (!ok) throw new Error('URL non ouvrable');
      await Linking.openURL(url);
    } catch (e) {
      console.error(e);
      Alert.alert('Erreur', 'Impossible d\'ouvrir le PDF. Vérifiez votre connexion.');
    }
  }, []);

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

  return (
    <ScrollView style={styles.container}>
      <Text style={styles.enfant}>{enfantActif.nom_complet}</Text>
      {bulletins.length === 0 ? (
        <View style={styles.empty}>
          <Text style={styles.emptyText}>Aucun bulletin publié pour le moment.</Text>
        </View>
      ) : (
        bulletins.map((b) => (
          <View key={b.id} testID={`bulletin-${b.id}`} style={styles.card}>
            <View style={styles.cardHeader}>
              <Text style={styles.trimestre}>{TRIMESTRE_LABEL[b.trimestre] ?? b.trimestre}</Text>
              <Text style={styles.annee}>{b.annee_scolaire}</Text>
            </View>
            {b.groupe?.nom ? <Text style={styles.groupe}>🏫 {b.groupe.nom}</Text> : null}
            <View style={styles.stats}>
              <View style={styles.stat}>
                <Text style={styles.statValeur}>
                  {b.moyenne_generale === null || b.moyenne_generale === undefined
                    ? '—' : Number(b.moyenne_generale).toFixed(2)}
                </Text>
                <Text style={styles.statLabel}>Moyenne</Text>
              </View>
              <View style={styles.stat}>
                <Text style={styles.statValeur}>
                  {b.rang ? `${b.rang}${b.effectif_classe ? `/${b.effectif_classe}` : ''}` : '—'}
                </Text>
                <Text style={styles.statLabel}>Rang</Text>
              </View>
            </View>
            {b.appreciation_gen ? <Text style={styles.app}>💬 {b.appreciation_gen}</Text> : null}
            <TouchableOpacity
              testID={`bulletin-pdf-${b.id}`}
              style={[styles.pdfBtn, !b.fichier_url && styles.pdfBtnDisabled]}
              onPress={() => ouvrirPdf(b)}
            >
              <Text style={styles.pdfBtnText}>
                {b.fichier_url ? '📄 Ouvrir le PDF' : '⏳ PDF en cours de génération'}
              </Text>
            </TouchableOpacity>
          </View>
        ))
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container:  { flex: 1, backgroundColor: colors.background, padding: spacing.md },
  center:     { flex: 1, justifyContent: 'center', alignItems: 'center' },
  enfant:     { fontSize: fontSizes.md, fontWeight: '600', color: colors.text, marginBottom: spacing.sm },
  empty:      { alignItems: 'center', paddingTop: 60 },
  emptyText:  { fontSize: fontSizes.md, color: colors.textSecondary },
  card:       { backgroundColor: colors.card, borderRadius: 14, padding: spacing.md, marginBottom: spacing.sm },
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  trimestre:  { fontSize: fontSizes.md, fontWeight: '700', color: colors.text },
  annee:      { fontSize: fontSizes.xs, color: colors.textSecondary },
  groupe:     { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: 2 },
  stats:      { flexDirection: 'row', marginTop: spacing.sm },
  stat:       { flex: 1, alignItems: 'center', backgroundColor: colors.background, borderRadius: 10, padding: spacing.sm, marginRight: 8 },
  statValeur: { fontSize: fontSizes.lg, fontWeight: '800', color: colors.primary },
  statLabel:  { fontSize: fontSizes.xs, color: colors.textSecondary },
  app:        { fontSize: fontSizes.sm, color: colors.textSecondary, fontStyle: 'italic', marginTop: spacing.sm },
  pdfBtn:     { backgroundColor: colors.primary, borderRadius: 10, padding: spacing.sm, alignItems: 'center', marginTop: spacing.sm },
  pdfBtnDisabled: { backgroundColor: colors.textSecondary },
  pdfBtnText: { color: '#fff', fontWeight: '700', fontSize: fontSizes.sm },
});
