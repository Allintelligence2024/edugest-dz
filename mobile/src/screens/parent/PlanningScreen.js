import React, { useState, useEffect, useCallback } from 'react';
import { View, Text, ScrollView, ActivityIndicator, StyleSheet } from 'react-native';
import { planningApi } from '../../api/endpoints';
import { useEnfants } from '../../context/EnfantContext';
import { colors, spacing, fontSizes } from '../../theme';

function cap(s) {
  return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
}

/**
 * PILOTE P1-C5 — Planning parent : GET /planning?eleve_id={id} (scopé inscriptions
 * validées côté backend) → data.[{date, jour, seances:[cours]}] groupé par jour.
 */
export default function PlanningScreen() {
  const { enfantActif, loading: loadingEnfant } = useEnfants();
  const [jours, setJours] = useState([]);
  const [loading, setLoading] = useState(true);

  const charger = useCallback(async () => {
    if (!enfantActif) return;
    setLoading(true);
    try {
      const res = await planningApi.list({ eleve_id: enfantActif.id });
      setJours(res?.data ?? []);
    } catch (e) {
      console.error(e);
      setJours([]);
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

  return (
    <ScrollView style={styles.container}>
      <Text style={styles.enfant}>📅 Semaine de {enfantActif.prenom}</Text>
      {jours.length === 0 ? (
        <View style={styles.empty}>
          <Text style={styles.emptyText}>Aucun cours cette semaine.</Text>
        </View>
      ) : (
        jours.map((j) => (
          <View key={j.date} testID={`jour-${j.date}`} style={styles.jourCard}>
            <Text style={styles.jourTitre}>{cap(j.jour)} <Text style={styles.jourDate}>{j.date}</Text></Text>
            {(j.seances ?? []).map((s) => (
              <View key={s.id} style={styles.seance}>
                <Text style={styles.heure}>
                  {(s.heure_debut ?? '').slice(0, 5)} — {(s.heure_fin ?? '').slice(0, 5)}
                </Text>
                <Text style={styles.matiere}>
                  {s.groupe?.matiere?.nom_fr ?? s.groupe?.nom ?? 'Cours'}
                </Text>
                <Text style={styles.detail}>
                  {s.enseignant ? `👩‍🏫 ${s.enseignant.prenom} ${s.enseignant.nom}` : ''}
                  {s.salle?.nom ? `  📍 ${s.salle.nom}` : ''}
                </Text>
              </View>
            ))}
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
  jourCard:   { backgroundColor: colors.card, borderRadius: 14, padding: spacing.md, marginBottom: spacing.sm },
  jourTitre:  { fontSize: fontSizes.md, fontWeight: '700', color: colors.primary, marginBottom: 8 },
  jourDate:   { fontSize: fontSizes.xs, fontWeight: '400', color: colors.textSecondary },
  seance:     { borderTopWidth: 1, borderTopColor: colors.border, paddingVertical: 8 },
  heure:      { fontSize: fontSizes.sm, fontWeight: '700', color: colors.text },
  matiere:    { fontSize: fontSizes.md, fontWeight: '600', color: colors.text, marginTop: 2 },
  detail:     { fontSize: fontSizes.xs, color: colors.textSecondary, marginTop: 2 },
});
