import React, { useState, useEffect } from 'react';
import {
  View, Text, FlatList, TouchableOpacity,
  StyleSheet, ActivityIndicator, RefreshControl,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';

const BASE = 'https://app.edugest.dz/api/v1';

const TYPE_CONFIG = {
  note:        { emoji: '📝', color: '#60a5fa', label: 'Note' },
  bulletin:    { emoji: '📄', color: '#a78bfa', label: 'Bulletin' },
  absence:     { emoji: '⚠️', color: '#fb923c', label: 'Absence' },
  signalement: { emoji: '📋', color: '#f87171', label: 'Signalement' },
  convocation: { emoji: '📅', color: '#ef4444', label: 'Convocation' },
  paiement:    { emoji: '💳', color: '#4ade80', label: 'Paiement' },
  diagnostic:  { emoji: '🔬', color: '#fb923c', label: 'Niveau' },
  message:     { emoji: '💬', color: '#60a5fa', label: 'Message' },
  autre:       { emoji: '🔔', color: '#94a3b8', label: 'Notification' },
};

export default function NotificationsScreen() {
  const [notifs, setNotifs]         = useState([]);
  const [nonLues, setNonLues]       = useState(0);
  const [loading, setLoading]       = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  useEffect(() => { loadNotifications(); }, []);

  const headers = async () => {
    const token    = await AsyncStorage.getItem('token');
    const tenantId = await AsyncStorage.getItem('tenantId');
    return { 'Authorization': `Bearer ${token}`, 'X-Tenant-ID': tenantId ?? '' };
  };

  const loadNotifications = async () => {
    const h = await headers();
    const r = await fetch(`${BASE}/notifications/parent?per_page=50`, { headers: h }).then(r => r.json());
    setNotifs(r?.data?.data ?? []);
    setNonLues(r?.non_lues ?? 0);
    setLoading(false);
    setRefreshing(false);
  };

  const marquerLue = async (id) => {
    const h = await headers();
    await fetch(`${BASE}/notifications/parent/${id}/lire`, { method: 'POST', headers: h });
    setNotifs(prev => prev.map(n => n.id === id ? { ...n, lu: true } : n));
    setNonLues(prev => Math.max(0, prev - 1));
  };

  const toutLire = async () => {
    const h = await headers();
    await fetch(`${BASE}/notifications/parent/tout-lire`, { method: 'POST', headers: h });
    setNotifs(prev => prev.map(n => ({ ...n, lu: true })));
    setNonLues(0);
  };

  const formatDate = (dateStr) => {
    const d = new Date(dateStr);
    const now = new Date();
    const diffH = Math.floor((now - d) / 3600000);
    if (diffH < 1)  return "À l'instant";
    if (diffH < 24) return `Il y a ${diffH}h`;
    return d.toLocaleDateString('fr-DZ', { day: '2-digit', month: 'short' });
  };

  const renderNotif = ({ item }) => {
    const cfg = TYPE_CONFIG[item.type] ?? TYPE_CONFIG.autre;
    return (
      <TouchableOpacity
        style={[s.card, !item.lu && s.cardUnread]}
        onPress={() => !item.lu && marquerLue(item.id)}
      >
        <View style={[s.iconWrap, { backgroundColor: cfg.color + '22' }]}>
          <Text style={{ fontSize: 20 }}>{cfg.emoji}</Text>
        </View>
        <View style={{ flex: 1 }}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 2 }}>
            <Text style={[s.cardTitre, !item.lu && s.cardTitreUnread]}>{item.titre}</Text>
            {!item.lu && <View style={[s.dot, { backgroundColor: cfg.color }]} />}
          </View>
          <Text style={s.cardCorps} numberOfLines={2}>{item.corps}</Text>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: 4 }}>
            <Text style={s.cardDate}>{formatDate(item.created_at)}</Text>
            {item.eleve && (
              <Text style={s.cardEleve}>
                {item.eleve.prenom} {item.eleve.nom}
              </Text>
            )}
          </View>
        </View>
      </TouchableOpacity>
    );
  };

  return (
    <View style={s.container}>
      <View style={s.header}>
        <View>
          <Text style={s.title}>🔔 Notifications</Text>
          {nonLues > 0 && (
            <Text style={s.badge}>{nonLues} non lue{nonLues > 1 ? 's' : ''}</Text>
          )}
        </View>
        {nonLues > 0 && (
          <TouchableOpacity onPress={toutLire} style={s.toutLireBtn}>
            <Text style={s.toutLireTxt}>Tout lire</Text>
          </TouchableOpacity>
        )}
      </View>

      {loading ? (
        <ActivityIndicator size="large" color="#3b82f6" style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={notifs}
          keyExtractor={n => n.id}
          renderItem={renderNotif}
          refreshControl={
            <RefreshControl refreshing={refreshing}
              onRefresh={() => { setRefreshing(true); loadNotifications(); }}
              tintColor="#3b82f6" />
          }
          ListEmptyComponent={
            <Text style={s.empty}>Aucune notification pour le moment.</Text>
          }
        />
      )}
    </View>
  );
}

const s = StyleSheet.create({
  container:      { flex: 1, backgroundColor: '#08090f', padding: 16 },
  header:         { flexDirection: 'row', justifyContent: 'space-between',
                    alignItems: 'flex-start', marginBottom: 16 },
  title:          { fontSize: 20, fontWeight: '900', color: '#fff' },
  badge:          { fontSize: 11, color: '#f87171', fontWeight: '700', marginTop: 2 },
  toutLireBtn:    { backgroundColor: '#1e293b', borderRadius: 8, padding: 8 },
  toutLireTxt:    { color: '#60a5fa', fontSize: 11, fontWeight: '700' },
  card:           { backgroundColor: '#111318', borderRadius: 12, padding: 14,
                    marginBottom: 8, flexDirection: 'row', gap: 12,
                    borderWidth: 1, borderColor: '#1e293b' },
  cardUnread:     { borderColor: '#3b82f6', backgroundColor: '#0c1a30' },
  iconWrap:       { width: 44, height: 44, borderRadius: 12, alignItems: 'center',
                    justifyContent: 'center', flexShrink: 0 },
  cardTitre:      { fontSize: 12, fontWeight: '700', color: '#94a3b8' },
  cardTitreUnread:{ color: '#f1f5f9' },
  cardCorps:      { fontSize: 11, color: '#64748b', lineHeight: 16 },
  cardDate:       { fontSize: 9, color: '#475569' },
  cardEleve:      { fontSize: 9, color: '#60a5fa' },
  dot:            { width: 8, height: 8, borderRadius: 4 },
  empty:          { color: '#475569', textAlign: 'center', marginTop: 40, fontSize: 13 },
});
