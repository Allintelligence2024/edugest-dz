import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Alert,
  ActivityIndicator,
  Image,
} from 'react-native';
import NetInfo from '@react-native-community/netinfo';
import { enseignantApi } from '../../api/endpoints';

const QR_SERVER_URL = 'https://api.qrserver.com/v1/create-qr-code/';

export default function EnseignantQrCodeScreen({ route }) {
  const { seanceId, seanceNom } = route.params || {};
  const [qrUrl, setQrUrl] = useState(null);
  const [sessionActive, setSessionActive] = useState(false);
  const [loading, setLoading] = useState(false);
  const [connected, setConnected] = useState(true);
  const [token, setToken] = useState(null);

  useEffect(() => {
    const unsub = NetInfo.addEventListener(state => {
      setConnected(state.isConnected ?? false);
    });
    return () => unsub();
  }, []);

  const demarrerSession = async () => {
    if (!connected) {
      Alert.alert('Hors ligne', 'Connexion requise pour le QR Code');
      return;
    }

    setLoading(true);
    try {
      const res = await enseignantApi.qrCode.demarrerSession(seanceId);
      if (res.data.success) {
        const newToken = res.data.data.token;
        setToken(newToken);
        setSessionActive(true);
        genererQR(newToken);
      }
    } catch (err) {
      Alert.alert('Erreur', err.response?.data?.error?.message || 'Impossible de démarrer');
    } finally {
      setLoading(false);
    }
  };

  const fermerSession = async () => {
    setLoading(true);
    try {
      await enseignantApi.qrCode.fermerSession(seanceId);
      setSessionActive(false);
      setQrUrl(null);
      setToken(null);
    } catch (err) {
      Alert.alert('Erreur', 'Fermeture échouée');
    } finally {
      setLoading(false);
    }
  };

  const genererQR = (sessionToken) => {
    const payload = JSON.stringify({ token: sessionToken, seance: seanceId });
    const url = `${QR_SERVER_URL}?size=400x400&data=${encodeURIComponent(payload)}`;
    setQrUrl(url);
  };

  if (!connected) {
    return (
      <View style={styles.container}>
        <Text style={styles.offlineIcon}>📡</Text>
        <Text style={styles.offlineTitle}>Hors connexion</Text>
        <Text style={styles.offlineText}>
          Connexion requise pour le QR Code
        </Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <Text style={styles.title}>QR Code Présence</Text>
      <Text style={styles.subtitle}>{seanceNom || 'Séance'}</Text>

      {!sessionActive ? (
        <TouchableOpacity
          style={styles.btnDemarrer}
          onPress={demarrerSession}
          disabled={loading}
        >
          {loading ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.btnText}>▶ Démarrer Session QR</Text>
          )}
        </TouchableOpacity>
      ) : (
        <>
          {qrUrl && (
            <View style={styles.qrContainer}>
              <Image
                source={{ uri: qrUrl }}
                style={styles.qrImage}
                resizeMode="contain"
              />
              <Text style={styles.instruction}>
                Demandez aux élèves de scanner ce QR code
              </Text>
            </View>
          )}

          <TouchableOpacity
            style={styles.btnFermer}
            onPress={fermerSession}
            disabled={loading}
          >
            {loading ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.btnText}>⏹ Fermer Session</Text>
            )}
          </TouchableOpacity>
        </>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f1f5f9',
    padding: 24,
    alignItems: 'center',
    justifyContent: 'center',
  },
  title: {
    fontSize: 22,
    fontWeight: '800',
    color: '#1e3a5f',
    marginBottom: 4,
  },
  subtitle: {
    fontSize: 14,
    color: '#64748b',
    marginBottom: 32,
  },
  btnDemarrer: {
    backgroundColor: '#2563eb',
    paddingVertical: 16,
    paddingHorizontal: 32,
    borderRadius: 12,
    width: '100%',
    alignItems: 'center',
  },
  btnFermer: {
    backgroundColor: '#dc2626',
    paddingVertical: 16,
    paddingHorizontal: 32,
    borderRadius: 12,
    width: '100%',
    alignItems: 'center',
    marginTop: 16,
  },
  btnText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '700',
  },
  qrContainer: {
    alignItems: 'center',
    marginBottom: 24,
  },
  qrImage: {
    width: 300,
    height: 300,
    backgroundColor: '#fff',
    borderRadius: 12,
  },
  instruction: {
    marginTop: 12,
    fontSize: 13,
    color: '#64748b',
    textAlign: 'center',
  },
  offlineIcon: {
    fontSize: 48,
    marginBottom: 16,
  },
  offlineTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: '#1e293b',
    marginBottom: 8,
  },
  offlineText: {
    fontSize: 14,
    color: '#64748b',
    textAlign: 'center',
  },
});
