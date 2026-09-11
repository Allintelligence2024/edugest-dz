import React, { useState, useEffect, useCallback } from 'react';
import { View, Text, ScrollView, TouchableOpacity, TextInput, ActivityIndicator, Alert, StyleSheet } from 'react-native';
import { enseignantApi } from '../../api/endpoints';
import { colors, spacing, fontSizes } from '../../theme';

const STATUTS = [
  { code: 'présent', emoji: '✅', label: 'P' },
  { code: 'absent',  emoji: '❌', label: 'A' },
  { code: 'retard',  emoji: '⏰', label: 'R' },
  { code: 'excusé',  emoji: '📝', label: 'E' },
];

/**
 * PILOTE P1-C5 — Appel enseignant réécrit sur le contrat réel :
 * GET /presences/seance/{id} → data.[{eleve_id, nom_complet, statut|null, motif}],
 * POST → {presences:[{eleve_id, statut, motif?}]} → {success, message}.
 * Par défaut : présent (sauf statut déjà saisi).
 */
export default function PresencesScreen({ route, navigation }) {
  const { seanceId, titreSeance } = route.params ?? {};
  const [eleves, setEleves] = useState([]);
  const [statuts, setStatuts] = useState({});
  const [motifs, setMotifs] = useState({});
  const [info, setInfo] = useState(null);
  const [loading, setLoading] = useState(true);
  const [envoi, setEnvoi] = useState(false);

  const charger = useCallback(async () => {
    if (!seanceId) return;
    setLoading(true);
    try {
      const res = await enseignantApi.presences.parSeance(seanceId);
      const liste = res?.data ?? [];
      setEleves(liste);
      setInfo(res?.seance ?? null);
      const initS = {};
      const initM = {};
      liste.forEach((e) => {
        initS[e.eleve_id] = e.statut ?? 'présent';
        initM[e.eleve_id] = e.motif ?? '';
      });
      setStatuts(initS);
      setMotifs(initM);
    } catch (e) {
      console.error(e);
      Alert.alert('Erreur', 'Impossible de charger la liste des élèves.');
    } finally {
      setLoading(false);
    }
  }, [seanceId]);

  useEffect(() => {
    charger();
  }, [charger]);

  const envoyer = useCallback(async () => {
    setEnvoi(true);
    try {
      const presences = eleves.map((e) => ({
        eleve_id: e.eleve_id,
        statut: statuts[e.eleve_id] ?? 'présent',
        motif: motifs[e.eleve_id]?.trim() ? motifs[e.eleve_id].trim() : null,
      }));
      const res = await enseignantApi.presences.saisir(seanceId, { presences });
      if (res?.success) {
        Alert.alert('Succès', res.message ?? 'Présences enregistrées.', [
          { text: 'OK', onPress: () => navigation.goBack() },
        ]);
      } else {
        Alert.alert('Erreur', 'La sauvegarde a échoué.');
      }
    } catch (e) {
      console.error(e);
      Alert.alert('Erreur', 'La sauvegarde a échoué. Vérifiez votre connexion.');
    } finally {
      setEnvoi(false);
    }
  }, [eleves, statuts, motifs, seanceId, navigation]);

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  const nbAbsents = Object.values(statuts).filter((s) => s === 'absent').length;

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.titre}>{titreSeance ?? 'Appel'}</Text>
        {info ? (
          <Text style={styles.sousTitre}>
            {(info.date ?? '').slice(0, 10)} • {info.groupe ?? ''} • {eleves.length} élève(s)
            {nbAbsents > 0 ? ` • ${nbAbsents} absent(s)` : ''}
          </Text>
        ) : null}
      </View>
      <ScrollView style={styles.list}>
        {eleves.map((e) => (
          <View key={e.eleve_id} testID={`eleve-${e.eleve_id}`} style={styles.card}>
            <Text style={styles.nom}>{e.nom_complet}</Text>
            <View style={styles.btns}>
              {STATUTS.map((s) => {
                const actif = statuts[e.eleve_id] === s.code;
                return (
                  <TouchableOpacity
                    key={s.code}
                    testID={`statut-${e.eleve_id}-${s.code}`}
                    style={[styles.btn, actif && styles.btnActif]}
                    onPress={() => setStatuts((prev) => ({ ...prev, [e.eleve_id]: s.code }))}
                  >
                    <Text style={[styles.btnText, actif && styles.btnTextActif]}>
                      {s.emoji} {s.label}
                    </Text>
                  </TouchableOpacity>
                );
              })}
            </View>
            {statuts[e.eleve_id] !== 'présent' && (
              <TextInput
                testID={`motif-${e.eleve_id}`}
                style={styles.motif}
                placeholder="Motif (optionnel)"
                value={motifs[e.eleve_id] ?? ''}
                onChangeText={(t) => setMotifs((prev) => ({ ...prev, [e.eleve_id]: t }))}
              />
            )}
          </View>
        ))}
      </ScrollView>
      <TouchableOpacity
        testID="appel-valider"
        style={[styles.valider, envoi && styles.validerDisabled]}
        onPress={envoyer}
        disabled={envoi}
      >
        <Text style={styles.validerText}>{envoi ? 'Envoi…' : `✅ Valider l'appel (${eleves.length})`}</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  center:    { flex: 1, justifyContent: 'center', alignItems: 'center' },
  header:    { padding: spacing.md, paddingBottom: spacing.sm },
  titre:     { fontSize: fontSizes.lg, fontWeight: '700', color: colors.text },
  sousTitre: { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: 2 },
  list:      { flex: 1, paddingHorizontal: spacing.md },
  card:      { backgroundColor: colors.card, borderRadius: 14, padding: spacing.md, marginBottom: spacing.sm },
  nom:       { fontSize: fontSizes.md, fontWeight: '600', color: colors.text, marginBottom: 8 },
  btns:      { flexDirection: 'row', gap: 6 },
  btn:       { flex: 1, borderRadius: 10, paddingVertical: 8, alignItems: 'center', backgroundColor: colors.background, borderWidth: 1, borderColor: colors.border },
  btnActif:  { backgroundColor: colors.primary, borderColor: colors.primary },
  btnText:   { fontSize: fontSizes.xs, fontWeight: '700', color: colors.text },
  btnTextActif: { color: '#fff' },
  motif:     { marginTop: 8, borderWidth: 1, borderColor: colors.border, borderRadius: 10, padding: 8, fontSize: fontSizes.sm, backgroundColor: colors.background },
  valider:   { backgroundColor: colors.success, margin: spacing.md, borderRadius: 12, padding: spacing.md, alignItems: 'center' },
  validerDisabled: { opacity: 0.6 },
  validerText: { color: '#fff', fontWeight: '800', fontSize: fontSizes.md },
});
