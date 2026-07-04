import React, { useState, useEffect, useCallback } from 'react';
import {
  View, Text, FlatList, TouchableOpacity,
  StyleSheet, Alert, ActivityIndicator, Modal,
} from 'react-native';
import { WebView } from 'react-native-webview';
import { useAuth } from '../../context/AuthContext';
import { useI18n } from '../../context/I18nContext';
import api from '../../api/axios';
import { withCache } from '../../services/cache';
import { colors } from '../../theme/colors';
import { spacing, fontSizes } from '../../theme/spacing';

const STATUS_STYLES = {
  payée:          { color: colors.success, label: 'Payée' },
  émise:          { color: colors.warning, label: 'Émise' },
  en_retard:      { color: colors.danger, label: 'En retard' },
  partiellement_payée: { color: '#fbbf24', label: 'Partielle' },
  annulée:        { color: colors.textLight, label: 'Annulée' },
};

export default function PaiementsScreen() {
  const { user } = useAuth();
  const { t } = useI18n();
  const [factures, setFactures]     = useState([]);
  const [loading, setLoading]       = useState(true);
  const [payingId, setPayingId]     = useState(null);
  const [satimUrl, setSatimUrl]     = useState(null);
  const [showWebView, setShowWebView] = useState(false);

  const loadFactures = useCallback(async () => {
    try {
      const data = await withCache('factures_parent', () =>
        api.get('/finance/factures?per_page=50'),
      );
      setFactures(data?.data?.data ?? data?.data ?? []);
    } catch {} finally { setLoading(false); }
  }, []);

  useEffect(() => { loadFactures(); }, [loadFactures]);

  const payerCIB = async (facture) => {
    if (facture.statut === 'payée') {
      Alert.alert('Info', 'Cette facture est déjà payée.');
      return;
    }

    setPayingId(facture.id);
    try {
      const res = await api.post('/paiements/cib/initier', {
        facture_id: facture.id,
        return_url: 'https://app.edugest.dz/paiement/retour',
        fail_url:   'https://app.edugest.dz/paiement/echec',
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

  const onWebViewNavChange = (navState) => {
    const { url } = navState;
    if (url.includes('/paiement/retour') || url.includes('orderStatus=2')) {
      setShowWebView(false);
      setSatimUrl(null);
      Alert.alert('Paiement réussi', 'Votre paiement CIB a été confirmé.', [
        { text: 'OK', onPress: loadFactures },
      ]);
    } else if (url.includes('/paiement/echec') || url.includes('orderStatus=0')) {
      setShowWebView(false);
      setSatimUrl(null);
      Alert.alert('Paiement échoué', 'Le paiement n\'a pas abouti. Réessayez ou contactez votre banque.');
    }
  };

  const renderFacture = ({ item }) => {
    const st = STATUS_STYLES[item.statut] || { color: colors.textLight, label: item.statut };
    return (
      <View style={styles.card}>
        <View style={styles.cardRow}>
          <View style={{ flex: 1 }}>
            <Text style={styles.numero}>{item.numero}</Text>
            <Text style={styles.sub}>Échéance : {item.date_echeance?.split('T')[0]}</Text>
          </View>
          <View style={{ alignItems: 'flex-end' }}>
            <Text style={[styles.montant, { color: st.color }]}>
              {Number(item.total_ttc).toLocaleString('fr-DZ')} DA
            </Text>
            <View style={[styles.badge, { backgroundColor: st.color + '22' }]}>
              <Text style={[styles.badgeText, { color: st.color }]}>{st.label.toUpperCase()}</Text>
            </View>
          </View>
        </View>

        {['émise', 'en_retard', 'partiellement_payée'].includes(item.statut) && (
          <TouchableOpacity
            style={styles.cibBtn}
            onPress={() => payerCIB(item)}
            disabled={payingId === item.id}
          >
            {payingId === item.id
              ? <ActivityIndicator size="small" color="#fff" />
              : <Text style={styles.cibBtnText}>Payer par CIB / Dahabia</Text>
            }
          </TouchableOpacity>
        )}
      </View>
    );
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Mes Factures</Text>

      {loading ? (
        <ActivityIndicator size="large" color={colors.primary} style={{ marginTop: 40 }} />
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

      <Modal visible={showWebView} animationType="slide" presentationStyle="pageSheet">
        <View style={{ flex: 1, backgroundColor: '#08090f' }}>
          <View style={styles.webviewHeader}>
            <Text style={styles.webviewTitle}>Paiement sécurisé CIB / Dahabia</Text>
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
              renderLoading={() => <ActivityIndicator size="large" color={colors.primary} />}
            />
          )}
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container:    { flex: 1, backgroundColor: colors.background, padding: spacing.md },
  title:        { fontSize: 20, fontWeight: '900', color: colors.text, marginBottom: spacing.md },
  card:         { backgroundColor: colors.surface, borderRadius: 12, padding: spacing.md,
                  marginBottom: 10, borderWidth: 1, borderColor: colors.border },
  cardRow:      { flexDirection: 'row', alignItems: 'center' },
  numero:       { fontSize: 13, fontWeight: '700', color: colors.text, marginBottom: 2 },
  sub:          { fontSize: 10, color: colors.textSecondary },
  montant:      { fontSize: 16, fontWeight: '900', marginBottom: 4 },
  badge:        { paddingHorizontal: 8, paddingVertical: 2, borderRadius: 20 },
  badgeText:    { fontSize: 9, fontWeight: '700' },
  cibBtn:       { backgroundColor: colors.primary, borderRadius: 8, padding: 12,
                  alignItems: 'center', marginTop: 10 },
  cibBtnText:   { color: colors.white, fontWeight: '700', fontSize: 13 },
  empty:        { color: colors.textLight, textAlign: 'center', marginTop: 40, fontSize: 13 },
  webviewHeader:{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
                  padding: spacing.md, borderBottomWidth: 1, borderBottomColor: colors.border },
  webviewTitle: { fontSize: 13, fontWeight: '700', color: colors.text },
  webviewClose: { fontSize: 13, color: colors.danger, fontWeight: '700' },
});
