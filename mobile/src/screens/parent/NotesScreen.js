import React, { useState, useEffect, useCallback } from 'react';
import { View, Text, ScrollView, ActivityIndicator, StyleSheet } from 'react-native';
import { notesApi } from '../../api/endpoints';
import { useEnfants } from '../../context/EnfantContext';
import { colors, spacing, fontSizes } from '../../theme';

const TYPE_LABEL = { devoir: '📝 Devoir', composition: '📋 Composition', interrogation: '✏️ Interro' };

/**
 * PILOTE P1 — Notes parent réécrit sur le contrat réel :
 * GET /eleves/{id}/notes → data.{notes[{matiere, couleur, coefficient, notes[], moyenne}],
 * moyenne_generale, taux_presence}. Plus de calcul local, plus de user.eleve_id.
 */
export default function NotesScreen() {
  const { enfantActif, loading: loadingEnfant } = useEnfants();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  const charger = useCallback(async () => {
    if (!enfantActif) return;
    setLoading(true);
    try {
      const res = await notesApi.byEleve(enfantActif.id);
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

  const groupes = data?.notes ?? [];
  const moyenne = data?.moyenne_generale;

  return (
    <ScrollView style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.moyenneLabel}>Moyenne générale</Text>
        <Text testID="notes-moyenne" style={styles.moyenne}>
          {moyenne === null || moyenne === undefined ? '—' : Number(moyenne).toFixed(2)}
        </Text>
        <Text style={styles.enfant}>{enfantActif.nom_complet}</Text>
      </View>

      {groupes.length === 0 ? (
        <View style={styles.empty}>
          <Text style={styles.emptyText}>Aucune note pour le moment.</Text>
        </View>
      ) : (
        groupes.map((g, i) => (
          <View key={i} testID={`matiere-${i}`} style={styles.card}>
            <View style={styles.cardHeader}>
              <View style={[styles.dot, { backgroundColor: g.couleur || colors.primary }]} />
              <Text style={styles.matiere}>{g.matiere}</Text>
              <Text style={styles.coef}>coef. {g.coefficient}</Text>
            </View>
            {g.notes.map((n) => (
              <View key={n.id} style={styles.noteRow}>
                <View style={styles.noteInfo}>
                  <Text style={styles.noteType}>{TYPE_LABEL[n.type] ?? n.type}</Text>
                  <Text style={styles.noteDate}>{(n.date ?? '').slice(0, 10)}</Text>
                  {n.appreciation ? <Text style={styles.noteApp}>{n.appreciation}</Text> : null}
                </View>
                {n.absent ? (
                  <Text style={styles.absent}>ABSENT</Text>
                ) : (
                  <Text style={styles.note}>
                    {n.note}<Text style={styles.sur}>/{n.note_sur}</Text>
                  </Text>
                )}
              </View>
            ))}
            <Text style={styles.moyenneMatiere}>
              Moyenne : {g.moyenne === null || g.moyenne === undefined ? '—' : Number(g.moyenne).toFixed(2)}
            </Text>
          </View>
        ))
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container:  { flex: 1, backgroundColor: colors.background, padding: spacing.md },
  center:     { flex: 1, justifyContent: 'center', alignItems: 'center' },
  header:     { alignItems: 'center', marginBottom: spacing.md },
  moyenneLabel: { fontSize: fontSizes.sm, color: colors.textSecondary },
  moyenne:    { fontSize: 40, fontWeight: '800', color: colors.primary },
  enfant:     { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: 4 },
  empty:      { alignItems: 'center', paddingTop: 60 },
  emptyText:  { fontSize: fontSizes.md, color: colors.textSecondary },
  card:       { backgroundColor: colors.card, borderRadius: 14, padding: spacing.md, marginBottom: spacing.sm },
  cardHeader: { flexDirection: 'row', alignItems: 'center', marginBottom: 8 },
  dot:        { width: 12, height: 12, borderRadius: 6, marginRight: 8 },
  matiere:    { fontSize: fontSizes.md, fontWeight: '700', color: colors.text, flex: 1 },
  coef:       { fontSize: fontSizes.xs, color: colors.textSecondary },
  noteRow:    { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingVertical: 8, borderTopWidth: 1, borderTopColor: colors.border },
  noteInfo:   { flex: 1 },
  noteType:   { fontSize: fontSizes.sm, fontWeight: '600', color: colors.text },
  noteDate:   { fontSize: fontSizes.xs, color: colors.textSecondary },
  noteApp:    { fontSize: fontSizes.xs, color: colors.textSecondary, fontStyle: 'italic' },
  note:       { fontSize: fontSizes.lg, fontWeight: '800', color: colors.text },
  sur:        { fontSize: fontSizes.sm, fontWeight: '400', color: colors.textSecondary },
  absent:     { fontSize: fontSizes.sm, fontWeight: '800', color: colors.danger },
  moyenneMatiere: { fontSize: fontSizes.sm, fontWeight: '700', color: colors.primary, marginTop: 6, textAlign: 'right' },
});
