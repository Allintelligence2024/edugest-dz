# 🤖 MISSION DEEPSEEK — Priorité 5 : Application Mobile — Screens Enseignant + Admin
## EduGest DZ · Branche : develop · 1er Juillet 2026
## Stack : React Native 0.76 · Expo 52 · React Navigation 7

---

## CONTEXTE EXACT

### Ce qui EXISTE dans le mobile (ne pas recréer)
```
mobile/src/
  api/
    axios.js          → client Axios JWT + refresh ✅
    endpoints.js      → authApi, planningApi, notesApi, presencesApi, paiementsApi, messagesApi, bulletinsApi ✅
  context/
    AuthContext.js    → useAuth() → { user, tenant, isAuthenticated, logout } ✅
    I18nContext.js    → useI18n() → { t, isRTL } ✅
  navigation/
    AppNavigator.js   → Auth + ParentTabs + EnseignantNavigator ✅ (à étendre)
  screens/
    auth/LoginScreen.js          ✅ complet
    parent/ (8 screens)          ✅ tous complets
    enseignant/DashboardScreen.js ⚠️ PLACEHOLDER (1 ligne de texte)
  services/
    cache.js, storage.js, notifications.js ✅
  theme/
    colors.js, spacing.js ✅
```

### Ce qui MANQUE — à créer
```
screens/enseignant/
  PlanningScreen.js        ← planning de la semaine du prof
  PresencesScreen.js       ← saisir les présences d'une séance
  NotesScreen.js           ← saisir les notes d'une évaluation
  MesGroupesScreen.js      ← liste des groupes/cours du prof
  PointageScreen.js        ← pointer son arrivée (QR ou manuel)

screens/admin/
  DashboardScreen.js       ← KPIs du centre : CA, élèves, absences
  ElevesScreen.js          ← liste élèves + recherche
  EleveDetailScreen.js     ← fiche élève complète
  AbsencesScreen.js        ← absences du jour + pointage
  FinanceScreen.js         ← CA, impayés, factures du jour

api/endpoints.js           ← ajouter : enseignantApi + adminApi + absencesApi

navigation/AppNavigator.js ← ajouter : EnseignantTabs + AdminTabs
```

### Style de référence (à respecter)
- Même structure que `screens/parent/DashboardScreen.js`
- Utiliser `useAuth()`, `useI18n()`, `colors`, `spacing`, `fontSizes`
- ScrollView + StyleSheet
- Appels API via `api/endpoints.js` avec `useEffect` + `useState`
- Gestion loading (ActivityIndicator) + erreur

---

## ÉTAPE 1 — Étendre endpoints.js

**Modifier :** `edugestdz/mobile/src/api/endpoints.js`

Ajouter à la fin du fichier :

```javascript
// ── API Enseignant ──
export const enseignantApi = {
  planning: (params)          => api.get('/planning', { params }),
  seances:  (params)          => api.get('/seances', { params }),
  groupes:  ()                => api.get('/groupes'),
  presences: {
    parSeance: (seanceId)     => api.get(`/presences/seance/${seanceId}`),
    saisir:    (seanceId, data)=> api.post(`/presences/seance/${seanceId}`, data),
  },
  evaluations: {
    list:      (params)       => api.get('/evaluations', { params }),
    notes:     (evalId)       => api.get(`/evaluations/${evalId}/notes`),
    saisirNotes: (evalId, data)=> api.post(`/evaluations/${evalId}/notes`, data),
  },
  pointage: {
    arrivee: (id, data)       => api.post(`/pointage/enseignants/${id}/arrivee`, data),
    depart:  (id, data)       => api.post(`/pointage/enseignants/${id}/depart`, data),
    aujourdhui: ()            => api.get('/pointage/enseignants/aujourd-hui'),
  },
  statistiques: (id)          => api.get(`/enseignants/${id}/statistiques`),
};

// ── API Admin / Dashboard ──
export const adminApi = {
  dashboard: {
    finance:     ()           => api.get('/finance/tableau-bord'),
    pedagogique: ()           => api.get('/rapports/pedagogique'),
  },
  eleves: {
    list:    (params)         => api.get('/eleves', { params }),
    show:    (id)             => api.get(`/eleves/${id}`),
    notes:   (id)             => api.get(`/eleves/${id}/notes`),
    paiements:(id)            => api.get(`/eleves/${id}/paiements`),
    bulletins:(id)            => api.get(`/eleves/${id}/bulletins`),
    presences:(id)            => api.get(`/eleves/${id}/presences`),
  },
  absences: {
    jour:        (params)     => api.get('/absences/jour', { params }),
    marquerPresent:(eleveId, data) => api.post(`/absences/${eleveId}/present`, data),
  },
  finance: {
    bilan:   (params)         => api.get('/budget/bilan-mensuel', { params }),
    factures:(params)         => api.get('/factures', { params }),
    impayes: ()               => api.get('/finance/impayes'),
  },
};
```

---

## ÉTAPE 2 — Screens Enseignant

### 2a — DashboardScreen.js (remplacer le placeholder)

**Remplacer :** `edugestdz/mobile/src/screens/enseignant/DashboardScreen.js`

```javascript
import React, { useState, useEffect } from 'react';
import {
  View, Text, StyleSheet, ScrollView,
  TouchableOpacity, ActivityIndicator, RefreshControl,
} from 'react-native';
import { useAuth } from '../../context/AuthContext';
import { useI18n } from '../../context/I18nContext';
import { enseignantApi } from '../../api/endpoints';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

export default function EnseignantDashboardScreen({ navigation }) {
  const { user } = useAuth();
  const { t } = useI18n();
  const [stats, setStats]         = useState(null);
  const [pointage, setPointage]   = useState(null);
  const [loading, setLoading]     = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchData = async () => {
    try {
      const [statsRes, pointageRes] = await Promise.all([
        enseignantApi.statistiques(user?.enseignant_id || user?.id),
        enseignantApi.pointage.aujourdhui(),
      ]);
      setStats(statsRes.data?.data);
      // Trouver mon pointage du jour
      const monPointage = pointageRes.data?.data?.enseignants
        ?.find(e => e.enseignant?.id === (user?.enseignant_id || user?.id));
      setPointage(monPointage);
    } catch (e) {
      console.error('Dashboard enseignant:', e);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => { fetchData(); }, []);

  const handlePointage = async (type) => {
    try {
      const id = user?.enseignant_id || user?.id;
      if (type === 'arrivee') {
        await enseignantApi.pointage.arrivee(id, {});
      } else {
        await enseignantApi.pointage.depart(id, {});
      }
      fetchData();
    } catch (e) {
      console.error('Pointage:', e);
    }
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  const estPointe = !!pointage?.heure_arrivee;
  const aDepart   = !!pointage?.heure_depart;

  return (
    <ScrollView
      style={styles.container}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); fetchData(); }} />}
    >
      <View style={styles.header}>
        <Text style={styles.greeting}>Bonjour, {user?.prenom} 👋</Text>
        <Text style={styles.date}>{new Date().toLocaleDateString('fr-DZ', { weekday: 'long', day: 'numeric', month: 'long' })}</Text>
      </View>

      {/* Pointage du jour */}
      <View style={styles.pointageCard}>
        <Text style={styles.cardTitle}>⏰ Pointage aujourd'hui</Text>
        <Text style={styles.pointageStatut}>
          {estPointe ? `✅ Arrivé à ${pointage?.heure_arrivee?.slice(0, 5)}` : '⏳ Non pointé'}
          {aDepart ? `  ·  Parti à ${pointage?.heure_depart?.slice(0, 5)}` : ''}
        </Text>
        <View style={styles.pointageButtons}>
          {!estPointe && (
            <TouchableOpacity style={[styles.btn, { backgroundColor: colors.success }]} onPress={() => handlePointage('arrivee')}>
              <Text style={styles.btnText}>📍 Pointer arrivée</Text>
            </TouchableOpacity>
          )}
          {estPointe && !aDepart && (
            <TouchableOpacity style={[styles.btn, { backgroundColor: colors.warning }]} onPress={() => handlePointage('depart')}>
              <Text style={styles.btnText}>🚪 Pointer départ</Text>
            </TouchableOpacity>
          )}
        </View>
      </View>

      {/* Stats */}
      <View style={styles.statsRow}>
        {[
          { label: 'Groupes actifs', value: stats?.cours_actifs ?? '—', color: '#6366f1' },
          { label: 'Paie du mois', value: stats?.paie_mois ? `${Number(stats.paie_mois).toLocaleString()} DA` : '—', color: '#10b981' },
        ].map((s, i) => (
          <View key={i} style={[styles.statCard, { backgroundColor: s.color }]}>
            <Text style={styles.statValue}>{s.value}</Text>
            <Text style={styles.statLabel}>{s.label}</Text>
          </View>
        ))}
      </View>

      {/* Navigation rapide */}
      <View style={styles.quickNav}>
        {[
          { label: '📅 Mon planning',  screen: 'Planning' },
          { label: '📚 Mes groupes',   screen: 'MesGroupes' },
          { label: '✅ Présences',     screen: 'Presences' },
          { label: '📝 Notes',         screen: 'Notes' },
        ].map((item, i) => (
          <TouchableOpacity key={i} style={styles.navCard} onPress={() => navigation.navigate(item.screen)}>
            <Text style={styles.navLabel}>{item.label}</Text>
          </TouchableOpacity>
        ))}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container:      { flex: 1, backgroundColor: colors.background },
  center:         { flex: 1, justifyContent: 'center', alignItems: 'center' },
  header:         { padding: spacing.lg, paddingBottom: spacing.sm },
  greeting:       { fontSize: fontSizes.xl, fontWeight: '700', color: colors.text },
  date:           { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: 4 },
  pointageCard:   { margin: spacing.md, backgroundColor: colors.card, borderRadius: 16, padding: spacing.md },
  cardTitle:      { fontSize: fontSizes.md, fontWeight: '700', color: colors.text, marginBottom: 8 },
  pointageStatut: { fontSize: fontSizes.sm, color: colors.textSecondary, marginBottom: 12 },
  pointageButtons:{ flexDirection: 'row', gap: 8 },
  btn:            { flex: 1, borderRadius: 10, paddingVertical: 10, alignItems: 'center' },
  btnText:        { color: '#fff', fontWeight: '600', fontSize: fontSizes.sm },
  statsRow:       { flexDirection: 'row', paddingHorizontal: spacing.md, gap: spacing.sm, marginBottom: spacing.md },
  statCard:       { flex: 1, borderRadius: 14, padding: spacing.md },
  statValue:      { fontSize: fontSizes.xl, fontWeight: '800', color: '#fff' },
  statLabel:      { fontSize: fontSizes.xs, color: '#fff', opacity: 0.85, marginTop: 4 },
  quickNav:       { flexDirection: 'row', flexWrap: 'wrap', paddingHorizontal: spacing.md, gap: spacing.sm },
  navCard:        { width: '48%', backgroundColor: colors.card, borderRadius: 14, padding: spacing.md, alignItems: 'center' },
  navLabel:       { fontSize: fontSizes.sm, fontWeight: '600', color: colors.text, textAlign: 'center' },
});
```

### 2b — PlanningScreen.js

**Créer :** `edugestdz/mobile/src/screens/enseignant/PlanningScreen.js`

```javascript
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
      {/* Sélecteur de jour */}
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
```

### 2c — PresencesScreen.js

**Créer :** `edugestdz/mobile/src/screens/enseignant/PresencesScreen.js`

```javascript
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
        // Construire un objet { eleve_id: statut }
        const map = {};
        data.forEach(p => { map[p.eleve_id] = p.statut || 'présent'; });
        // Extraire la liste des élèves
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
```

### 2d — NotesScreen.js

**Créer :** `edugestdz/mobile/src/screens/enseignant/NotesScreen.js`

```javascript
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
```

---

## ÉTAPE 3 — Screens Admin

### 3a — DashboardScreen.js (Admin)

**Créer :** `edugestdz/mobile/src/screens/admin/DashboardScreen.js`

```javascript
import React, { useState, useEffect } from 'react';
import {
  View, Text, StyleSheet, ScrollView,
  TouchableOpacity, ActivityIndicator, RefreshControl,
} from 'react-native';
import { useAuth } from '../../context/AuthContext';
import { adminApi } from '../../api/endpoints';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

export default function AdminDashboardScreen({ navigation }) {
  const { user, tenant } = useAuth();
  const [finance, setFinance]     = useState(null);
  const [loading, setLoading]     = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchData = async () => {
    try {
      const res = await adminApi.dashboard.finance();
      setFinance(res.data?.data);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => { fetchData(); }, []);

  if (loading) return <View style={styles.center}><ActivityIndicator size="large" color={colors.primary} /></View>;

  const kpis = [
    { label: 'CA ce mois',   value: finance?.ca_mois     ? `${Number(finance.ca_mois).toLocaleString()} DA`    : '—', color: '#10b981', icon: '💰' },
    { label: 'Impayés',      value: finance?.impayes     ? `${Number(finance.impayes).toLocaleString()} DA`     : '—', color: '#ef4444', icon: '⚠️' },
    { label: 'CA annuel',    value: finance?.ca_annee    ? `${Number(finance.ca_annee).toLocaleString()} DA`    : '—', color: '#6366f1', icon: '📊' },
    { label: 'Fact. impayées', value: finance?.nb_impayes ?? '—',                                                      color: '#f59e0b', icon: '📄' },
  ];

  return (
    <ScrollView
      style={styles.container}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); fetchData(); }} />}
    >
      <View style={styles.header}>
        <Text style={styles.greeting}>Bonjour, {user?.prenom} 👋</Text>
        <Text style={styles.tenant}>{tenant?.nom_etablissement}</Text>
      </View>

      <View style={styles.kpiGrid}>
        {kpis.map((k, i) => (
          <View key={i} style={[styles.kpiCard, { backgroundColor: k.color }]}>
            <Text style={styles.kpiIcon}>{k.icon}</Text>
            <Text style={styles.kpiValue}>{k.value}</Text>
            <Text style={styles.kpiLabel}>{k.label}</Text>
          </View>
        ))}
      </View>

      <View style={styles.menuGrid}>
        {[
          { label: '👦 Élèves',     screen: 'Eleves',   color: '#6366f1' },
          { label: '✅ Absences',   screen: 'Absences', color: '#ef4444' },
          { label: '💰 Finance',    screen: 'Finance',  color: '#10b981' },
          { label: '📊 Rapports',   screen: 'Finance',  color: '#f59e0b' },
        ].map((m, i) => (
          <TouchableOpacity
            key={i}
            style={[styles.menuCard, { borderLeftColor: m.color }]}
            onPress={() => navigation.navigate(m.screen)}
          >
            <Text style={styles.menuLabel}>{m.label}</Text>
          </TouchableOpacity>
        ))}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container:  { flex: 1, backgroundColor: colors.background },
  center:     { flex: 1, justifyContent: 'center', alignItems: 'center' },
  header:     { padding: spacing.lg, paddingBottom: spacing.sm },
  greeting:   { fontSize: fontSizes.xl, fontWeight: '700', color: colors.text },
  tenant:     { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: 4 },
  kpiGrid:    { flexDirection: 'row', flexWrap: 'wrap', padding: spacing.md, gap: spacing.sm },
  kpiCard:    { width: '48%', borderRadius: 14, padding: spacing.md },
  kpiIcon:    { fontSize: 22 },
  kpiValue:   { fontSize: fontSizes.xl, fontWeight: '800', color: '#fff', marginTop: 4 },
  kpiLabel:   { fontSize: fontSizes.xs, color: '#fff', opacity: 0.85, marginTop: 4 },
  menuGrid:   { padding: spacing.md, gap: spacing.sm },
  menuCard:   { backgroundColor: colors.card, borderRadius: 14, padding: spacing.md, borderLeftWidth: 4 },
  menuLabel:  { fontSize: fontSizes.md, fontWeight: '600', color: colors.text },
});
```

### 3b — ElevesScreen.js (Admin)

**Créer :** `edugestdz/mobile/src/screens/admin/ElevesScreen.js`

```javascript
import React, { useState, useEffect, useCallback } from 'react';
import {
  View, Text, StyleSheet, FlatList, TextInput,
  TouchableOpacity, ActivityIndicator,
} from 'react-native';
import { adminApi } from '../../api/endpoints';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

export default function AdminElevesScreen({ navigation }) {
  const [eleves, setEleves]   = useState([]);
  const [search, setSearch]   = useState('');
  const [loading, setLoading] = useState(true);
  const [page, setPage]       = useState(1);
  const [total, setTotal]     = useState(0);

  const fetchEleves = useCallback(async (s = search, p = 1) => {
    setLoading(true);
    try {
      const res = await adminApi.eleves.list({ search: s, per_page: 20, page: p });
      if (p === 1) setEleves(res.data?.data || []);
      else setEleves(prev => [...prev, ...(res.data?.data || [])]);
      setTotal(res.data?.meta?.total || 0);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchEleves(); }, []);

  const onSearch = (text) => {
    setSearch(text);
    setPage(1);
    if (text.length === 0 || text.length >= 2) fetchEleves(text, 1);
  };

  return (
    <View style={styles.container}>
      <View style={styles.searchBar}>
        <TextInput
          style={styles.searchInput}
          placeholder="🔍 Rechercher un élève..."
          value={search}
          onChangeText={onSearch}
          placeholderTextColor={colors.textLight}
        />
      </View>

      <Text style={styles.count}>{total} élève(s)</Text>

      {loading && page === 1 ? (
        <View style={styles.center}><ActivityIndicator size="large" color={colors.primary} /></View>
      ) : (
        <FlatList
          data={eleves}
          keyExtractor={item => item.id}
          contentContainerStyle={{ padding: spacing.md }}
          renderItem={({ item }) => (
            <TouchableOpacity
              style={styles.eleveCard}
              onPress={() => navigation.navigate('EleveDetail', { eleveId: item.id, nom: `${item.prenom} ${item.nom}` })}
            >
              <View style={styles.avatar}>
                <Text style={styles.avatarText}>{item.prenom?.[0]}{item.nom?.[0]}</Text>
              </View>
              <View style={styles.eleveInfo}>
                <Text style={styles.eleveName}>{item.prenom} {item.nom}</Text>
                <Text style={styles.eleveLevel}>{item.niveau_scolaire} · {item.numero_inscription}</Text>
              </View>
              <View style={[styles.statutDot, { backgroundColor: item.statut === 'actif' ? '#10b981' : '#ef4444' }]} />
            </TouchableOpacity>
          )}
          onEndReached={() => {
            if (eleves.length < total) {
              const nextPage = page + 1;
              setPage(nextPage);
              fetchEleves(search, nextPage);
            }
          }}
          onEndReachedThreshold={0.3}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container:  { flex: 1, backgroundColor: colors.background },
  center:     { flex: 1, justifyContent: 'center', alignItems: 'center' },
  searchBar:  { padding: spacing.md, paddingBottom: 0 },
  searchInput:{ backgroundColor: colors.card, borderRadius: 12, padding: 12, fontSize: fontSizes.sm, color: colors.text },
  count:      { paddingHorizontal: spacing.lg, paddingVertical: 6, fontSize: fontSizes.xs, color: colors.textSecondary },
  eleveCard:  { flexDirection: 'row', alignItems: 'center', backgroundColor: colors.card, borderRadius: 14, padding: spacing.md, marginBottom: spacing.sm },
  avatar:     { width: 44, height: 44, borderRadius: 22, backgroundColor: colors.primary + '20', justifyContent: 'center', alignItems: 'center', marginRight: 12 },
  avatarText: { fontSize: fontSizes.md, fontWeight: '700', color: colors.primary },
  eleveInfo:  { flex: 1 },
  eleveName:  { fontSize: fontSizes.md, fontWeight: '600', color: colors.text },
  eleveLevel: { fontSize: fontSizes.sm, color: colors.textSecondary, marginTop: 2 },
  statutDot:  { width: 10, height: 10, borderRadius: 5 },
});
```

### 3c — AbsencesScreen.js (Admin)

**Créer :** `edugestdz/mobile/src/screens/admin/AbsencesScreen.js`

```javascript
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
```

---

## ÉTAPE 4 — Mettre à jour AppNavigator.js

**Remplacer :** `edugestdz/mobile/src/navigation/AppNavigator.js`

```javascript
import React from 'react';
import { View, ActivityIndicator } from 'react-native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { Text } from 'react-native';
import { useAuth } from '../context/AuthContext';
import { useI18n } from '../context/I18nContext';
import { colors } from '../theme/colors';

// Auth
import LoginScreen from '../screens/auth/LoginScreen';

// Parent (existants)
import ParentDashboard  from '../screens/parent/DashboardScreen';
import ParentPlanning   from '../screens/parent/PlanningScreen';
import ParentNotes      from '../screens/parent/NotesScreen';
import ParentPresences  from '../screens/parent/PresencesScreen';
import ParentPaiements  from '../screens/parent/PaiementsScreen';
import ParentMessages   from '../screens/parent/MessagesScreen';
import ParentBulletins  from '../screens/parent/BulletinsScreen';
import ParentProfile    from '../screens/parent/ProfileScreen';

// Enseignant (nouveaux)
import EnseignantDashboard  from '../screens/enseignant/DashboardScreen';
import EnseignantPlanning   from '../screens/enseignant/PlanningScreen';
import EnseignantPresences  from '../screens/enseignant/PresencesScreen';
import EnseignantNotes      from '../screens/enseignant/NotesScreen';

// Admin (nouveaux)
import AdminDashboard   from '../screens/admin/DashboardScreen';
import AdminEleves      from '../screens/admin/ElevesScreen';
import AdminAbsences    from '../screens/admin/AbsencesScreen';

const AuthStack      = createNativeStackNavigator();
const ParentTab      = createBottomTabNavigator();
const EnseignantTab  = createBottomTabNavigator();
const AdminTab       = createBottomTabNavigator();
const EnseignantStack= createNativeStackNavigator();

function tabIcon(name, focused) {
  const map = {
    Dashboard:  focused ? '🏠' : '🏡',
    Planning:   focused ? '📅' : '📆',
    Notes:      focused ? '📝' : '📄',
    Presences:  focused ? '✅' : '☑️',
    Paiements:  focused ? '💰' : '💳',
    Messages:   focused ? '💬' : '🗨️',
    Bulletins:  focused ? '📊' : '📋',
    Profile:    focused ? '👤' : '👥',
    MesGroupes: focused ? '📚' : '📖',
    Eleves:     focused ? '👦' : '👤',
    Absences:   focused ? '✅' : '☑️',
    Finance:    focused ? '💰' : '💳',
  };
  return map[name] || '📌';
}

const tabScreenOptions = (route) => ({
  tabBarIcon: ({ focused }) => <Text>{tabIcon(route.name, focused)}</Text>,
  tabBarActiveTintColor: colors.primary,
  tabBarInactiveTintColor: '#94a3b8',
  headerStyle: { backgroundColor: colors.primary },
  headerTintColor: '#fff',
});

// ── Parent Tabs ──
function ParentTabs() {
  return (
    <ParentTab.Navigator screenOptions={({ route }) => tabScreenOptions(route)}>
      <ParentTab.Screen name="Dashboard" component={ParentDashboard}  options={{ title: 'Accueil' }} />
      <ParentTab.Screen name="Planning"  component={ParentPlanning}   options={{ title: 'Planning' }} />
      <ParentTab.Screen name="Notes"     component={ParentNotes}      options={{ title: 'Notes' }} />
      <ParentTab.Screen name="Presences" component={ParentPresences}  options={{ title: 'Présences' }} />
      <ParentTab.Screen name="Paiements" component={ParentPaiements}  options={{ title: 'Paiements' }} />
      <ParentTab.Screen name="Messages"  component={ParentMessages}   options={{ title: 'Messages' }} />
      <ParentTab.Screen name="Bulletins" component={ParentBulletins}  options={{ title: 'Bulletins' }} />
      <ParentTab.Screen name="Profile"   component={ParentProfile}    options={{ title: 'Profil' }} />
    </ParentTab.Navigator>
  );
}

// ── Enseignant Tabs + Stack (pour Presences qui a des params) ──
function EnseignantTabs() {
  return (
    <EnseignantTab.Navigator screenOptions={({ route }) => tabScreenOptions(route)}>
      <EnseignantTab.Screen name="Dashboard" component={EnseignantDashboard} options={{ title: 'Accueil' }} />
      <EnseignantTab.Screen name="Planning"  component={EnseignantPlanning}  options={{ title: 'Planning' }} />
      <EnseignantTab.Screen name="Notes"     component={EnseignantNotes}     options={{ title: 'Notes' }} />
    </EnseignantTab.Navigator>
  );
}

function EnseignantNavigator() {
  return (
    <EnseignantStack.Navigator screenOptions={{ headerStyle: { backgroundColor: colors.primary }, headerTintColor: '#fff' }}>
      <EnseignantStack.Screen name="EnseignantTabs" component={EnseignantTabs} options={{ headerShown: false }} />
      <EnseignantStack.Screen name="Presences"      component={EnseignantPresences} options={{ title: 'Présences' }} />
    </EnseignantStack.Navigator>
  );
}

// ── Admin Tabs ──
function AdminTabs() {
  return (
    <AdminTab.Navigator screenOptions={({ route }) => tabScreenOptions(route)}>
      <AdminTab.Screen name="Dashboard" component={AdminDashboard}  options={{ title: 'Tableau de bord' }} />
      <AdminTab.Screen name="Eleves"    component={AdminEleves}     options={{ title: 'Élèves' }} />
      <AdminTab.Screen name="Absences"  component={AdminAbsences}   options={{ title: 'Absences' }} />
    </AdminTab.Navigator>
  );
}

// ── Auth ──
function AuthNavigator() {
  return (
    <AuthStack.Navigator screenOptions={{ headerShown: false }}>
      <AuthStack.Screen name="Login" component={LoginScreen} />
    </AuthStack.Navigator>
  );
}

// ── Root ──
export default function AppNavigator() {
  const { isAuthenticated, isLoading, user } = useAuth();

  if (isLoading) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  if (!isAuthenticated) return <AuthNavigator />;

  // Routage selon le rôle
  const role = user?.role || user?.role_nom || '';
  if (role === 'enseignant') return <EnseignantNavigator />;
  if (['admin', 'admin_centre', 'secretaire', 'super_admin'].includes(role)) return <AdminTabs />;
  return <ParentTabs />; // défaut = parent
}
```

---

## ORDRE D'EXÉCUTION DEEPSEEK

```bash
# 0. Attendre merge PR #7 (M14 Entretien) dans main, puis synchroniser
git checkout develop
git pull origin main

# 1. Mettre à jour endpoints.js (ajouter enseignantApi + adminApi)
modify: edugestdz/mobile/src/api/endpoints.js

# 2. Screens Enseignant
replace: edugestdz/mobile/src/screens/enseignant/DashboardScreen.js  (remplacer le placeholder)
create:  edugestdz/mobile/src/screens/enseignant/PlanningScreen.js
create:  edugestdz/mobile/src/screens/enseignant/PresencesScreen.js
create:  edugestdz/mobile/src/screens/enseignant/NotesScreen.js

# 3. Screens Admin (créer le dossier)
create:  edugestdz/mobile/src/screens/admin/DashboardScreen.js
create:  edugestdz/mobile/src/screens/admin/ElevesScreen.js
create:  edugestdz/mobile/src/screens/admin/AbsencesScreen.js

# 4. Mettre à jour AppNavigator.js (ajouter EnseignantTabs + AdminTabs)
replace: edugestdz/mobile/src/navigation/AppNavigator.js

# 5. Vérifier que le projet compile (pas de tests Jest ici — projet React Native)
cd edugestdz/mobile
npx expo export --platform all --dev 2>&1 | tail -20
# → Doit terminer sans erreur de syntaxe

# 6. Si OK — pas de backend modifié donc pas de migration nécessaire
cd ../..
git add edugestdz/mobile/
git commit -m "feat: Mobile — Screens Enseignant (Planning+Présences+Notes) + Admin (Dashboard+Élèves+Absences) + Navigation par rôle"
git push origin develop

# 7. Ouvrir PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Dossier de travail : edugestdz/mobile/
Branche : develop
Attends merge PR #7 (M14 Entretien) dans main, puis :
git checkout develop && git pull origin main

Fichier : MISSION_P5_MOBILE_SCREENS.md — 7 étapes dans l'ordre.

IMPORTANT : ce sont des fichiers React Native (JS), pas PHP.
Pas de tests PHPUnit ici — vérifier la compilation avec :
  cd edugestdz/mobile && npx expo export --platform all --dev

La navigation est basée sur le rôle de l'utilisateur connecté :
- role = 'enseignant' → EnseignantNavigator
- role = 'admin' / 'admin_centre' / 'secretaire' → AdminTabs
- autre (parent) → ParentTabs (existant)

Commit + push + PR develop → main à la fin.
```
