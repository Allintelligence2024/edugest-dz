import React, { useState, useEffect, useCallback } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, TextInput,
  StyleSheet, ActivityIndicator, Alert,
} from 'react-native';
import { enseignantApi } from '../../api/endpoints';

// PILOTE-30OCT (P1-C1) — réécriture data-layer : cet écran utilisait une URL de
// prod codée en dur + AsyncStorage + header X-Tenant-ID maison, contournant
// AuthContext (SecureStore), le refresh sérialisé et le tenant fail-closed.
// Il passe désormais par le client API officiel (intercepteur auth + refresh).

export default function NotesScreen() {
  const [groupes, setGroupes]         = useState([]);
  const [selectedGroupe, setSelectedGroupe] = useState(null);
  const [evaluations, setEvaluations] = useState([]);
  const [selectedEval, setSelectedEval]     = useState(null);
  const [eleves, setEleves]           = useState([]);
  const [notes, setNotes]             = useState({});
  const [loading, setLoading]         = useState(false);
  const [saving, setSaving]           = useState(false);
  const [error, setError]             = useState('');
  const [mode, setMode]               = useState('liste');

  const loadGroupes = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const body = await enseignantApi.groupes();
      setGroupes(body?.data?.data ?? body?.data ?? []);
    } catch (e) {
      setError(e?.error?.message || e?.message || 'Impossible de charger les groupes');
      setGroupes([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadGroupes(); }, [loadGroupes]);

  const loadEvaluations = async (groupeId) => {
    setError('');
    try {
      const body = await enseignantApi.evaluations.list({ groupe_id: groupeId, per_page: 50 });
      setEvaluations(body?.data?.data ?? body?.data ?? []);
    } catch (e) {
      setError(e?.error?.message || e?.message || 'Impossible de charger les évaluations');
      setEvaluations([]);
    }
  };

  const loadElevesPourSaisie = async (evalId) => {
    setLoading(true);
    setError('');
    try {
      const body = await enseignantApi.evaluations.notes(evalId);
      const data = body?.data ?? [];
      setEleves(data);
      const notesInit = {};
      data.forEach(e => {
        notesInit[e.eleve_id] = {
          note:        e.note?.toString() ?? '',
          absent:      e.absent ?? false,
          commentaire: e.commentaire ?? '',
        };
      });
      setNotes(notesInit);
      setMode('saisie');
    } catch (e) {
      setError(e?.error?.message || e?.message || 'Impossible de charger les élèves');
    } finally {
      setLoading(false);
    }
  };

  const sauvegarderNotes = async () => {
    if (!selectedEval) return;
    setSaving(true);

    const payload = eleves.map(e => ({
      eleve_id:    e.eleve_id,
      note:        notes[e.eleve_id]?.note === '' ? null : (parseFloat(notes[e.eleve_id]?.note) || null),
      absent:      notes[e.eleve_id]?.absent ?? false,
      commentaire: notes[e.eleve_id]?.commentaire ?? '',
    }));

    try {
      const body = await enseignantApi.evaluations.saisirNotes(selectedEval.id, { notes: payload });
      if (body?.success) {
        Alert.alert('Enregistré', `${body?.stats?.nb_notes ?? payload.length} note(s) sauvegardée(s).`);
        setMode('liste');
      } else {
        Alert.alert('Erreur', body?.message ?? 'Échec de la sauvegarde');
      }
    } catch (e) {
      Alert.alert('Erreur', e?.error?.message || e?.message || 'Échec de la sauvegarde');
    } finally {
      setSaving(false);
    }
  };

  const updateNote = (eleveId, field, value) => {
    setNotes(n => ({ ...n, [eleveId]: { ...n[eleveId], [field]: value } }));
  };

  const erreur = error ? <Text style={s.error}>{error}</Text> : null;

  if (mode === 'liste') {
    if (!selectedGroupe) {
      return (
        <View style={s.container}>
          <Text style={s.title}>Saisie de notes</Text>
          <Text style={s.sub}>Sélectionnez un groupe :</Text>
          {erreur}
          {loading ? <ActivityIndicator color="#3b82f6" style={{ marginTop: 30 }} /> : (
            <FlatList
              data={groupes}
              keyExtractor={g => String(g.id)}
              renderItem={({ item }) => (
                <TouchableOpacity style={s.groupeCard} onPress={() => {
                  setSelectedGroupe(item);
                  loadEvaluations(item.id);
                }}>
                  <Text style={s.groupeName}>{item.nom}</Text>
                  <Text style={s.groupeSub}>{item.matiere?.nom_fr} · {item.niveau ?? ''}</Text>
                </TouchableOpacity>
              )}
              ListEmptyComponent={<Text style={s.empty}>Aucun groupe trouvé</Text>}
            />
          )}
        </View>
      );
    }

    return (
      <View style={s.container}>
        <TouchableOpacity onPress={() => setSelectedGroupe(null)}>
          <Text style={s.back}>← Retour aux groupes</Text>
        </TouchableOpacity>
        <Text style={s.title}>{selectedGroupe.nom}</Text>
        <Text style={s.sub}>Sélectionnez une évaluation :</Text>
        {erreur}
        <FlatList
          data={evaluations}
          keyExtractor={e => String(e.id)}
          renderItem={({ item }) => (
            <TouchableOpacity style={s.evalCard} onPress={() => {
              setSelectedEval(item);
              loadElevesPourSaisie(item.id);
            }}>
              <Text style={s.evalTitre}>{item.titre}</Text>
              <Text style={s.evalSub}>{item.type_eval} · {item.date_evaluation} · /{item.note_sur}</Text>
              <Text style={s.evalCoeff}>Coeff. {item.coefficient}</Text>
            </TouchableOpacity>
          )}
          ListEmptyComponent={<Text style={s.empty}>Aucune évaluation pour ce groupe</Text>}
        />
        <TouchableOpacity style={s.addBtn} onPress={() => Alert.alert('Info', "Créer une évaluation depuis le web pour l'instant.")}>
          <Text style={s.addBtnText}>+ Créer une évaluation</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <View style={s.container}>
      <View style={s.header}>
        <TouchableOpacity onPress={() => setMode('liste')}>
          <Text style={s.back}>← Retour</Text>
        </TouchableOpacity>
        <Text style={s.evalTitre}>{selectedEval?.titre}</Text>
        <Text style={s.sub}>Note sur {selectedEval?.note_sur} · Coeff. {selectedEval?.coefficient}</Text>
      </View>

      {loading ? <ActivityIndicator color="#3b82f6" style={{ marginTop: 30 }} /> : (
        <>
          <FlatList
            data={eleves}
            keyExtractor={e => String(e.eleve_id)}
            contentContainerStyle={{ paddingBottom: 100 }}
            renderItem={({ item }) => {
              const note = notes[item.eleve_id] ?? {};
              return (
                <View style={s.eleveRow}>
                  <View style={{ flex: 1 }}>
                    <Text style={s.eleveName}>{item.nom_complet}</Text>
                    {note.absent && <Text style={{ fontSize: 10, color: '#f87171' }}>Absent</Text>}
                  </View>

                  <TouchableOpacity
                    style={[s.absentBtn, note.absent && s.absentBtnActive]}
                    onPress={() => updateNote(item.eleve_id, 'absent', !note.absent)}
                  >
                    <Text style={{ fontSize: 10, fontWeight: '700',
                      color: note.absent ? '#fff' : '#64748b' }}>ABS</Text>
                  </TouchableOpacity>

                  {!note.absent && (
                    <TextInput
                      value={note.note?.toString() ?? ''}
                      onChangeText={v => updateNote(item.eleve_id, 'note', v)}
                      placeholder={`0-${selectedEval?.note_sur}`}
                      keyboardType="decimal-pad"
                      style={s.noteInput}
                      placeholderTextColor="#475569"
                    />
                  )}
                </View>
              );
            }}
          />

          <TouchableOpacity style={s.saveBtn} onPress={sauvegarderNotes} disabled={saving} testID="btn-enregistrer">
            {saving
              ? <ActivityIndicator color="#fff" />
              : <Text style={s.saveBtnText}>Enregistrer toutes les notes</Text>
            }
          </TouchableOpacity>
        </>
      )}
    </View>
  );
}

const s = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#08090f', padding: 16 },
  title:     { fontSize: 20, fontWeight: '900', color: '#fff', marginBottom: 4 },
  sub:       { fontSize: 11, color: '#64748b', marginBottom: 16 },
  back:      { fontSize: 12, color: '#60a5fa', marginBottom: 12, fontWeight: '700' },
  header:    { marginBottom: 16 },
  empty:     { color: '#475569', textAlign: 'center', marginTop: 40 },
  error:     { color: '#f87171', fontSize: 12, fontWeight: '700', marginBottom: 12 },
  groupeCard:{ backgroundColor: '#111318', borderRadius: 10,
               padding: 14, marginBottom: 8, borderWidth: 1, borderColor: '#1e293b' },
  groupeName:{ fontSize: 13, fontWeight: '800', color: '#f1f5f9' },
  groupeSub: { fontSize: 10, color: '#64748b', marginTop: 2 },
  evalCard:  { backgroundColor: '#111318', borderRadius: 10, padding: 14,
               marginBottom: 8, borderWidth: 1, borderColor: '#1e293b' },
  evalTitre: { fontSize: 13, fontWeight: '800', color: '#f1f5f9' },
  evalSub:   { fontSize: 10, color: '#64748b', marginTop: 2 },
  evalCoeff: { fontSize: 10, color: '#60a5fa', marginTop: 2, fontWeight: '700' },
  addBtn:    { backgroundColor: '#1e3a5f', borderRadius: 8, padding: 12,
               alignItems: 'center', marginTop: 12 },
  addBtnText:{ color: '#60a5fa', fontWeight: '700', fontSize: 13 },
  eleveRow:  { flexDirection: 'row', alignItems: 'center', gap: 10,
               backgroundColor: '#111318', borderRadius: 8, padding: 12,
               marginBottom: 6, borderWidth: 1, borderColor: '#1e293b' },
  eleveName: { fontSize: 12, fontWeight: '700', color: '#f1f5f9' },
  absentBtn: { backgroundColor: '#1e293b', borderRadius: 6, padding: 6,
               minWidth: 36, alignItems: 'center' },
  absentBtnActive: { backgroundColor: '#b91c1c' },
  noteInput: { backgroundColor: '#1e293b', borderRadius: 6, color: '#e2e8f0',
               padding: 8, width: 70, textAlign: 'center', fontSize: 14, fontWeight: '800' },
  saveBtn:   { position: 'absolute', bottom: 16, left: 16, right: 16,
               backgroundColor: '#1d4ed8', borderRadius: 10, padding: 16,
               alignItems: 'center' },
  saveBtnText: { color: '#fff', fontWeight: '800', fontSize: 14 },
});
