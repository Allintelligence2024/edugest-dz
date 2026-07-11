import React, { useState, useEffect, useCallback } from 'react';
import {
  View, Text, ScrollView, TouchableOpacity,
  StyleSheet, ActivityIndicator, RefreshControl, Alert, TextInput,
} from 'react-native';
import { useAuth } from '../../context/AuthContext';
import { adminApi } from '../../api/endpoints';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

const GRAVITE = {
  tres_grave: { color: '#ef4444', label: '🔴 Très grave' },
  grave:      { color: '#f97316', label: '🟠 Grave' },
  important:  { color: '#eab308', label: '🟡 Important' },
};

const STATUT = {
  soumis:           { color: '#ef4444', label: '⏳ À traiter' },
  en_investigation: { color: '#f97316', label: '🔍 En investigation' },
  resolu:           { color: '#22c55e', label: '✅ Résolu' },
  non_fonde:        { color: '#64748b', label: '❌ Non fondé' },
  archive:          { color: '#64748b', label: '📁 Archivé' },
};

export default function AdminSignalementsScreen() {
  const { token } = useAuth();
  const [signalements, setSignalements] = useState([]);
  const [alerte, setAlerte] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [selected, setSelected] = useState(null);
  const [reponse, setReponse] = useState('');
  const [nouveauStatut, setNouveauStatut] = useState('en_investigation');
  const [traitement, setTraitement] = useState(false);

  const charger = useCallback(async () => {
    try {
      const res = await adminApi.signalements.list(token);
      setSignalements(res?.data ?? []);
      setAlerte(res?.alerte ?? null);
    } catch (e) {
      Alert.alert('Erreur', 'Impossible de charger les signalements');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [token]);

  useEffect(() => { charger(); }, [charger]);

  const traiter = async () => {
    if (!reponse.trim() || reponse.length < 10) {
      Alert.alert('Réponse trop courte', 'Veuillez saisir au moins 10 caractères.');
      return;
    }
    setTraitement(true);
    try {
      await adminApi.signalements.traiter(token, selected.id, {
        statut: nouveauStatut,
        commentaire_admin: reponse,
      });
      Alert.alert('✅ Traité', 'Le signalement a été traité. L\'élève a été notifié.');
      setSelected(null);
      setReponse('');
      charger();
    } catch (e) {
      Alert.alert('Erreur', e.message ?? 'Impossible de traiter le signalement');
    } finally {
      setTraitement(false);
    }
  };

  if (loading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#ef4444" />
        <Text style={styles.loadingText}>Chargement des signalements...</Text>
      </View>
    );
  }

  if (selected) {
    return (
      <ScrollView style={styles.container}>
        <View style={styles.modalHeader}>
          <TouchableOpacity onPress={() => { setSelected(null); setReponse(''); }} style={styles.backBtn}>
            <Text style={styles.backBtnText}>← Retour</Text>
          </TouchableOpacity>
          <Text style={styles.modalTitle}>Traiter le signalement</Text>
        </View>

        <View style={styles.signalCard}>
          <Text style={styles.signalType}>{selected.type_incident?.replace(/_/g, ' ').toUpperCase()}</Text>
          <Text style={styles.signalEleve}>👤 {selected.eleve_nom}</Text>
          <Text style={styles.signalDesc}>{selected.description}</Text>
          <Text style={styles.signalDate}>📅 Incident le {selected.date_incident}</Text>
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionLabel}>Nouveau statut</Text>
          {['en_investigation', 'resolu', 'non_fonde', 'archive'].map(s => (
            <TouchableOpacity
              key={s}
              onPress={() => setNouveauStatut(s)}
              style={[styles.statutBtn, nouveauStatut === s && styles.statutBtnActive]}
            >
              <Text style={[styles.statutTxt, nouveauStatut === s && styles.statutTxtActive]}>
                {STATUT[s]?.label ?? s}
              </Text>
            </TouchableOpacity>
          ))}
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionLabel}>Votre réponse à l'élève *</Text>
          <TextInput
            style={styles.textArea}
            multiline
            numberOfLines={5}
            value={reponse}
            onChangeText={setReponse}
            placeholder="Expliquez les suites données à ce signalement..."
            placeholderTextColor="#64748b"
            maxLength={1000}
          />
          <Text style={styles.charCount}>{reponse.length}/1000</Text>
        </View>

        <TouchableOpacity
          style={[styles.traiterBtn, traitement && styles.traiterBtnDisabled]}
          onPress={traiter}
          disabled={traitement}
        >
          <Text style={styles.traiterBtnText}>
            {traitement ? '⏳ Traitement...' : '✅ Valider et notifier l\'élève'}
          </Text>
        </TouchableOpacity>

        <View style={styles.bottomSpacer} />
      </ScrollView>
    );
  }

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); charger(); }} tintColor="#ef4444" />
      }
    >
      <View style={styles.header}>
        <Text style={styles.headerTitle}>🚨 Signalements graves</Text>
        <Text style={styles.headerSub}>Confidentiels · Directeur uniquement</Text>
      </View>

      {alerte && (
        <View style={styles.alerteBanner}>
          <Text style={styles.alerteText}>⚠️ {alerte}</Text>
        </View>
      )}

      {signalements.length === 0 ? (
        <View style={styles.emptyState}>
          <Text style={styles.emptyIcon}>🛡️</Text>
          <Text style={styles.emptyText}>Aucun signalement en cours</Text>
          <Text style={styles.emptySub}>Les signalements soumis par les élèves apparaîtront ici</Text>
        </View>
      ) : (
        <View style={styles.liste}>
          {signalements.map((sig, idx) => {
            const gc = GRAVITE[sig.gravite] ?? { color: '#64748b', label: sig.gravite };
            const sc = STATUT[sig.statut]   ?? { color: '#64748b', label: sig.statut };
            const urgent = sig.statut === 'soumis';

            return (
              <TouchableOpacity
                key={sig.id ?? idx}
                style={[styles.sigCard, { borderLeftColor: gc.color }, urgent && styles.sigCardUrgent]}
                onPress={() => setSelected(sig)}
              >
                <View style={styles.sigTop}>
                  <Text style={styles.sigType}>
                    {sig.type_incident?.replace(/_/g, ' ') ?? 'Incident'}
                  </Text>
                  <View style={[styles.gravBadge, { backgroundColor: `${gc.color}20`, borderColor: gc.color }]}>
                    <Text style={[styles.gravText, { color: gc.color }]}>{gc.label}</Text>
                  </View>
                </View>
                <Text style={styles.sigEleve}>👤 {sig.eleve_nom}</Text>
                <Text style={styles.sigDesc} numberOfLines={2}>{sig.description}</Text>
                <View style={styles.sigBottom}>
                  <Text style={[styles.sigStatut, { color: sc.color }]}>{sc.label}</Text>
                  <Text style={styles.sigDate}>{sig.date_incident}</Text>
                </View>
                {urgent && (
                  <View style={styles.traiterTag}>
                    <Text style={styles.traiterTagText}>Appuyer pour traiter →</Text>
                  </View>
                )}
              </TouchableOpacity>
            );
          })}
        </View>
      )}

      <View style={styles.confidentialNote}>
        <Text style={styles.confText}>
          🔒 Ces signalements sont strictement confidentiels.
          L'enseignant concerné n'est jamais notifié directement.
        </Text>
      </View>
      <View style={styles.bottomSpacer} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container:       { flex: 1, backgroundColor: colors.background },
  centered:        { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.background },
  loadingText:     { color: colors.textSecondary, marginTop: 12, fontSize: fontSizes.sm },
  header:          { padding: spacing.lg, paddingTop: spacing.sm },
  headerTitle:     { fontSize: fontSizes.xl, fontWeight: '900', color: colors.text },
  headerSub:       { fontSize: fontSizes.xs, color: '#ef4444', marginTop: 4 },
  alerteBanner:    { marginHorizontal: 16, backgroundColor: 'rgba(239,68,68,0.1)', borderRadius: 12, padding: 12, borderWidth: 1, borderColor: 'rgba(239,68,68,0.3)', marginBottom: 12 },
  alerteText:      { fontSize: 13, fontWeight: '700', color: '#f87171' },
  liste:           { paddingHorizontal: 16, gap: 12, marginBottom: 16 },
  emptyState:      { alignItems: 'center', paddingVertical: 60, paddingHorizontal: 32 },
  emptyIcon:       { fontSize: 48, marginBottom: 12 },
  emptyText:       { fontSize: fontSizes.md, fontWeight: '700', color: colors.textSecondary },
  emptySub:        { fontSize: 13, color: colors.textSecondary, marginTop: 6, textAlign: 'center' },
  sigCard:         { backgroundColor: colors.card, borderRadius: 16, padding: 16, borderWidth: 1, borderColor: colors.border, borderLeftWidth: 4 },
  sigCardUrgent:   { borderColor: 'rgba(239,68,68,0.4)' },
  sigTop:          { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 },
  sigType:         { fontSize: 12, fontWeight: '700', color: colors.textSecondary, textTransform: 'capitalize', flex: 1, marginRight: 8 },
  gravBadge:       { borderRadius: 10, paddingHorizontal: 8, paddingVertical: 3, borderWidth: 1 },
  gravText:        { fontSize: 10, fontWeight: '700' },
  sigEleve:        { fontSize: 14, fontWeight: '800', color: colors.text, marginBottom: 6 },
  sigDesc:         { fontSize: 12, color: colors.textSecondary, lineHeight: 18, marginBottom: 10 },
  sigBottom:       { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  sigStatut:       { fontSize: 11, fontWeight: '700' },
  sigDate:         { fontSize: 11, color: colors.textSecondary },
  traiterTag:      { marginTop: 10, backgroundColor: 'rgba(239,68,68,0.1)', borderRadius: 8, padding: 8, alignItems: 'center' },
  traiterTagText:  { fontSize: 12, fontWeight: '700', color: '#ef4444' },
  modalHeader:     { padding: 16, flexDirection: 'row', alignItems: 'center', gap: 12 },
  backBtn:         { padding: 8 },
  backBtnText:     { color: colors.primary, fontSize: 14, fontWeight: '600' },
  modalTitle:      { fontSize: 16, fontWeight: '800', color: colors.text },
  signalCard:      { marginHorizontal: 16, backgroundColor: colors.card, borderRadius: 14, padding: 16, borderWidth: 1, borderColor: 'rgba(239,68,68,0.3)', marginBottom: 16 },
  signalType:      { fontSize: 11, fontWeight: '700', color: '#ef4444', marginBottom: 4 },
  signalEleve:     { fontSize: 14, fontWeight: '700', color: colors.text, marginBottom: 8 },
  signalDesc:      { fontSize: 13, color: colors.textSecondary, lineHeight: 20, marginBottom: 8 },
  signalDate:      { fontSize: 11, color: colors.textSecondary },
  section:         { marginHorizontal: 16, marginBottom: 16 },
  sectionLabel:    { fontSize: 12, fontWeight: '700', color: colors.textSecondary, marginBottom: 8, textTransform: 'uppercase' },
  statutBtn:       { backgroundColor: colors.card, borderRadius: 10, padding: 12, marginBottom: 6, borderWidth: 1, borderColor: colors.border },
  statutBtnActive: { backgroundColor: 'rgba(37,99,235,0.15)', borderColor: colors.primary },
  statutTxt:       { fontSize: 13, color: colors.textSecondary, fontWeight: '600' },
  statutTxtActive: { color: '#60a5fa', fontWeight: '800' },
  textArea:        { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.border, borderRadius: 12, padding: 14, color: colors.text, fontSize: 14, minHeight: 120, textAlignVertical: 'top' },
  charCount:       { fontSize: 10, color: colors.textSecondary, textAlign: 'right', marginTop: 4 },
  traiterBtn:      { marginHorizontal: 16, backgroundColor: '#22c55e', borderRadius: 14, padding: 16, alignItems: 'center', marginBottom: 8 },
  traiterBtnDisabled: { opacity: 0.5 },
  traiterBtnText:  { fontSize: 15, fontWeight: '800', color: '#fff' },
  confidentialNote:{ marginHorizontal: 16, padding: 12, backgroundColor: 'rgba(100,116,139,0.06)', borderRadius: 10, borderWidth: 1, borderColor: 'rgba(100,116,139,0.15)', marginBottom: 16 },
  confText:        { fontSize: 11, color: colors.textSecondary, textAlign: 'center', lineHeight: 16 },
  bottomSpacer:    { height: 40 },
});
