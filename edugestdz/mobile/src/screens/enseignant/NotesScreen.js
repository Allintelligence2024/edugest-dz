import React, { useState, useEffect } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, TextInput,
  StyleSheet, ActivityIndicator, Alert, Modal,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';

const BASE = 'https://app.edugest.dz/api/v1';

const apiHeaders = async () => {
  const token    = await AsyncStorage.getItem('access_token');
  const tenantId = await AsyncStorage.getItem('tenantId');
  return {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`,
    'X-Tenant-ID': tenantId ?? '',
  };
};

function getNoteColor(noteVal, max) {
  if (!noteVal || noteVal === '') return '#475569';
  const v = parseFloat(noteVal);
  if (isNaN(v)) return '#475569';
  const pct = (v / max) * 100;
  if (pct >= 75) return '#10B981';
  if (pct >= 50) return '#F59E0B';
  return '#EF4444';
}

export default function NotesScreen() {
  const [groupes, setGroupes]         = useState([]);
  const [selectedGroupe, setSelectedGroupe] = useState(null);
  const [evaluations, setEvaluations] = useState([]);
  const [selectedEval, setSelectedEval]     = useState(null);
  const [eleves, setEleves]           = useState([]);
  const [notes, setNotes]             = useState({});
  const [loading, setLoading]         = useState(false);
  const [saving, setSaving]           = useState(false);
  const [mode, setMode]               = useState('liste');
  const [modalVisible, setModalVisible] = useState(false);
  const [modalEleve, setModalEleve]   = useState(null);

  useEffect(() => { loadGroupes(); }, []);

  const loadGroupes = async () => {
    setLoading(true);
    const h = await apiHeaders();
    const r = await fetch(`${BASE}/groupes?per_page=100`, { headers: h }).then(r => r.json());
    setGroupes(r?.data?.data ?? r?.data ?? []);
    setLoading(false);
  };

  const loadEvaluations = async (groupeId) => {
    const h = await apiHeaders();
    const r = await fetch(`${BASE}/evaluations?groupe_id=${groupeId}&per_page=50`, { headers: h }).then(r => r.json());
    setEvaluations(r?.data ?? []);
  };

  const loadElevesPourSaisie = async (evalId) => {
    setLoading(true);
    const h = await apiHeaders();
    const r = await fetch(`${BASE}/evaluations/${evalId}/notes`, { headers: h }).then(r => r.json());
    const data = r?.data ?? [];
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
    setLoading(false);
    setMode('saisie');
  };

  const sauvegarderNotes = async () => {
    if (!selectedEval) return;
    setSaving(true);

    const payload = eleves.map(e => ({
      eleve_id:    e.eleve_id,
      note:        parseFloat(notes[e.eleve_id]?.note) || null,
      absent:      notes[e.eleve_id]?.absent ?? false,
      commentaire: notes[e.eleve_id]?.commentaire ?? '',
    }));

    const h = await apiHeaders();
    const r = await fetch(`${BASE}/evaluations/${selectedEval.id}/notes`, {
      method:  'POST',
      headers: h,
      body:    JSON.stringify({ notes: payload }),
    }).then(r => r.json());

    setSaving(false);
    if (r?.success) {
      Alert.alert('✅ Enregistré', `${r?.stats?.nb_notes ?? payload.length} note(s) sauvegardée(s).`);
      setMode('liste');
    } else {
      Alert.alert('Erreur', r?.message ?? 'Échec de la sauvegarde');
    }
  };

  const updateNote = (eleveId, field, value) => {
    setNotes(n => ({ ...n, [eleveId]: { ...n[eleveId], [field]: value } }));
  };

  const openModal = (eleve) => {
    setModalEleve(eleve);
    setModalVisible(true);
  };

  const closeModal = () => {
    setModalVisible(false);
    setModalEleve(null);
  };

  if (mode === 'liste') {
    if (!selectedGroupe) {
      return (
        <View style={s.container}>
          <Text style={s.title}>📝 Saisie de notes</Text>
          <Text style={s.sub}>Sélectionnez un groupe :</Text>
          {loading ? <ActivityIndicator color="#3b82f6" style={{ marginTop: 30 }} /> : (
            <FlatList
              data={groupes}
              keyExtractor={g => g.id}
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
        <FlatList
          data={evaluations}
          keyExtractor={e => e.id}
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
            keyExtractor={e => e.eleve_id}
            contentContainerStyle={{ paddingBottom: 100 }}
            renderItem={({ item }) => {
              const note = notes[item.eleve_id] ?? {};
              const noteColor = getNoteColor(note.note, selectedEval?.note_sur || 20);
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
                    <TouchableOpacity onPress={() => openModal(item)}>
                      <TextInput
                        value={note.note?.toString() ?? ''}
                        onChangeText={v => updateNote(item.eleve_id, 'note', v)}
                        placeholder={`0-${selectedEval?.note_sur}`}
                        keyboardType="decimal-pad"
                        style={[s.noteInput, { borderColor: noteColor, color: noteColor }]}
                        placeholderTextColor="#475569"
                      />
                    </TouchableOpacity>
                  )}
                </View>
              );
            }}
          />

          <TouchableOpacity style={s.saveBtn} onPress={sauvegarderNotes} disabled={saving}>
            {saving
              ? <ActivityIndicator color="#fff" />
              : <Text style={s.saveBtnText}>💾 Enregistrer toutes les notes</Text>
            }
          </TouchableOpacity>
        </>
      )}

      <Modal visible={modalVisible} transparent animationType="slide">
        <View style={s.modalOverlay}>
          <View style={s.modalContent}>
            <Text style={s.modalTitle}>{modalEleve?.nom_complet}</Text>
            <Text style={s.sub}>Note / {selectedEval?.note_sur}</Text>

            {modalEleve && (
              <>
                <TextInput
                  value={notes[modalEleve.eleve_id]?.note?.toString() ?? ''}
                  onChangeText={v => updateNote(modalEleve.eleve_id, 'note', v)}
                  keyboardType="decimal-pad"
                  placeholder={`0-${selectedEval?.note_sur}`}
                  style={[s.modalInput, {
                    borderColor: getNoteColor(notes[modalEleve.eleve_id]?.note, selectedEval?.note_sur || 20),
                    color: getNoteColor(notes[modalEleve.eleve_id]?.note, selectedEval?.note_sur || 20),
                  }]}
                  placeholderTextColor="#475569"
                  autoFocus
                />

                <TextInput
                  value={notes[modalEleve.eleve_id]?.commentaire ?? ''}
                  onChangeText={v => updateNote(modalEleve.eleve_id, 'commentaire', v)}
                  placeholder="Commentaire (optionnel)"
                  style={s.modalCommentInput}
                  placeholderTextColor="#475569"
                  multiline
                />

                <TouchableOpacity style={s.modalSaveBtn} onPress={closeModal}>
                  <Text style={s.modalSaveBtnText}>Valider</Text>
                </TouchableOpacity>
              </>
            )}
          </View>
        </View>
      </Modal>
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
  noteInput: { backgroundColor: '#1e293b', borderRadius: 6,
               padding: 8, width: 70, textAlign: 'center', fontSize: 14, fontWeight: '800',
               borderWidth: 1.5 },
  saveBtn:   { position: 'absolute', bottom: 16, left: 16, right: 16,
               backgroundColor: '#1d4ed8', borderRadius: 10, padding: 16,
               alignItems: 'center' },
  saveBtnText: { color: '#fff', fontWeight: '800', fontSize: 14 },
  modalOverlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.7)', justifyContent: 'center', alignItems: 'center' },
  modalContent: { backgroundColor: '#111318', borderRadius: 16, padding: 24, width: '85%', borderWidth: 1, borderColor: '#1e293b' },
  modalTitle: { fontSize: 16, fontWeight: '900', color: '#fff', marginBottom: 4 },
  modalInput: { backgroundColor: '#1e293b', borderRadius: 10, padding: 16, fontSize: 28,
                fontWeight: '900', textAlign: 'center', marginTop: 12, borderWidth: 2 },
  modalCommentInput: { backgroundColor: '#1e293b', borderRadius: 10, padding: 12, fontSize: 13,
                       color: '#e2e8f0', marginTop: 12, borderWidth: 1, borderColor: '#334155',
                       minHeight: 60, textAlignVertical: 'top' },
  modalSaveBtn: { backgroundColor: '#1d4ed8', borderRadius: 10, padding: 14, alignItems: 'center', marginTop: 16 },
  modalSaveBtnText: { color: '#fff', fontWeight: '800', fontSize: 14 },
});
