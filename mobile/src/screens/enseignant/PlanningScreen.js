import React, { useState, useEffect, useCallback } from 'react';
import { View, Text, ScrollView, TouchableOpacity, ActivityIndicator, StyleSheet } from 'react-native';
import { enseignantApi } from '../../api/endpoints';
import { colors, spacing, fontSizes } from '../../theme';

const JOURS = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];

function iso(d) {
  return d.toISOString().split('T')[0];
}

/**
 * PILOTE P1-C5 — Planning enseignant réécrit sur GET /seances (instances avec id,
 * date_seance, statut), PAS sur /planning (canevas de cours récurrents sans id).
 * Limitation pilote assumée : toutes les séances de l'établissement (même tenant,
 * lecture seule). Scoping par enseignant connecté : P3.
 */
export default function PlanningScreen({ navigation }) {
  const [seances, setSeances] = useState([]);
  const [loading, setLoading] = useState(true);
  const [jourSelect, setJourSelect] = useState(iso(new Date()));

  const charger = useCallback(async () => {
    setLoading(true);
    try {
      const now = new Date();
      const debut = new Date(now);
      debut.setDate(now.getDate() - now.getDay());
      const fin = new Date(debut);
      fin.setDate(debut.getDate() + 6);
      const res = await enseignantApi.seances({ date_debut: iso(debut), date_fin: iso(fin) });
      setSeances(res?.data ?? []);
    } catch (e) {
      console.error(e);
      setSeances([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    charger();
  }, [charger]);

  const seancesDuJour = seances.filter(
    (s) => (s.date_seance ?? '').slice(0, 10) === jourSelect
  );

  const semaineJours = Array.from({ length: 7 }, (_, i) => {
    const d = new Date();
    d.setDate(d.getDate() - d.getDay() + i);
    return { date: iso(d), label: JOURS[d.getDay()], jour: d.getDate() };
  });

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.jourSelector}>
        {semaineJours.map((j) => (
          <TouchableOpacity
            key={j.date}
            testID={`jour-${j.date}`}
            style={[styles.jourBtn, jourSelect === j.date && styles.jourBtnActive]}
            onPress={() => setJourSelect(j.date)}
          >
            <Text style={[styles.jourLabel, jourSelect === j.date && { color: '#fff' }]}>{j.label}</Text>
            <Text style={[styles.jourNum, jourSelect === j.date && { color: '#fff' }]}>{j.jour}</Text>
          </TouchableOpacity>
        ))}
      </ScrollView>

      <ScrollView style={styles.content}>
        {seancesDuJour.length === 0 ? (
          <View style={styles.empty}>
            <Text style={styles.emptyText}>🎉 Pas de cours ce jour</Text>
          </View>
        ) : (
          seancesDuJour.map((s) => (
            <TouchableOpacity
              key={s.id}
              style={[
                styles.seanceCard,
                { borderLeftColor: s.statut === 'terminée' ? colors.success : colors.primary },
              ]}
              onPress={() => navigation.navigate('Presences', { seanceId: s.id, titreSeance: s.cours?.groupe?.nom })}
            >
              <View style={styles.seanceHeader}>
                <Text style={styles.seanceHeure}>
                  {(s.heure_debut ?? '').slice(0, 5)} — {(s.heure_fin ?? '').slice(0, 5)}
                </Text>
                <View
                  style={[
                    styles.statutBadge,
                    { backgroundColor: s.statut === 'terminée' ? '#d1fae5' : '#dbeafe' },
                  ]}
                >
                  <Text
                    style={[
                      styles.statutText,
                      { color: s.statut === 'terminée' ? '#065f46' : '#1e3a8a' },
                    ]}
                  >
                    {s.statut === 'terminée' ? '✅ Terminée' : '📅 Planifiée'}
                  </Text>
                </View>
              </View>
              <Text style={styles.seanceTitre}>
                {s.cours?.groupe?.nom} — {s.cours?.groupe?.matiere?.nom_fr}
              </Text>
              <Text style={styles.seanceSalle}>📍 {s.cours?.salle?.nom || 'Salle non définie'}</Text>
              {s.statut !== 'terminée' && (
                <Text style={styles.seanceAction}>👆 Appuyer pour saisir les présences</Text>
              )}
            </TouchableOpacity>
          ))
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container:      { flex: 1, backgroundColor: colors.background },
  center:         { flex: 1, justifyContent: 'center', alignItems: 'center' },
  jourSelector:   { paddingHorizontal: spacing.md, paddingVertical: spacing.sm, maxHeight: 80 },
  jourBtn:        { alignItems: 'center', paddingHorizontal: 14, paddingVertical: 8, borderRadius: 12, marginRight: 8, backgroundColor: colors.card },
  jourBtnActive:  { backgroundColor: colors.primary },
  jourLabel:      { fontSize: fontSizes.xs, color: colors.textSecondary },
  jourNum:        { fontSize: fontSizes.md, fontWeight: '700', color: colors.text },
  content:        { flex: 1, padding: spacing.md },
  empty:          { alignItems: 'center', paddingTop: 60 },
  emptyText:      { fontSize: fontSizes.md, color: colors.textSecondary },
  seanceCard:     { backgroundColor: colors.card, borderRadius: 14, padding: spacing.md, marginBottom: spacing.sm, borderLeftWidth: 4 },
  seanceHeader:   { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6 },
  seanceHeure:    { fontSize: fontSizes.sm, fontWeight: '700', color: colors.primary },
  statutBadge:    { borderRadius: 20, paddingHorizontal: 8, paddingVertical: 3 },
  statutText:     { fontSize: fontSizes.xs, fontWeight: '600' },
  seanceTitre:    { fontSize: fontSizes.md, fontWeight: '600', color: colors.text },
  seanceSalle:    { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: 4 },
  seanceAction:   { fontSize: fontSizes.xs, color: colors.primary, marginTop: 6, fontStyle: 'italic' },
});
