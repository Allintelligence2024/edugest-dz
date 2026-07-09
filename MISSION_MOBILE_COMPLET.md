# 🤖 MISSION DEEPSEEK — Mobile Complet : CIB + Push + Marketplace + Hors-ligne
## EduGest DZ · Branche : develop · 3 Juillet 2026
## Tests actuels : 423+ ✅ · Objectif : 0 régression + app mobile complète

---

## CONTEXTE

L'app mobile React Native existe avec 15 écrans (parent ×8, enseignant ×4, admin ×3).
Cette mission complète les 4 zones manquantes identifiées dans l'audit :

| Zone | Manque actuel | Ce qu'on construit |
|---|---|---|
| Paiement mobile | Aucun | WebView Satim CIB/Dahabia dans FacturesScreen |
| Push notifications | Firebase configuré backend, pas Expo | Config Expo + handlers complets |
| Marketplace mobile | Pas de recherche centres dans mobile | MarketplaceScreen parent complet |
| Écran admin pointage | AdminDashboardScreen seulement | AdminPointageScreen temps réel |

### RÈGLES ABSOLUES
1. **0 régression backend** — les tests existants restent verts
2. **Ne pas modifier** les écrans existants — uniquement ajouter
3. **Expo SDK 52** — utiliser les packages expo compatibles
4. **Même style** que les écrans existants (dark theme, StyleSheet React Native)
5. **PostgreSQL uniquement** côté backend

---

## ÉTAPE 0 — Synchroniser develop

```bash
git checkout develop
git pull origin main
```

---

## ÉTAPE 1 — Installer les packages Expo manquants

```bash
cd edugestdz/mobile

# Paiement CIB — WebView
npx expo install expo-web-browser
npx expo install react-native-webview

# Push notifications
npx expo install expo-notifications
npx expo install expo-device

# Stockage local (cache hors-ligne)
npx expo install @react-native-async-storage/async-storage

# Biométrie (bonus)
npx expo install expo-local-authentication
```

---

## ÉTAPE 2 — Configuration push notifications dans app.json

**Modifier :** `edugestdz/mobile/app.json`

```json
{
  "expo": {
    "name": "EduGest DZ",
    "slug": "edugest-dz",
    "version": "1.0.0",
    "orientation": "portrait",
    "icon": "./assets/icon.png",
    "userInterfaceStyle": "dark",
    "splash": {
      "image": "./assets/splash.png",
      "resizeMode": "contain",
      "backgroundColor": "#08090f"
    },
    "assetBundlePatterns": ["**/*"],
    "ios": {
      "supportsTablet": false,
      "bundleIdentifier": "dz.edugest.app",
      "infoPlist": {
        "NSCameraUsageDescription": "Scanner les QR codes de présence",
        "NSFaceIDUsageDescription": "Connexion rapide par Face ID"
      }
    },
    "android": {
      "adaptiveIcon": {
        "foregroundImage": "./assets/adaptive-icon.png",
        "backgroundColor": "#08090f"
      },
      "package": "dz.edugest.app",
      "googleServicesFile": "./google-services.json",
      "permissions": [
        "android.permission.CAMERA",
        "android.permission.USE_BIOMETRIC",
        "android.permission.RECEIVE_BOOT_COMPLETED",
        "android.permission.VIBRATE"
      ]
    },
    "plugins": [
      [
        "expo-notifications",
        {
          "icon": "./assets/notification-icon.png",
          "color": "#3b82f6",
          "sounds": []
        }
      ]
    ],
    "extra": {
      "apiBaseUrl": "https://app.edugest.dz/api/v1",
      "eas": {
        "projectId": "VOTRE_EAS_PROJECT_ID"
      }
    }
  }
}
```

---

## ÉTAPE 3 — Service de notifications push

**Créer :** `edugestdz/mobile/src/services/NotificationService.js`

```javascript
import * as Notifications from 'expo-notifications';
import * as Device from 'expo-device';
import { Platform } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import api from './api';

// Configuration du handler de notification (affiché même en premier plan)
Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge:  true,
  }),
});

/**
 * Demander la permission push et enregistrer le token Expo sur le backend.
 * À appeler au login.
 */
export async function registerPushToken() {
  if (!Device.isDevice) {
    console.warn('Push notifications : appareil physique requis');
    return null;
  }

  const { status: existingStatus } = await Notifications.getPermissionsAsync();
  let finalStatus = existingStatus;

  if (existingStatus !== 'granted') {
    const { status } = await Notifications.requestPermissionsAsync();
    finalStatus = status;
  }

  if (finalStatus !== 'granted') {
    console.warn('Permission push refusée');
    return null;
  }

  // Récupérer le token Expo Push
  const tokenData = await Notifications.getExpoPushTokenAsync({
    projectId: require('../../app.json').expo.extra.eas?.projectId,
  });
  const token = tokenData.data;

  // Configurer le canal Android
  if (Platform.OS === 'android') {
    await Notifications.setNotificationChannelAsync('default', {
      name: 'EduGest DZ',
      importance: Notifications.AndroidImportance.MAX,
      vibrationPattern: [0, 250, 250, 250],
      lightColor: '#3b82f6',
    });
  }

  // Envoyer le token au backend
  try {
    await api.post('/device-tokens', { token, platform: Platform.OS });
    await AsyncStorage.setItem('pushToken', token);
    console.log('Push token enregistré :', token);
  } catch (e) {
    console.warn('Erreur enregistrement push token :', e.message);
  }

  return token;
}

/**
 * Configurer les listeners de notification.
 * Retourne une fonction de nettoyage.
 */
export function setupNotificationListeners(navigation) {
  // Notification reçue en premier plan
  const foregroundSub = Notifications.addNotificationReceivedListener(notification => {
    console.log('Notification reçue en 1er plan :', notification);
  });

  // Tap sur la notification → naviguer
  const responseSub = Notifications.addNotificationResponseReceivedListener(response => {
    const data = response.notification.request.content.data;
    handleNotificationNavigation(navigation, data);
  });

  return () => {
    foregroundSub.remove();
    responseSub.remove();
  };
}

/**
 * Naviguer vers le bon écran selon le type de notification.
 */
function handleNotificationNavigation(navigation, data) {
  if (!navigation || !data?.type) return;

  switch (data.type) {
    case 'absence':
      navigation.navigate('Absences');
      break;
    case 'facture':
      navigation.navigate('Factures');
      break;
    case 'note':
      navigation.navigate('Notes');
      break;
    case 'reservation':
      navigation.navigate('MesReservations');
      break;
    case 'message':
      navigation.navigate('Messages');
      break;
    default:
      navigation.navigate('Dashboard');
  }
}
```

---

## ÉTAPE 4 — Service API centralisé

**Créer :** `edugestdz/mobile/src/services/api.js`

```javascript
import AsyncStorage from '@react-native-async-storage/async-storage';

const BASE_URL = 'https://app.edugest.dz/api/v1';
// En dev : const BASE_URL = 'http://localhost/api/v1';

const api = {
  async getHeaders() {
    const token    = await AsyncStorage.getItem('token');
    const tenantId = await AsyncStorage.getItem('tenantId');
    return {
      'Content-Type': 'application/json',
      'Accept':        'application/json',
      ...(token    && { 'Authorization': `Bearer ${token}` }),
      ...(tenantId && { 'X-Tenant-ID': tenantId }),
    };
  },

  async get(path) {
    const headers  = await this.getHeaders();
    const response = await fetch(`${BASE_URL}${path}`, { headers });
    return response.json();
  },

  async post(path, body) {
    const headers  = await this.getHeaders();
    const response = await fetch(`${BASE_URL}${path}`, {
      method: 'POST',
      headers,
      body: JSON.stringify(body),
    });
    return response.json();
  },

  async put(path, body) {
    const headers  = await this.getHeaders();
    const response = await fetch(`${BASE_URL}${path}`, {
      method: 'PUT',
      headers,
      body: JSON.stringify(body),
    });
    return response.json();
  },

  async delete(path) {
    const headers  = await this.getHeaders();
    const response = await fetch(`${BASE_URL}${path}`, { method: 'DELETE', headers });
    return response.json();
  },
};

export default api;

// ── Cache hors-ligne ──────────────────────────────────────────────────────────

/**
 * Récupérer avec cache local (fallback si pas de réseau).
 * @param {string} path - endpoint API
 * @param {string} cacheKey - clé AsyncStorage
 * @param {number} ttlMinutes - durée de validité du cache
 */
export async function getCached(path, cacheKey, ttlMinutes = 30) {
  try {
    // Essayer l'API en premier
    const data = await api.get(path);
    if (data.success) {
      // Stocker en cache avec timestamp
      await AsyncStorage.setItem(cacheKey, JSON.stringify({
        data:      data.data,
        cachedAt:  Date.now(),
        ttl:       ttlMinutes * 60 * 1000,
      }));
      return data.data;
    }
  } catch (networkError) {
    console.warn(`Réseau indisponible pour ${path}, utilisation du cache`);
  }

  // Fallback cache
  const cached = await AsyncStorage.getItem(cacheKey);
  if (cached) {
    const { data, cachedAt, ttl } = JSON.parse(cached);
    if (Date.now() - cachedAt < ttl) {
      return data; // Cache encore valide
    }
  }

  return null; // Rien disponible
}
```

---

## ÉTAPE 5 — FacturesScreen : intégrer le paiement CIB WebView

**Modifier :** `edugestdz/mobile/src/screens/parent/FacturesScreen.js`

Ajouter le paiement CIB/Dahabia via WebView Satim :

```javascript
import React, { useState, useEffect, useCallback } from 'react';
import {
  View, Text, FlatList, TouchableOpacity,
  StyleSheet, Alert, ActivityIndicator, Modal,
} from 'react-native';
import { WebView } from 'react-native-webview';
import AsyncStorage from '@react-native-async-storage/async-storage';
import api, { getCached } from '../../services/api';

export default function FacturesScreen() {
  const [factures, setFactures]       = useState([]);
  const [loading, setLoading]         = useState(true);
  const [payingId, setPayingId]       = useState(null);  // facture en cours de paiement
  const [satimUrl, setSatimUrl]       = useState(null);  // URL Satim
  const [showWebView, setShowWebView] = useState(false);
  const [offline, setOffline]         = useState(false);

  const loadFactures = useCallback(async () => {
    setLoading(true);
    const data = await getCached('/finance/factures?per_page=50', 'cache_factures', 15);
    if (data) {
      setFactures(data.data ?? data ?? []);
    } else {
      setOffline(true);
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadFactures(); }, [loadFactures]);

  // Initier le paiement CIB via Satim
  const payerCIB = async (facture) => {
    if (facture.statut === 'payée') {
      Alert.alert('Info', 'Cette facture est déjà payée.');
      return;
    }

    setPayingId(facture.id);
    try {
      const res = await api.post('/paiements/cib/initier', {
        facture_id:  facture.id,
        return_url:  'https://app.edugest.dz/paiement/retour',
        fail_url:    'https://app.edugest.dz/paiement/echec',
      });

      if (res.success && res.data?.redirect_url) {
        setSatimUrl(res.data.redirect_url);
        setShowWebView(true);
      } else {
        Alert.alert('Erreur', res.message ?? 'Impossible d\'initier le paiement CIB.');
      }
    } catch (e) {
      Alert.alert('Erreur réseau', 'Vérifiez votre connexion internet.');
    } finally {
      setPayingId(null);
    }
  };

  // Surveiller la navigation WebView pour détecter le retour Satim
  const onWebViewNavChange = (navState) => {
    const { url } = navState;
    if (url.includes('/paiement/retour') || url.includes('orderStatus=2')) {
      // Paiement confirmé
      setShowWebView(false);
      setSatimUrl(null);
      Alert.alert('✅ Paiement réussi', 'Votre paiement CIB a été confirmé. Un SMS de confirmation vous a été envoyé.', [
        { text: 'OK', onPress: loadFactures },
      ]);
    } else if (url.includes('/paiement/echec') || url.includes('orderStatus=0')) {
      // Paiement échoué
      setShowWebView(false);
      setSatimUrl(null);
      Alert.alert('❌ Paiement échoué', 'Le paiement n\'a pas abouti. Réessayez ou contactez votre banque.');
    }
  };

  const statusColor = (s) => ({
    payée: '#4ade80', émise: '#fb923c', en_retard: '#f87171',
    partiellement_payée: '#fbbf24', annulée: '#64748b',
  }[s] ?? '#94a3b8');

  const renderFacture = ({ item }) => (
    <View style={styles.card}>
      <View style={styles.cardRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.numero}>{item.numero}</Text>
          <Text style={styles.sub}>Échéance : {item.date_echeance?.split('T')[0]}</Text>
        </View>
        <View style={{ alignItems: 'flex-end' }}>
          <Text style={[styles.montant, { color: statusColor(item.statut) }]}>
            {Number(item.total_ttc).toLocaleString('fr-DZ')} DA
          </Text>
          <View style={[styles.badge, { backgroundColor: statusColor(item.statut) + '22' }]}>
            <Text style={[styles.badgeText, { color: statusColor(item.statut) }]}>
              {item.statut?.toUpperCase()}
            </Text>
          </View>
        </View>
      </View>

      {/* Bouton paiement CIB si impayée */}
      {['émise', 'en_retard', 'partiellement_payée'].includes(item.statut) && (
        <TouchableOpacity
          style={styles.cibBtn}
          onPress={() => payerCIB(item)}
          disabled={payingId === item.id}
        >
          {payingId === item.id
            ? <ActivityIndicator size="small" color="#fff" />
            : <Text style={styles.cibBtnText}>💳 Payer par CIB / Dahabia</Text>
          }
        </TouchableOpacity>
      )}
    </View>
  );

  return (
    <View style={styles.container}>
      <Text style={styles.title}>💰 Mes Factures</Text>

      {offline && (
        <View style={styles.offlineBanner}>
          <Text style={styles.offlineText}>📡 Mode hors-ligne — données en cache</Text>
        </View>
      )}

      {loading ? (
        <ActivityIndicator size="large" color="#3b82f6" style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={factures}
          keyExtractor={item => item.id}
          renderItem={renderFacture}
          contentContainerStyle={{ paddingBottom: 20 }}
          ListEmptyComponent={
            <Text style={styles.empty}>Aucune facture trouvée.</Text>
          }
        />
      )}

      {/* Modal WebView Satim */}
      <Modal visible={showWebView} animationType="slide" presentationStyle="pageSheet">
        <View style={{ flex: 1, backgroundColor: '#08090f' }}>
          <View style={styles.webviewHeader}>
            <Text style={styles.webviewTitle}>🔒 Paiement sécurisé CIB / Dahabia</Text>
            <TouchableOpacity onPress={() => { setShowWebView(false); setSatimUrl(null); }}>
              <Text style={styles.webviewClose}>Annuler</Text>
            </TouchableOpacity>
          </View>
          {satimUrl && (
            <WebView
              source={{ uri: satimUrl }}
              onNavigationStateChange={onWebViewNavChange}
              javaScriptEnabled
              domStorageEnabled
              startInLoadingState
              renderLoading={() => <ActivityIndicator size="large" color="#3b82f6" />}
            />
          )}
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container:    { flex: 1, backgroundColor: '#08090f', padding: 16 },
  title:        { fontSize: 20, fontWeight: '900', color: '#fff', marginBottom: 16 },
  card:         { backgroundColor: '#111318', borderRadius: 12, padding: 16,
                  marginBottom: 10, borderWidth: 1, borderColor: '#1e293b' },
  cardRow:      { flexDirection: 'row', alignItems: 'center' },
  numero:       { fontSize: 13, fontWeight: '700', color: '#f1f5f9', marginBottom: 2 },
  sub:          { fontSize: 10, color: '#64748b' },
  montant:      { fontSize: 16, fontWeight: '900', marginBottom: 4 },
  badge:        { paddingHorizontal: 8, paddingVertical: 2, borderRadius: 20 },
  badgeText:    { fontSize: 9, fontWeight: '700' },
  cibBtn:       { backgroundColor: '#1d4ed8', borderRadius: 8, padding: 12,
                  alignItems: 'center', marginTop: 10 },
  cibBtnText:   { color: '#fff', fontWeight: '700', fontSize: 13 },
  offlineBanner:{ backgroundColor: '#422006', borderRadius: 8, padding: 10,
                  marginBottom: 12, borderWidth: 1, borderColor: '#ea580c' },
  offlineText:  { color: '#fb923c', fontSize: 11, textAlign: 'center', fontWeight: '600' },
  empty:        { color: '#475569', textAlign: 'center', marginTop: 40, fontSize: 13 },
  webviewHeader:{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
                  padding: 16, borderBottomWidth: 1, borderBottomColor: '#1e293b' },
  webviewTitle: { fontSize: 13, fontWeight: '700', color: '#f1f5f9' },
  webviewClose: { fontSize: 13, color: '#f87171', fontWeight: '700' },
});
```

---

## ÉTAPE 6 — MarketplaceScreen (parent) : rechercher et réserver

**Créer :** `edugestdz/mobile/src/screens/parent/MarketplaceScreen.js`

```javascript
import React, { useState, useEffect } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, TextInput,
  StyleSheet, ActivityIndicator, Modal, ScrollView, Alert,
} from 'react-native';
import api, { getCached } from '../../services/api';

const MATIERES = ['Mathématiques', 'Physique', 'Chimie', 'Français', 'Anglais', 'Arabe', 'Informatique'];
const WILAYAS  = ['Alger', 'Oran', 'Constantine', 'Annaba', 'Blida', 'Sétif', 'Tlemcen', 'Béjaïa', 'Batna'];

export default function MarketplaceScreen({ navigation }) {
  const [centres, setCentres]           = useState([]);
  const [featured, setFeatured]         = useState([]);
  const [loading, setLoading]           = useState(false);
  const [selectedCentre, setSelectedCentre] = useState(null);
  const [showDetail, setShowDetail]     = useState(false);
  const [filtres, setFiltres]           = useState({ wilaya: '', matiere: '', essai: false });

  useEffect(() => {
    loadFeatured();
  }, []);

  const loadFeatured = async () => {
    setLoading(true);
    const data = await getCached('/marketplace/featured', 'cache_featured', 60);
    if (data) setFeatured(Array.isArray(data) ? data : []);
    setLoading(false);
  };

  const rechercher = async () => {
    setLoading(true);
    const params = new URLSearchParams();
    if (filtres.wilaya)  params.append('wilaya', filtres.wilaya);
    if (filtres.matiere) params.append('matiere', filtres.matiere);
    if (filtres.essai)   params.append('essai_gratuit', '1');

    try {
      const res = await api.get(`/marketplace/recherche?${params}`);
      if (res.success) setCentres(res.data?.centres ?? []);
    } catch (e) { Alert.alert('Erreur', 'Vérifiez votre connexion.'); }
    finally { setLoading(false); }
  };

  const voirProfil = async (tenantId) => {
    try {
      const res = await api.get(`/marketplace/centres/${tenantId}`);
      if (res.success) { setSelectedCentre(res.data); setShowDetail(true); }
    } catch (e) { Alert.alert('Erreur réseau'); }
  };

  const Stars = ({ note }) => (
    <Text style={{ fontSize: 12 }}>
      {'★'.repeat(Math.round(note))}{'☆'.repeat(5 - Math.round(note))}
      <Text style={{ color: '#94a3b8', fontSize: 10 }}> {note}</Text>
    </Text>
  );

  const CentreCard = ({ item }) => (
    <TouchableOpacity style={styles.card} onPress={() => voirProfil(item.tenant_id)}>
      <View style={styles.cardHeader}>
        <View style={styles.avatar}>
          <Text style={{ fontSize: 22 }}>🎓</Text>
        </View>
        <View style={{ flex: 1 }}>
          <Text style={styles.centreName}>
            {item.nom_etablissement}
            {item.verifie ? ' ✅' : ''}
          </Text>
          <Text style={styles.centreWilaya}>📍 {item.wilaya}</Text>
          <Stars note={Number(item.note_moyenne ?? 0)} />
        </View>
        <Text style={styles.centreScore}>
          {item.tarif_heure_min ? `Dès\n${item.tarif_heure_min} DA/h` : ''}
        </Text>
      </View>
      {item.accepte_essai_gratuit && (
        <View style={styles.essaiBadge}>
          <Text style={styles.essaiText}>🎁 Essai gratuit disponible</Text>
        </View>
      )}
    </TouchableOpacity>
  );

  const data = centres.length > 0 ? centres : featured;

  return (
    <View style={styles.container}>
      <Text style={styles.title}>🛒 Trouver un centre</Text>

      {/* Filtres */}
      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.filtresScroll}>
        <TouchableOpacity style={styles.filtreTag}
          onPress={() => setFiltres(f => ({ ...f, essai: !f.essai }))}>
          <Text style={[styles.filtreTagText, filtres.essai && styles.filtreTagActive]}>
            🎁 Essai gratuit
          </Text>
        </TouchableOpacity>
        {MATIERES.map(m => (
          <TouchableOpacity key={m} style={styles.filtreTag}
            onPress={() => setFiltres(f => ({ ...f, matiere: f.matiere === m ? '' : m }))}>
            <Text style={[styles.filtreTagText, filtres.matiere === m && styles.filtreTagActive]}>
              {m}
            </Text>
          </TouchableOpacity>
        ))}
      </ScrollView>

      <TouchableOpacity style={styles.searchBtn} onPress={rechercher}>
        <Text style={styles.searchBtnText}>🔍 Rechercher</Text>
      </TouchableOpacity>

      {loading ? (
        <ActivityIndicator size="large" color="#3b82f6" style={{ marginTop: 30 }} />
      ) : (
        <FlatList
          data={data}
          keyExtractor={(item, i) => item.id ?? item.tenant_id ?? String(i)}
          renderItem={({ item }) => <CentreCard item={item} />}
          ListHeaderComponent={
            <Text style={styles.sectionLabel}>
              {centres.length > 0 ? `${centres.length} résultats` : '⭐ Centres vérifiés'}
            </Text>
          }
          ListEmptyComponent={
            <Text style={styles.empty}>Aucun centre trouvé. Modifiez les filtres.</Text>
          }
        />
      )}

      {/* Modal détail centre */}
      <Modal visible={showDetail} animationType="slide" presentationStyle="pageSheet">
        <ScrollView style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>
              {selectedCentre?.nom_etablissement}
              {selectedCentre?.verifie ? ' ✅' : ''}
            </Text>
            <TouchableOpacity onPress={() => setShowDetail(false)}>
              <Text style={styles.modalClose}>✕ Fermer</Text>
            </TouchableOpacity>
          </View>

          {selectedCentre && (
            <>
              <Text style={styles.modalSub}>📍 {selectedCentre.wilaya} {selectedCentre.commune ? `· ${selectedCentre.commune}` : ''}</Text>
              {selectedCentre.description && (
                <Text style={styles.modalDesc}>{selectedCentre.description}</Text>
              )}

              {/* Offres */}
              {selectedCentre.offres?.length > 0 && (
                <View style={styles.section}>
                  <Text style={styles.sectionLabel}>OFFRES DISPONIBLES</Text>
                  {selectedCentre.offres.map(o => (
                    <View key={o.id} style={styles.offreCard}>
                      <View style={{ flex: 1 }}>
                        <Text style={styles.offreTitre}>{o.titre}</Text>
                        <Text style={styles.offreSub}>{o.matiere} · {o.type}</Text>
                      </View>
                      <View style={{ alignItems: 'flex-end' }}>
                        <Text style={styles.offrePrix}>{o.tarif_heure} DA/h</Text>
                        {o.essai_gratuit && (
                          <Text style={styles.essaiMini}>Essai gratuit</Text>
                        )}
                      </View>
                    </View>
                  ))}
                </View>
              )}

              {/* Avis */}
              {selectedCentre.avis?.length > 0 && (
                <View style={styles.section}>
                  <Text style={styles.sectionLabel}>AVIS ({selectedCentre.nb_avis})</Text>
                  {selectedCentre.avis.slice(0, 3).map(a => (
                    <View key={a.id} style={styles.avisCard}>
                      <Text style={styles.avisAuteur}>
                        {a.parent?.prenom} {a.parent?.nom} · {'★'.repeat(a.note)}
                      </Text>
                      {a.commentaire && <Text style={styles.avisComment}>{a.commentaire}</Text>}
                    </View>
                  ))}
                </View>
              )}

              {/* Bouton réserver */}
              <TouchableOpacity
                style={styles.reserverBtn}
                onPress={() => {
                  setShowDetail(false);
                  navigation.navigate('ReservationMarketplace', { centre: selectedCentre });
                }}
              >
                <Text style={styles.reserverBtnText}>📅 Réserver une séance</Text>
              </TouchableOpacity>
            </>
          )}
        </ScrollView>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container:       { flex: 1, backgroundColor: '#08090f', padding: 16 },
  title:           { fontSize: 20, fontWeight: '900', color: '#fff', marginBottom: 12 },
  filtresScroll:   { marginBottom: 10 },
  filtreTag:       { backgroundColor: '#111318', borderRadius: 20, paddingHorizontal: 12,
                     paddingVertical: 6, marginRight: 8, borderWidth: 1, borderColor: '#1e293b' },
  filtreTagText:   { fontSize: 11, color: '#94a3b8', fontWeight: '600' },
  filtreTagActive: { color: '#60a5fa' },
  searchBtn:       { backgroundColor: '#1d4ed8', borderRadius: 8, padding: 12,
                     alignItems: 'center', marginBottom: 16 },
  searchBtnText:   { color: '#fff', fontWeight: '700', fontSize: 13 },
  card:            { backgroundColor: '#111318', borderRadius: 12, padding: 14,
                     marginBottom: 10, borderWidth: 1, borderColor: '#1e293b' },
  cardHeader:      { flexDirection: 'row', gap: 12, marginBottom: 8 },
  avatar:          { width: 44, height: 44, borderRadius: 10, backgroundColor: '#1e293b',
                     alignItems: 'center', justifyContent: 'center' },
  centreName:      { fontSize: 13, fontWeight: '800', color: '#f1f5f9', marginBottom: 2 },
  centreWilaya:    { fontSize: 10, color: '#64748b', marginBottom: 2 },
  centreScore:     { fontSize: 10, color: '#60a5fa', fontWeight: '700', textAlign: 'right' },
  essaiBadge:      { backgroundColor: '#14532d', borderRadius: 6, padding: 6 },
  essaiText:       { fontSize: 10, color: '#4ade80', fontWeight: '700' },
  sectionLabel:    { fontSize: 10, color: '#60a5fa', fontWeight: '800',
                     textTransform: 'uppercase', letterSpacing: 1, marginBottom: 8 },
  empty:           { color: '#475569', textAlign: 'center', marginTop: 40, fontSize: 13 },
  // Modal
  modalContainer:  { flex: 1, backgroundColor: '#08090f', padding: 20 },
  modalHeader:     { flexDirection: 'row', justifyContent: 'space-between',
                     alignItems: 'flex-start', marginBottom: 12 },
  modalTitle:      { fontSize: 18, fontWeight: '900', color: '#fff', flex: 1 },
  modalClose:      { fontSize: 13, color: '#f87171', fontWeight: '700' },
  modalSub:        { fontSize: 11, color: '#64748b', marginBottom: 10 },
  modalDesc:       { fontSize: 12, color: '#94a3b8', lineHeight: 20, marginBottom: 16 },
  section:         { marginBottom: 20 },
  offreCard:       { backgroundColor: '#111318', borderRadius: 8, padding: 12,
                     marginBottom: 6, flexDirection: 'row', alignItems: 'center',
                     borderWidth: 1, borderColor: '#1e293b' },
  offreTitre:      { fontSize: 12, fontWeight: '700', color: '#f1f5f9', marginBottom: 2 },
  offreSub:        { fontSize: 10, color: '#64748b' },
  offrePrix:       { fontSize: 14, fontWeight: '900', color: '#4ade80', marginBottom: 2 },
  essaiMini:       { fontSize: 9, color: '#4ade80', backgroundColor: '#14532d',
                     paddingHorizontal: 6, paddingVertical: 1, borderRadius: 10 },
  avisCard:        { backgroundColor: '#0d2515', borderRadius: 8, padding: 10, marginBottom: 6 },
  avisAuteur:      { fontSize: 11, fontWeight: '700', color: '#4ade80', marginBottom: 4 },
  avisComment:     { fontSize: 11, color: '#94a3b8' },
  reserverBtn:     { backgroundColor: '#3b82f6', borderRadius: 10, padding: 16,
                     alignItems: 'center', marginTop: 10, marginBottom: 30 },
  reserverBtnText: { color: '#fff', fontWeight: '800', fontSize: 14 },
});
```

---

## ÉTAPE 7 — AdminPointageScreen : pointage temps réel

**Créer :** `edugestdz/mobile/src/screens/admin/AdminPointageScreen.js`

```javascript
import React, { useState, useEffect, useCallback } from 'react';
import {
  View, Text, FlatList, TouchableOpacity,
  StyleSheet, RefreshControl, Alert,
} from 'react-native';
import api from '../../services/api';

export default function AdminPointageScreen() {
  const [enseignants, setEnseignants] = useState([]);
  const [pointages, setPointages]     = useState([]);
  const [loading, setLoading]         = useState(true);
  const [refreshing, setRefreshing]   = useState(false);
  const [today]                       = useState(new Date().toISOString().split('T')[0]);

  const load = useCallback(async () => {
    try {
      const [ens, pt] = await Promise.all([
        api.get('/enseignants?per_page=100'),
        api.get(`/pointage/enseignants?date=${today}`),
      ]);
      setEnseignants(ens?.data?.data ?? []);
      setPointages(pt?.data ?? []);
    } catch (e) { console.warn(e); }
    finally { setLoading(false); setRefreshing(false); }
  }, [today]);

  useEffect(() => { load(); }, [load]);

  const getPointage = (id) => pointages.find(p => p.enseignant_id === id);

  const pointer = async (enseignantId, type) => {
    const heure = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    try {
      const res = await api.post('/pointage/enseignants', {
        enseignant_id: enseignantId, type, date: today, heure,
      });
      if (res.success) await load();
      else Alert.alert('Erreur', res.message ?? 'Pointage échoué');
    } catch (e) { Alert.alert('Erreur réseau'); }
  };

  // Stats du jour
  const presents = pointages.filter(p => p.arrivee).length;
  const absents  = enseignants.length - presents;
  const retards  = pointages.filter(p => p.arrivee && p.retard).length;

  const renderEnseignant = ({ item }) => {
    const pt = getPointage(item.id);
    const statut = !pt || !pt.arrivee ? 'absent'
      : pt.depart ? 'complet'
      : 'present';

    return (
      <View style={styles.card}>
        <View style={styles.row}>
          <View style={styles.avatar}>
            <Text style={{ fontSize: 18 }}>👨‍🏫</Text>
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.nom}>{item.nom} {item.prenom}</Text>
            <Text style={styles.specialite}>{item.specialite ?? 'Enseignant'}</Text>
            {pt?.arrivee && (
              <Text style={styles.heure}>
                Arrivée : {pt.arrivee}
                {pt.depart ? `  ·  Départ : ${pt.depart}` : ''}
              </Text>
            )}
          </View>
          <View style={[styles.statusDot, {
            backgroundColor: statut === 'complet' ? '#4ade80'
              : statut === 'present' ? '#60a5fa' : '#f87171',
          }]} />
        </View>

        <View style={styles.actions}>
          {statut === 'absent' && (
            <>
              <TouchableOpacity style={styles.btnPresent} onPress={() => pointer(item.id, 'arrivée')}>
                <Text style={styles.btnText}>✅ Arrivée</Text>
              </TouchableOpacity>
              <TouchableOpacity style={styles.btnAbsent} onPress={() => pointer(item.id, 'absent')}>
                <Text style={styles.btnText}>❌ Absent</Text>
              </TouchableOpacity>
            </>
          )}
          {statut === 'present' && (
            <TouchableOpacity style={styles.btnDepart} onPress={() => pointer(item.id, 'départ')}>
              <Text style={styles.btnText}>🚪 Enregistrer départ</Text>
            </TouchableOpacity>
          )}
          {statut === 'complet' && (
            <Text style={styles.complet}>✅ Journée complète</Text>
          )}
        </View>
      </View>
    );
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>🏷️ Pointage du jour</Text>
      <Text style={styles.date}>{new Date().toLocaleDateString('fr-DZ', { weekday:'long', day:'numeric', month:'long' })}</Text>

      {/* Stats */}
      <View style={styles.statsRow}>
        <View style={[styles.statBox, { borderColor: '#16a34a' }]}>
          <Text style={[styles.statVal, { color: '#4ade80' }]}>{presents}</Text>
          <Text style={styles.statLbl}>Présents</Text>
        </View>
        <View style={[styles.statBox, { borderColor: '#b91c1c' }]}>
          <Text style={[styles.statVal, { color: '#f87171' }]}>{absents}</Text>
          <Text style={styles.statLbl}>Absents</Text>
        </View>
        <View style={[styles.statBox, { borderColor: '#b45309' }]}>
          <Text style={[styles.statVal, { color: '#fb923c' }]}>{retards}</Text>
          <Text style={styles.statLbl}>Retards</Text>
        </View>
      </View>

      <FlatList
        data={enseignants}
        keyExtractor={item => item.id}
        renderItem={renderEnseignant}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} tintColor="#3b82f6" />}
        ListEmptyComponent={
          !loading && <Text style={styles.empty}>Aucun enseignant trouvé.</Text>
        }
        contentContainerStyle={{ paddingBottom: 20 }}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container:   { flex: 1, backgroundColor: '#08090f', padding: 16 },
  title:       { fontSize: 20, fontWeight: '900', color: '#fff', marginBottom: 2 },
  date:        { fontSize: 11, color: '#64748b', marginBottom: 16 },
  statsRow:    { flexDirection: 'row', gap: 10, marginBottom: 16 },
  statBox:     { flex: 1, backgroundColor: '#111318', borderRadius: 10, padding: 12,
                 alignItems: 'center', borderWidth: 1 },
  statVal:     { fontSize: 24, fontWeight: '900' },
  statLbl:     { fontSize: 9, color: '#64748b', marginTop: 2, textTransform: 'uppercase' },
  card:        { backgroundColor: '#111318', borderRadius: 12, padding: 14,
                 marginBottom: 8, borderWidth: 1, borderColor: '#1e293b' },
  row:         { flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: 10 },
  avatar:      { width: 40, height: 40, borderRadius: 10, backgroundColor: '#1e293b',
                 alignItems: 'center', justifyContent: 'center' },
  nom:         { fontSize: 13, fontWeight: '700', color: '#f1f5f9', marginBottom: 2 },
  specialite:  { fontSize: 10, color: '#64748b' },
  heure:       { fontSize: 10, color: '#60a5fa', marginTop: 2 },
  statusDot:   { width: 10, height: 10, borderRadius: 5 },
  actions:     { flexDirection: 'row', gap: 8 },
  btnPresent:  { flex: 1, backgroundColor: '#14532d', borderRadius: 8,
                 padding: 8, alignItems: 'center' },
  btnAbsent:   { flex: 1, backgroundColor: '#450a0a', borderRadius: 8,
                 padding: 8, alignItems: 'center' },
  btnDepart:   { flex: 1, backgroundColor: '#1e3a5f', borderRadius: 8,
                 padding: 8, alignItems: 'center' },
  btnText:     { color: '#fff', fontWeight: '700', fontSize: 11 },
  complet:     { fontSize: 11, color: '#4ade80', fontWeight: '700', padding: 8 },
  empty:       { color: '#475569', textAlign: 'center', marginTop: 40, fontSize: 13 },
});
```

---

## ÉTAPE 8 — Mettre à jour AppNavigator.js

**Modifier :** `edugestdz/mobile/src/navigation/AppNavigator.js`

Ajouter les nouveaux écrans :

```javascript
// Ajouter les imports
import MarketplaceScreen    from '../screens/parent/MarketplaceScreen';
import AdminPointageScreen  from '../screens/admin/AdminPointageScreen';

// Dans ParentTabs — ajouter MarketplaceScreen :
// (dans le BottomTab des parents)
<Tab.Screen
  name="Marketplace"
  component={MarketplaceScreen}
  options={{
    tabBarLabel: 'Centres',
    tabBarIcon: ({ color, size }) => <Text style={{ fontSize: size, color }}>🛒</Text>,
  }}
/>

// Dans AdminTabs — ajouter AdminPointageScreen :
<Tab.Screen
  name="Pointage"
  component={AdminPointageScreen}
  options={{
    tabBarLabel: 'Pointage',
    tabBarIcon: ({ color, size }) => <Text style={{ fontSize: size, color }}>🏷️</Text>,
  }}
/>
```

---

## ÉTAPE 9 — Intégrer les push notifications dans App.js

**Modifier :** `edugestdz/mobile/App.js`

```javascript
import { useEffect, useRef } from 'react';
import { NavigationContainer } from '@react-navigation/native';
import AppNavigator from './src/navigation/AppNavigator';
import { registerPushToken, setupNotificationListeners } from './src/services/NotificationService';
import AsyncStorage from '@react-native-async-storage/async-storage';

export default function App() {
  const navigationRef = useRef(null);

  useEffect(() => {
    // Enregistrer le token push si l'utilisateur est connecté
    AsyncStorage.getItem('token').then(token => {
      if (token) registerPushToken();
    });

    // Configurer les listeners de notification
    const cleanup = setupNotificationListeners(navigationRef.current);
    return cleanup;
  }, []);

  return (
    <NavigationContainer ref={navigationRef}>
      <AppNavigator />
    </NavigationContainer>
  );
}
```

---

## ÉTAPE 10 — eas.json pour build production

**Créer :** `edugestdz/mobile/eas.json`

```json
{
  "cli": {
    "version": ">= 7.0.0"
  },
  "build": {
    "development": {
      "developmentClient": true,
      "distribution": "internal",
      "android": { "buildType": "apk" },
      "ios":     { "simulator": true }
    },
    "preview": {
      "distribution": "internal",
      "android": { "buildType": "apk" }
    },
    "production": {
      "autoIncrement": true,
      "android": { "buildType": "app-bundle" },
      "ios":     { "image": "latest" },
      "env": {
        "APP_ENV": "production"
      }
    }
  },
  "submit": {
    "production": {
      "android": {
        "serviceAccountKeyPath": "./google-play-key.json",
        "track": "internal"
      }
    }
  }
}
```

---

## ÉTAPE 11 — Créer les assets placeholder (icônes)

```bash
cd edugestdz/mobile

# Créer le dossier assets s'il n'existe pas
mkdir -p assets

# Créer des fichiers placeholder si absents
# (les vrais fichiers doivent être fournis par le designer)
# icon.png : 1024×1024 PNG
# splash.png : 1284×2778 PNG
# adaptive-icon.png : 1024×1024 PNG
# notification-icon.png : 96×96 PNG blanc

# Si les fichiers n'existent pas, créer des placeholders vides
# pour que app.json soit valide :
touch assets/icon.png assets/splash.png assets/adaptive-icon.png assets/notification-icon.png
```

---

## ORDRE D'EXÉCUTION DEEPSEEK

```bash
# 0. Synchroniser
git checkout develop && git pull origin main

# 1. Installer packages Expo
cd edugestdz/mobile
npx expo install expo-web-browser react-native-webview expo-notifications expo-device
npx expo install @react-native-async-storage/async-storage expo-local-authentication

# 2. Modifier app.json (config push + permissions)
modify: edugestdz/mobile/app.json

# 3. Créer services
create: edugestdz/mobile/src/services/NotificationService.js
create: edugestdz/mobile/src/services/api.js

# 4. Modifier FacturesScreen (paiement CIB WebView)
modify: edugestdz/mobile/src/screens/parent/FacturesScreen.js

# 5. Créer MarketplaceScreen parent
create: edugestdz/mobile/src/screens/parent/MarketplaceScreen.js

# 6. Créer AdminPointageScreen
create: edugestdz/mobile/src/screens/admin/AdminPointageScreen.js

# 7. Modifier AppNavigator (nouveaux écrans + tabs)
modify: edugestdz/mobile/src/navigation/AppNavigator.js

# 8. Modifier App.js (push notifications setup)
modify: edugestdz/mobile/App.js

# 9. Créer eas.json
create: edugestdz/mobile/eas.json

# 10. Assets placeholder
mkdir -p edugestdz/mobile/assets
touch edugestdz/mobile/assets/notification-icon.png  # si absent

# 11. Vérifier syntaxe JS (pas de PHP ici, mais vérifier)
cd edugestdz/mobile
node --check src/services/NotificationService.js
node --check src/services/api.js
node --check src/screens/parent/FacturesScreen.js
node --check src/screens/parent/MarketplaceScreen.js
node --check src/screens/admin/AdminPointageScreen.js

# 12. Tests backend — vérifier 0 régression
cd edugestdz/backend
php artisan test --parallel
# → Tous les tests existants doivent rester verts (423+)

# 13. Commit & push
git add .
git commit -m "feat(mobile): CIB WebView + Push notifications Expo + MarketplaceScreen + AdminPointageScreen + cache hors-ligne + eas.json"
git push origin develop
# → PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_MOBILE_COMPLET.md — 11 étapes dans l'ordre.

RÈGLES :
1. Mobile = React Native 0.76 + Expo SDK 52 — utiliser npx expo install (pas npm).
2. Backend : php artisan test --parallel → 0 régression tolérée.
3. Ne pas modifier les écrans existants (parent ×8, enseignant ×4, admin ×3) — ajouter seulement.
4. Vérifier la syntaxe JS avec : node --check <fichier>.
5. Si react-native-webview ou autre package a des conflits de peer deps → utiliser --legacy-peer-deps.

Après git push → PR develop → main.
```
