import React, { useState, useEffect } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, TextInput,
  StyleSheet, Alert, Modal, ScrollView,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';

const BASE = 'https://app.edugest.dz/api/v1';

const TYPES = [
  { id: 'perturbation',  label: '😤 Perturbation',    positif: false },
  { id: 'retard_répété', label: '⏰ Retards répétés',  positif: false },
  { id: 'violence',      label: '⚠️ Violence',         positif: false },
  { id: 'tricherie',     label: '📋 Tricherie',        positif: false },
  { id: 'insolence',     label: '🗣️ Insolence',        positif: false },
  { id: 'félicitation',  label: '⭐ Félicitation',     positif: true  },
  { id: 'encouragement', label: '📈 Encouragement',    positif: true  },
  { id: 'autre',         label: '📝 Autre',            positif: false },
];

const GRAVITES = [
  { id: 'info',       label: 'Information',  color: '#60a5fa' },
  { id: 'normale',    label: 'Normale',      color: '#fb923c' },
  { id: 'grave',      label: 'Grave',        color: '#f87171' },
  { id: 'très_grave', label: 'Très grave',   color: '#ef4444' },
];

export default function SignalementsScreen() {
  const [signalements, setSignalements] = useState([]);
  const [showForm, setShowForm]         = useState(false);
  const [eleves, setEleves]             = useState([]);
  const [form, setForm] = useState({
    eleve_id: '', type: 'perturbation', gravite: 'normale',
    description: '', lieu: '', date_incident: new Date().toISOString().split('T')[0],
  });
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    loadMesSignalements();
    loadEleves();
  }, []);

  const headers = async () => {
    const token    = await AsyncStorage.getItem('token');
    const tenantId = await AsyncStorage.getItem('tenantId');
    return { 'Content-Type': 'application/json',
             'Authorization': `Bearer ${token}`, 'X-Tenant-ID': tenantId ?? '' };
  };

  const loadMesSignalements = async () => {
    const h = await headers();
    const r = await fetch(`${BASE}/signalements/mes-signalements`, { headers: h }).then(r => r.json());
    setSignalements(r?.data?.data ?? []);
  };

  const loadEleves = async () => {
    const h = await headers();
    const r = await fetch(`${BASE}/eleves?per_page=200&statut=actif`, { headers: h }).then(r => r.json());
    setEleves(r?.data?.data ?? []);
  };

  const soumettre = async () => {
    if (!form.eleve_id || !form.description) {
      Alert.alert('Manquant', 'Sélectionnez un élève et décrivez le signalement.');
      return;
    }
    setSaving(true);
    const h = await headers();
    const r = await fetch(`${BASE}/signalements`, {
      method: 'POST', headers: h, body: JSON.stringify(form),
    }).then(r => r.json());

    setSaving(false);
    if (r?.success) {
      Alert.alert('✅ Signalement envoyé', 'Le parent a été notifié automatiquement.');
      setShowForm(false);
      setForm({ eleve_id: '', type: 'perturbation', gravite: 'normale',
                description: '', lieu: '', date_incident: new Date().toISOString().split('T')[0] });
      loadMesSignalements();
    } else {
      Alert.alert('Erreur', r?.message ?? 'Échec');
    }
  };

  return (
    <View style={s.container}>
      <View style={s.header}>
        <Text style={s.title}>📋 Signalements</Text>
        <TouchableOpacity style={s.addBtn} onPress={() => setShowForm(true)}>
          <Text style={s.addBtnText}>+ Signaler</Text>
        </TouchableOpacity>
      </View>

      <FlatList
        data={signalements}
        keyExtractor={i => i.id}
        renderItem={({ item }) => (
          <View style={[s.card, { borderColor: GRAVITES.find(g => g.id === item.gravite)?.color ?? '#1e293b' }]}>
            <Text style={s.cardType}>{TYPES.find(t => t.id === item.type)?.label ?? item.type}</Text>
            <Text style={s.cardEleve}>{item.eleve?.prenom} {item.eleve?.nom}</Text>
            <Text style={s.cardDesc} numberOfLines={2}>{item.description}</Text>
            <Text style={s.cardDate}>{item.date_incident} {item.notifie_parent ? '· 📱 Parent notifié' : ''}</Text>
          </View>
        )}
        ListEmptyComponent={
          <Text style={s.empty}>Aucun signalement. Appuyez sur "+ Signaler" pour en créer un.</Text>
        }
      />

      <Modal visible={showForm} animationType="slide" presentationStyle="pageSheet">
        <ScrollView style={s.modal}>
          <Text style={s.modalTitle}>📋 Nouveau signalement</Text>
          <Text style={s.label}>Élève *</Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginBottom: 12 }}>
            {eleves.slice(0, 20).map(e => (
              <TouchableOpacity key={e.id} onPress={() => setForm(f => ({ ...f, eleve_id: e.id }))}
                style={[s.chip, form.eleve_id === e.id && s.chipActive]}>
                <Text style={[s.chipText, form.eleve_id === e.id && s.chipTextActive]}>
                  {e.prenom} {e.nom}
                </Text>
              </TouchableOpacity>
            ))}
          </ScrollView>

          <Text style={s.label}>Type de signalement *</Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginBottom: 12 }}>
            {TYPES.map(t => (
              <TouchableOpacity key={t.id} onPress={() => setForm(f => ({ ...f, type: t.id }))}
                style={[s.chip, form.type === t.id && s.chipActive]}>
                <Text style={[s.chipText, form.type === t.id && s.chipTextActive]}>{t.label}</Text>
              </TouchableOpacity>
            ))}
          </ScrollView>

          <Text style={s.label}>Gravité *</Text>
          <View style={{ flexDirection: 'row', gap: 8, marginBottom: 12 }}>
            {GRAVITES.map(g => (
              <TouchableOpacity key={g.id} onPress={() => setForm(f => ({ ...f, gravite: g.id }))}
                style={[s.graviteBtn, form.gravite === g.id && { backgroundColor: g.color + '33', borderColor: g.color }]}>
                <Text style={[s.graviteText, form.gravite === g.id && { color: g.color }]}>{g.label}</Text>
              </TouchableOpacity>
            ))}
          </View>

          <Text style={s.label}>Description *</Text>
          <TextInput value={form.description}
            onChangeText={v => setForm(f => ({ ...f, description: v }))}
            multiline numberOfLines={4}
            placeholder="Décrivez l'incident ou la félicitation en détail..."
            placeholderTextColor="#475569"
            style={[s.input, { height: 80, textAlignVertical: 'top' }]}
          />

          <Text style={s.label}>Lieu (optionnel)</Text>
          <TextInput value={form.lieu}
            onChangeText={v => setForm(f => ({ ...f, lieu: v }))}
            placeholder="Ex: Salle 12, Couloir bâtiment A..."
            placeholderTextColor="#475569" style={s.input}
          />

          <View style={s.modalActions}>
            <TouchableOpacity style={s.cancelBtn} onPress={() => setShowForm(false)}>
              <Text style={s.cancelBtnText}>Annuler</Text>
            </TouchableOpacity>
            <TouchableOpacity style={s.submitBtn} onPress={soumettre} disabled={saving}>
              <Text style={s.submitBtnText}>
                {saving ? 'Envoi...' : '✅ Envoyer + Notifier parent'}
              </Text>
            </TouchableOpacity>
          </View>
        </ScrollView>
      </Modal>
    </View>
  );
}

const s = StyleSheet.create({
  container:    { flex: 1, backgroundColor: '#08090f', padding: 16 },
  header:       { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 },
  title:        { fontSize: 20, fontWeight: '900', color: '#fff' },
  addBtn:       { backgroundColor: '#1d4ed8', borderRadius: 8, padding: 10 },
  addBtnText:   { color: '#fff', fontWeight: '700', fontSize: 12 },
  card:         { backgroundColor: '#111318', borderRadius: 10, padding: 14,
                  marginBottom: 8, borderWidth: 1 },
  cardType:     { fontSize: 12, fontWeight: '800', color: '#f1f5f9', marginBottom: 2 },
  cardEleve:    { fontSize: 11, color: '#60a5fa', marginBottom: 4 },
  cardDesc:     { fontSize: 11, color: '#94a3b8', marginBottom: 4 },
  cardDate:     { fontSize: 9, color: '#475569' },
  empty:        { color: '#475569', textAlign: 'center', marginTop: 40, fontSize: 12 },
  modal:        { flex: 1, backgroundColor: '#08090f', padding: 20 },
  modalTitle:   { fontSize: 18, fontWeight: '900', color: '#fff', marginBottom: 20 },
  label:        { fontSize: 10, color: '#64748b', marginBottom: 6, fontWeight: '700',
                  textTransform: 'uppercase', letterSpacing: 1 },
  input:        { backgroundColor: '#1e293b', borderRadius: 8, color: '#e2e8f0',
                  padding: 12, fontSize: 12, marginBottom: 12, borderWidth: 1, borderColor: '#334155' },
  chip:         { backgroundColor: '#1e293b', borderRadius: 20, paddingHorizontal: 12,
                  paddingVertical: 6, marginRight: 8, borderWidth: 1, borderColor: '#334155' },
  chipActive:   { backgroundColor: '#1e3a5f', borderColor: '#3b82f6' },
  chipText:     { fontSize: 11, color: '#94a3b8', fontWeight: '600' },
  chipTextActive: { color: '#60a5fa' },
  graviteBtn:   { flex: 1, backgroundColor: '#1e293b', borderRadius: 6, padding: 8,
                  alignItems: 'center', borderWidth: 1, borderColor: '#334155' },
  graviteText:  { fontSize: 9, color: '#64748b', fontWeight: '700' },
  modalActions: { flexDirection: 'row', gap: 10, marginTop: 20, marginBottom: 40 },
  cancelBtn:    { flex: 1, backgroundColor: '#1e293b', borderRadius: 8, padding: 12, alignItems: 'center' },
  cancelBtnText:{ color: '#94a3b8', fontWeight: '700' },
  submitBtn:    { flex: 2, backgroundColor: '#1d4ed8', borderRadius: 8, padding: 12, alignItems: 'center' },
  submitBtnText:{ color: '#fff', fontWeight: '800', fontSize: 12 },
});
