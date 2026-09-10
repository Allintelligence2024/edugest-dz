import React, { useState, useEffect } from 'react';
import {
  View, Text, StyleSheet, FlatList,
  TouchableOpacity, ActivityIndicator, Alert,
} from 'react-native';
import { adminApi } from '../../api/endpoints';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

export default function AdminAbsencesScreen() {
  const [absences, setAbsences] = useState([]);
  const [stats, setStats]       = useState(null);
  const [loading, setLoading]   = useState(true);

  const today = new Date().toISOString().split('T')[0];

  const fetchAbsences = async () => {
    setLoading(true);
    try {
      const res = await adminApi.absences.jour({ date: today });
      setAbsences(res.data?.data || []);
      setStats(res.data?.meta?.stats);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchAbsences(); }, []);

  const marquerPresent = async (eleveId, nom) => {
    try {
      await adminApi.absences.marquerPresent(eleveId, { statut: 'present' });
      Alert.alert('✅', `${nom} marqué(e) présent(e)`);
      fetchAbsences();
    } catch (e) {
      Alert.alert('❌ Erreur', 'Impossible de modifier');
    }
  };

  if (loading) return <View style={styles.center}><ActivityIndicator size="large" color={colors.primary} /></View>;

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.titre}>Absences du jour</Text>
        <Text style={styles.date}>{new Date().toLocaleDateString('fr-DZ', { weekday: 'long', day: 'numeric', month: 'long' })}</Text>
      </View>

      {stats && (
        <View style={styles.statsRow}>
          {[
            { label: 'Absents',  value: stats.absents  ?? 0, color: '#ef4444' },
            { label: 'Présents', value: stats.presents ?? 0, color: '#10b981' },
            { label: 'Retards',  value: stats.retards  ?? 0, color: '#f59e0b' },
          ].map((s, i) => (
            <View key={i} style={[styles.statCard, { backgroundColor: s.color }]}>
              <Text style={styles.statValue}>{s.value}</Text>
              <Text style={styles.statLabel}>{s.label}</Text>
            </View>
          ))}
        </View>
      )}

      <FlatList
        data={absences}
        keyExtractor={item => item.id}
        contentContainerStyle={{ padding: spacing.md }}
        ListEmptyComponent={<Text style={styles.empty}>🎉 Aucune absence enregistrée</Text>}
        renderItem={({ item }) => {
          const eleve = item.eleve;
          const estAbsent = item.statut === 'absent';
          return (
            <View style={styles.absenceCard}>
              <View style={styles.absInfo}>
                <Text style={styles.absName}>{eleve?.prenom} {eleve?.nom}</Text>
                <Text style={styles.absLevel}>{eleve?.niveau_scolaire}</Text>
                <View style={[styles.statutBadge, { backgroundColor: estAbsent ? '#fee2e2' : '#fef3c7' }]}>
                  <Text style={{ color: estAbsent ? '#dc2626' : '#d97706', fontWeight: '600', fontSize: fontSizes.xs }}>
                    {estAbsent ? '❌ Absent' : '⏰ Retard'}
                  </Text>
                </View>
              </View>
              {estAbsent && (
                <TouchableOpacity
                  style={styles.presentBtn}
                  onPress={() => marquerPresent(eleve?.id, `${eleve?.prenom} ${eleve?.nom}`)}
                >
                  <Text style={styles.presentBtnText}>✅ Marquer présent</Text>
                </TouchableOpacity>
              )}
            </View>
          );
        }}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container:   { flex: 1, backgroundColor: colors.background },
  center:      { flex: 1, justifyContent: 'center', alignItems: 'center' },
  header:      { padding: spacing.lg, paddingBottom: spacing.sm },
  titre:       { fontSize: fontSizes.xl, fontWeight: '700', color: colors.text },
  date:        { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: 4 },
  statsRow:    { flexDirection: 'row', padding: spacing.md, gap: spacing.sm },
  statCard:    { flex: 1, borderRadius: 12, padding: spacing.sm, alignItems: 'center' },
  statValue:   { fontSize: fontSizes.xl, fontWeight: '800', color: '#fff' },
  statLabel:   { fontSize: fontSizes.xs, color: '#fff', opacity: 0.9 },
  absenceCard: { flexDirection: 'row', alignItems: 'center', backgroundColor: colors.card, borderRadius: 14, padding: spacing.md, marginBottom: spacing.sm },
  absInfo:     { flex: 1 },
  absName:     { fontSize: fontSizes.md, fontWeight: '600', color: colors.text },
  absLevel:    { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: 2 },
  statutBadge: { alignSelf: 'flex-start', borderRadius: 20, paddingHorizontal: 8, paddingVertical: 3, marginTop: 6 },
  presentBtn:  { backgroundColor: '#d1fae5', borderRadius: 10, paddingHorizontal: 12, paddingVertical: 8 },
  presentBtnText: { color: '#065f46', fontWeight: '600', fontSize: fontSizes.xs },
  empty:       { textAlign: 'center', color: colors.textSecondary, padding: 40, fontSize: fontSizes.md },
});
