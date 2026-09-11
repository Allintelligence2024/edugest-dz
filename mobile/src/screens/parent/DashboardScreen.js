import React, { useState, useEffect, useCallback } from 'react';
import { View, Text, ScrollView, TouchableOpacity, ActivityIndicator, StyleSheet } from 'react-native';
import { notesApi, presencesApi, paiementsApi, planningApi } from '../../api/endpoints';
import { useAuth } from '../../context/AuthContext';
import { useEnfants } from '../../context/EnfantContext';
import EnfantSelector from '../../components/EnfantSelector';
import { colors, spacing, fontSizes } from '../../theme';

/**
 * PILOTE P1-C3 — Dashboard parent branché sur le réel : moyenne, taux de présence,
 * impayés, prochain cours. Source enfant : EnfantContext (GET /parents/mes-enfants).
 */
export default function DashboardScreen({ navigation }) {
  const { user } = useAuth();
  const { enfantActif, loading: loadingEnfant } = useEnfants();
  const [stats, setStats] = useState({ moyenne: null, taux: null, dette: null, prochain: null });
  const [loading, setLoading] = useState(true);

  const charger = useCallback(async () => {
    if (!enfantActif) return;
    setLoading(true);
    try {
      const [notes, pres, paie, plan] = await Promise.allSettled([
        notesApi.byEleve(enfantActif.id),
        presencesApi.byEleve(enfantActif.id, { per_page: 1 }),
        paiementsApi.byEleve(enfantActif.id),
        planningApi.list({ eleve_id: enfantActif.id }),
      ]);
      const jours = plan.status === 'fulfilled' ? plan.value?.data ?? [] : [];
      const aujourdhui = new Date().toISOString().split('T')[0];
      const prochainJour = jours.find((j) => j.date >= aujourdhui && (j.seances ?? []).length > 0);
      const premiere = prochainJour?.seances?.[0];
      setStats({
        moyenne: notes.status === 'fulfilled' ? notes.value?.data?.moyenne_generale ?? null : null,
        taux: pres.status === 'fulfilled' ? pres.value?.meta?.stats?.taux ?? null : null,
        dette: paie.status === 'fulfilled' ? paie.value?.data?.financier?.total_dette ?? null : null,
        prochain: premiere
          ? `${(premiere.heure_debut ?? '').slice(0, 5)} ${premiere.groupe?.matiere?.nom_fr ?? ''}`
          : null,
      });
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  }, [enfantActif?.id]);

  useEffect(() => {
    if (!loadingEnfant) {
      if (enfantActif) charger();
      else setLoading(false);
    }
  }, [loadingEnfant, enfantActif?.id, charger]);

  if (loadingEnfant || loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  const cartes = [
    {
      testID: 'dash-moyenne', emoji: '📊', titre: 'Moyenne générale',
      valeur: stats.moyenne === null || stats.moyenne === undefined ? '—' : Number(stats.moyenne).toFixed(2),
      cible: 'Notes',
    },
    {
      testID: 'dash-presence', emoji: '✅', titre: 'Taux de présence',
      valeur: stats.taux === null || stats.taux === undefined ? '—' : `${stats.taux}%`,
      cible: 'Presences',
    },
    {
      testID: 'dash-dette', emoji: '💰', titre: 'Reste dû',
      valeur: stats.dette === null || stats.dette === undefined ? '—' : `${Number(stats.dette).toLocaleString('fr-FR')} DA`,
      cible: 'Paiements',
    },
    {
      testID: 'dash-prochain', emoji: '📅', titre: 'Prochain cours',
      valeur: stats.prochain ?? 'Aucun',
      cible: 'Planning',
    },
  ];

  return (
    <ScrollView style={styles.container}>
      <Text style={styles.bonjour}>👋 Bonjour {user?.prenom ?? user?.nom ?? ''}</Text>
      <EnfantSelector />
      {!enfantActif ? (
        <View style={styles.empty}>
          <Text style={styles.emptyText}>Aucun enfant rattaché à ce compte.</Text>
        </View>
      ) : (
        cartes.map((c) => (
          <TouchableOpacity
            key={c.testID}
            testID={c.testID}
            style={styles.carte}
            onPress={() => navigation.navigate(c.cible)}
          >
            <Text style={styles.emoji}>{c.emoji}</Text>
            <View style={styles.carteTexte}>
              <Text style={styles.carteTitre}>{c.titre}</Text>
              <Text style={styles.carteValeur}>{c.valeur}</Text>
            </View>
            <Text style={styles.chevron}>›</Text>
          </TouchableOpacity>
        ))
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container:    { flex: 1, backgroundColor: colors.background, padding: spacing.md },
  center:       { flex: 1, justifyContent: 'center', alignItems: 'center' },
  bonjour:      { fontSize: fontSizes.lg, fontWeight: '700', color: colors.text, marginBottom: spacing.sm },
  empty:        { alignItems: 'center', paddingTop: 60 },
  emptyText:    { fontSize: fontSizes.md, color: colors.textSecondary },
  carte:        { flexDirection: 'row', alignItems: 'center', backgroundColor: colors.card, borderRadius: 14, padding: spacing.md, marginBottom: spacing.sm },
  emoji:        { fontSize: 28, marginRight: spacing.sm },
  carteTexte:   { flex: 1 },
  carteTitre:   { fontSize: fontSizes.sm, color: colors.textSecondary },
  carteValeur:  { fontSize: fontSizes.lg, fontWeight: '800', color: colors.text },
  chevron:      { fontSize: 24, color: colors.textSecondary },
});
