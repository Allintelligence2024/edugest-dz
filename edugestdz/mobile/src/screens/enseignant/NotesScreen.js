import React, { useState, useEffect } from 'react';
import {
  View, Text, StyleSheet, FlatList, TextInput,
  TouchableOpacity, ActivityIndicator, Alert,
} from 'react-native';
import { enseignantApi } from '../../api/endpoints';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

export default function NotesScreen() {
  const [evaluations, setEvaluations] = useState([]);
  const [evalSelect, setEvalSelect]   = useState(null);
  const [notes, setNotes]             = useState({});
  const [loading, setLoading]         = useState(true);
  const [saving, setSaving]           = useState(false);

  useEffect(() => {
    enseignantApi.evaluations.list().then(res => {
      setEvaluations(res.data?.data || []);
    }).catch(console.error).finally(() => setLoading(false));
  }, []);

  const selectionnerEval = async (eval_) => {
    setEvalSelect(eval_);
    try {
      const res = await enseignantApi.evaluations.notes(eval_.id);
      const map = {};
      (res.data?.data || []).forEach(n => { map[n.eleve_id] = n.note?.toString() || ''; });
      setNotes(map);
    } catch (e) {
      console.error(e);
    }
  };

  const sauvegarder = async () => {
    if (!evalSelect) return;
    setSaving(true);
    try {
      const data = Object.entries(notes).map(([eleve_id, note]) => ({
        eleve_id, note: parseFloat(note) || 0,
      }));
      await enseignantApi.evaluations.saisirNotes(evalSelect.id, { notes: data });
      Alert.alert('✅ Succès', 'Notes enregistrées');
    } catch (e) {
      Alert.alert('❌ Erreur', 'Impossible d\'enregistrer les notes');
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <View style={styles.center}><ActivityIndicator size="large" color={colors.primary} /></View>;

  if (!evalSelect) {
    return (
      <View style={styles.container}>
        <Text style={styles.titre}>Choisir une évaluation</Text>
        <FlatList
          data={evaluations}
          keyExtractor={item => item.id}
          contentContainerStyle={{ padding: spacing.md }}
          renderItem={({ item }) => (
            <TouchableOpacity style={styles.evalCard} onPress={() => selectionnerEval(item)}>
              <Text style={styles.evalTitre}>{item.titre}</Text>
              <Text style={styles.evalSous}>{item.groupe?.nom} · {item.type_eval} · /{item.note_max}</Text>
            </TouchableOpacity>
          )}
          ListEmptyComponent={<Text style={styles.empty}>Aucune évaluation disponible</Text>}
        />
      </View>
    );
  }

  const eleves = evalSelect.eleves || Object.keys(notes).map(id => ({ id }));

  return (
    <View style={styles.container}>
      <TouchableOpacity style={styles.backBtn} onPress={() => setEvalSelect(null)}>
        <Text style={styles.backText}>← Changer d'évaluation</Text>
      </TouchableOpacity>
      <View style={styles.evalHeader}>
        <Text style={styles.evalHeaderTitre}>{evalSelect.titre}</Text>
        <Text style={styles.evalHeaderSous}>Note sur {evalSelect.note_max} · Coeff. {evalSelect.coefficient}</Text>
      </View>
      <FlatList
        data={eleves}
        keyExtractor={item => item.id}
        contentContainerStyle={{ padding: spacing.md }}
        renderItem={({ item }) => (
          <View style={styles.noteRow}>
            <Text style={styles.eleveName}>{item.prenom} {item.nom}</Text>
            <TextInput
              style={styles.noteInput}
              value={notes[item.id] || ''}
              onChangeText={v => setNotes(prev => ({ ...prev, [item.id]: v }))}
              keyboardType="decimal-pad"
              placeholder={`/  ${evalSelect.note_max}`}
              placeholderTextColor={colors.textLight}
            />
          </View>
        )}
      />
      <TouchableOpacity
        style={[styles.saveBtn, saving && { opacity: 0.6 }]}
        onPress={sauvegarder}
        disabled={saving}
      >
        <Text style={styles.saveBtnText}>{saving ? '⏳ Enregistrement...' : '💾 Sauvegarder les notes'}</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container:       { flex: 1, backgroundColor: colors.background },
  center:          { flex: 1, justifyContent: 'center', alignItems: 'center' },
  titre:           { fontSize: fontSizes.lg, fontWeight: '700', color: colors.text, padding: spacing.lg },
  evalCard:        { backgroundColor: colors.card, borderRadius: 14, padding: spacing.md, marginBottom: spacing.sm },
  evalTitre:       { fontSize: fontSizes.md, fontWeight: '600', color: colors.text },
  evalSous:        { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: 4 },
  empty:           { textAlign: 'center', color: colors.textSecondary, padding: 40 },
  backBtn:         { padding: spacing.md, paddingBottom: 0 },
  backText:        { color: colors.primary, fontWeight: '600' },
  evalHeader:      { padding: spacing.md, backgroundColor: colors.primary + '15', margin: spacing.md, borderRadius: 12 },
  evalHeaderTitre: { fontWeight: '700', fontSize: fontSizes.md, color: colors.text },
  evalHeaderSous:  { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: 4 },
  noteRow:         { flexDirection: 'row', alignItems: 'center', backgroundColor: colors.card, borderRadius: 12, padding: spacing.md, marginBottom: spacing.sm },
  eleveName:       { flex: 1, fontSize: fontSizes.sm, fontWeight: '600', color: colors.text },
  noteInput:       { width: 80, borderWidth: 1.5, borderColor: colors.primary, borderRadius: 10, padding: 8, textAlign: 'center', fontSize: fontSizes.md, fontWeight: '700', color: colors.text },
  saveBtn:         { margin: spacing.md, backgroundColor: colors.primary, borderRadius: 14, padding: spacing.md, alignItems: 'center' },
  saveBtnText:     { color: '#fff', fontWeight: '700', fontSize: fontSizes.md },
});
