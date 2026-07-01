import React, { useState, useEffect } from 'react';
import {
  View, Text, StyleSheet, FlatList,
  TouchableOpacity, ActivityIndicator, Alert,
} from 'react-native';
import { enseignantApi } from '../../api/endpoints';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

const STATUTS = [
  { value: 'présent', label: '✅ Présent',  color: '#10b981' },
  { value: 'absent',  label: '❌ Absent',   color: '#ef4444' },
  { value: 'retard',  label: '⏰ Retard',   color: '#f59e0b' },
  { value: 'excusé',  label: '📝 Excusé',   color: '#6366f1' },
];

export default function PresencesScreen({ route }) {
  const { seanceId, titreSeance } = route.params || {};
  const [eleves, setEleves]       = useState([]);
  const [presences, setPresences] = useState({});
  const [loading, setLoading]     = useState(true);
  const [saving, setSaving]       = useState(false);

  useEffect(() => {
    if (!seanceId) return;
    const fetch = async () => {
      try {
        const res = await enseignantApi.presences.parSeance(seanceId);
        const data = res.data?.data || [];
        const map = {};
        data.forEach(p => { map[p.eleve_id] = p.statut || 'présent'; });
        const elevesListe = data.map(p => p.eleve).filter(Boolean);
        setEleves(elevesListe);
        setPresences(map);
      } catch (e) {
        console.error(e);
      } finally {
        setLoading(false);
      }
    };
    fetch();
  }, [seanceId]);

  const toggleStatut = (eleveId, statut) => {
    setPresences(prev => ({ ...prev, [eleveId]: statut }));
  };

  const sauvegarder = async () => {
    setSaving(true);
    try {
      const data = Object.entries(presences).map(([eleve_id, statut]) => ({ eleve_id, statut }));
      await enseignantApi.presences.saisir(seanceId, { presences: data });
      Alert.alert('✅ Succès', 'Présences enregistrées');
    } catch (e) {
      Alert.alert('❌ Erreur', 'Impossible d\'enregistrer');
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <View style={styles.center}><ActivityIndicator size="large" color={colors.primary} /></View>;

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.titre}>{titreSeance || 'Présences'}</Text>
        <Text style={styles.sous}>{eleves.length} élève(s)</Text>
      </View>

      <FlatList
        data={eleves}
        keyExtractor={item => item.id}
        contentContainerStyle={{ padding: spacing.md }}
        renderItem={({ item }) => (
          <View style={styles.eleveCard}>
            <Text style={styles.eleveName}>{item.prenom} {item.nom}</Text>
            <View style={styles.statutsRow}>
              {STATUTS.map(s => (
                <TouchableOpacity
                  key={s.value}
                  style={[
                    styles.statutBtn,
                    { borderColor: s.color },
                    presences[item.id] === s.value && { backgroundColor: s.color },
                  ]}
                  onPress={() => toggleStatut(item.id, s.value)}
                >
                  <Text style={[
                    styles.statutBtnText,
                    presences[item.id] === s.value && { color: '#fff' },
                  ]}>{s.label}</Text>
                </TouchableOpacity>
              ))}
            </View>
          </View>
        )}
      />

      <TouchableOpacity
        style={[styles.saveBtn, saving && styles.saveBtnDisabled]}
        onPress={sauvegarder}
        disabled={saving}
      >
        <Text style={styles.saveBtnText}>
          {saving ? '⏳ Enregistrement...' : '💾 Enregistrer les présences'}
        </Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container:      { flex: 1, backgroundColor: colors.background },
  center:         { flex: 1, justifyContent: 'center', alignItems: 'center' },
  header:         { padding: spacing.lg, backgroundColor: colors.primary },
  titre:          { fontSize: fontSizes.lg, fontWeight: '700', color: '#fff' },
  sous:           { fontSize: fontSizes.sm, color: 'rgba(255,255,255,0.8)', marginTop: 4 },
  eleveCard:      { backgroundColor: colors.card, borderRadius: 14, padding: spacing.md, marginBottom: spacing.sm },
  eleveName:      { fontSize: fontSizes.md, fontWeight: '600', color: colors.text, marginBottom: 10 },
  statutsRow:     { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  statutBtn:      { borderWidth: 1.5, borderRadius: 20, paddingHorizontal: 10, paddingVertical: 5 },
  statutBtnText:  { fontSize: fontSizes.xs, fontWeight: '600', color: colors.text },
  saveBtn:        { margin: spacing.md, backgroundColor: colors.primary, borderRadius: 14, padding: spacing.md, alignItems: 'center' },
  saveBtnDisabled:{ opacity: 0.6 },
  saveBtnText:    { color: '#fff', fontWeight: '700', fontSize: fontSizes.md },
});
