import React, { useState, useEffect } from 'react';
import {
  View, Text, StyleSheet, ScrollView,
  TouchableOpacity, ActivityIndicator,
} from 'react-native';
import { enseignantApi } from '../../api/endpoints';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

const JOURS = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];

export default function EnseignantPlanningScreen({ navigation }) {
  const [seances, setSeances]   = useState([]);
  const [loading, setLoading]   = useState(true);
  const [jourSelect, setJourSelect] = useState(new Date().toISOString().split('T')[0]);

  useEffect(() => {
    const fetchPlanning = async () => {
      try {
        const debut = new Date();
        debut.setDate(debut.getDate() - debut.getDay());
        const fin = new Date(debut);
        fin.setDate(fin.getDate() + 6);
        const res = await enseignantApi.planning({
          date_debut: debut.toISOString().split('T')[0],
          date_fin:   fin.toISOString().split('T')[0],
        });
        setSeances(res.data?.data?.seances || []);
      } catch (e) {
        console.error(e);
      } finally {
        setLoading(false);
      }
    };
    fetchPlanning();
  }, []);

  const seancesDuJour = seances.filter(s => s.date_seance === jourSelect);

  const semaineJours = Array.from({ length: 7 }, (_, i) => {
    const d = new Date();
    d.setDate(d.getDate() - d.getDay() + i);
    return { date: d.toISOString().split('T')[0], label: JOURS[i], jour: d.getDate() };
  });

  if (loading) return <View style={styles.center}><ActivityIndicator size="large" color={colors.primary} /></View>;

  return (
    <View style={styles.container}>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.jourSelector}>
        {semaineJours.map(j => (
          <TouchableOpacity
            key={j.date}
            style={[styles.jourBtn, jourSelect === j.date && styles.jourBtnActive]}
            onPress={() => setJourSelect(j.date)}
          >
            <Text style={[styles.jourLabel, jourSelect === j.date && { color: '#fff' }]}>{j.label}</Text>
            <Text style={[styles.jourNum,   jourSelect === j.date && { color: '#fff' }]}>{j.jour}</Text>
          </TouchableOpacity>
        ))}
      </ScrollView>

      <ScrollView style={styles.content}>
        {seancesDuJour.length === 0 ? (
          <View style={styles.empty}>
            <Text style={styles.emptyText}>🎉 Pas de cours ce jour</Text>
          </View>
        ) : (
          seancesDuJour.map((s, i) => (
            <TouchableOpacity
              key={i}
              style={[styles.seanceCard, { borderLeftColor: s.statut === 'terminée' ? colors.success : colors.primary }]}
              onPress={() => navigation.navigate('Presences', { seanceId: s.id, titreSeance: s.cours?.groupe?.nom })}
            >
              <View style={styles.seanceHeader}>
                <Text style={styles.seanceHeure}>{s.cours?.heure_debut} — {s.cours?.heure_fin}</Text>
                <View style={[styles.statutBadge, { backgroundColor: s.statut === 'terminée' ? '#d1fae5' : '#dbeafe' }]}>
                  <Text style={[styles.statutText, { color: s.statut === 'terminée' ? '#065f46' : '#1e3a8a' }]}>
                    {s.statut === 'terminée' ? '✅ Terminée' : '📅 Planifiée'}
                  </Text>
                </View>
              </View>
              <Text style={styles.seanceTitre}>{s.cours?.groupe?.nom} — {s.cours?.matiere?.nom_fr}</Text>
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
