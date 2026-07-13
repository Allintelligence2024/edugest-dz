import React, { useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  Alert,
  ActivityIndicator,
} from 'react-native';

let ImagePicker;
try {
  ImagePicker = require('expo-image-picker');
} catch (e) {
  ImagePicker = null;
}

import { bibliothequeApi } from '../../api/endpoints';

export default function BiblioScanScreen({ navigation }) {
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState(null);

  if (!ImagePicker) {
    return (
      <View style={styles.container}>
        <Text style={styles.errorTitle}>Module indisponible</Text>
        <Text style={styles.errorText}>
          Le module de scan photo n'est pas installé.
          Veuillez installer expo-image-picker.
        </Text>
      </View>
    );
  }

  const pickImage = async (useCamera) => {
    try {
      setError(null);
      setResult(null);

      const options = {
        mediaTypes: ImagePicker.MediaTypeOptions.Images,
        quality: 0.8,
        base64: true,
      };

      const pickerResult = useCamera
        ? await ImagePicker.launchCameraAsync(options)
        : await ImagePicker.launchImageLibraryAsync(options);

      if (pickerResult.canceled) return;

      const base64 = pickerResult.assets[0].base64;
      scanImage(base64);
    } catch (err) {
      setError("Erreur lors de la sélection de l'image.");
    }
  };

  const scanImage = async (base64) => {
    setLoading(true);
    try {
      const response = await bibliothequeApi.scan(base64);
      setResult(response.data);
    } catch (err) {
      const msg = err.response?.data?.error?.message || 'Erreur de scan.';
      setError(msg);
    } finally {
      setLoading(false);
    }
  };

  const emprunter = async (livreId) => {
    try {
      await bibliothequeApi.emprunter({
        livre_id: livreId,
        type_emprunteur: 'eleve',
      });
      Alert.alert('Succès', 'Livre emprunté avec succès !');
      navigation.goBack();
    } catch (err) {
      Alert.alert('Erreur', err.response?.data?.message || 'Emprunt échoué.');
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Scanner un livre</Text>
      <Text style={styles.subtitle}>
        Prenez en photo la couverture d'un livre pour le rechercher
        dans le catalogue.
      </Text>

      {loading ? (
        <ActivityIndicator size="large" color="#4F46E5" style={styles.loader} />
      ) : (
        <>
          <TouchableOpacity
            style={styles.button}
            onPress={() => pickImage(true)}
          >
            <Text style={styles.buttonText}>📷 Prendre une photo</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.button, styles.buttonSecondary]}
            onPress={() => pickImage(false)}
          >
            <Text style={[styles.buttonText, styles.buttonTextSecondary]}>
              🖼️ Choisir dans la galerie
            </Text>
          </TouchableOpacity>
        </>
      )}

      {error && (
        <View style={styles.errorBox}>
          <Text style={styles.errorText}>{error}</Text>
        </View>
      )}

      {result && (
        <View style={styles.resultBox}>
          {result.source === 'catalogue' ? (
            <>
              <Text style={styles.resultTitle}>✅ Livre trouvé !</Text>
              <Text style={styles.resultInfo}>
                {result.data.livre.titre} — {result.data.livre.auteur}
              </Text>
              <Text style={styles.resultInfo}>
                Disponibilité : {result.data.nb_dispo} exemplaire(s)
              </Text>
              {result.data.disponible && (
                <TouchableOpacity
                  style={styles.emprunterButton}
                  onPress={() => emprunter(result.data.livre.id)}
                >
                  <Text style={styles.emprunterButtonText}>Emprunter</Text>
                </TouchableOpacity>
              )}
            </>
          ) : (
            <>
              <Text style={styles.resultTitle}>📖 Livre non trouvé</Text>
              {result.ocr?.titre && (
                <Text style={styles.resultInfo}>
                  Titre détecté : {result.ocr.titre}
                </Text>
              )}
              {result.ocr?.auteur && (
                <Text style={styles.resultInfo}>
                  Auteur détecté : {result.ocr.auteur}
                </Text>
              )}
              <Text style={styles.resultInfo}>
                Confiance OCR : {result.ocr?.confiance}%
              </Text>
              <Text style={styles.ocrHint}>
                Vous pouvez l'ajouter manuellement au catalogue.
              </Text>
            </>
          )}

          {result.ocr && (
            <View style={styles.ocrBox}>
              <Text style={styles.ocrTitle}>Texte détecté :</Text>
              <Text style={styles.ocrText}>
                {result.ocr.texte_brut || result.ocr.titre}
              </Text>
            </View>
          )}
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: 20, backgroundColor: '#F9FAFB' },
  title: { fontSize: 22, fontWeight: 'bold', color: '#111827', marginBottom: 4 },
  subtitle: { fontSize: 14, color: '#6B7280', marginBottom: 24 },
  loader: { marginTop: 40 },
  button: {
    backgroundColor: '#4F46E5',
    padding: 16,
    borderRadius: 12,
    alignItems: 'center',
    marginBottom: 12,
  },
  buttonSecondary: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#D1D5DB',
  },
  buttonText: { color: '#fff', fontSize: 16, fontWeight: '600' },
  buttonTextSecondary: { color: '#374151' },
  errorBox: { marginTop: 16, padding: 12, backgroundColor: '#FEF2F2', borderRadius: 8 },
  errorTitle: { fontSize: 18, fontWeight: 'bold', color: '#DC2626', marginBottom: 8 },
  errorText: { color: '#DC2626', fontSize: 14 },
  resultBox: {
    marginTop: 20,
    padding: 16,
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  resultTitle: { fontSize: 18, fontWeight: 'bold', color: '#111827', marginBottom: 8 },
  resultInfo: { fontSize: 14, color: '#374151', marginBottom: 4 },
  emprunterButton: {
    backgroundColor: '#10B981',
    padding: 12,
    borderRadius: 8,
    marginTop: 12,
    alignItems: 'center',
  },
  emprunterButtonText: { color: '#fff', fontSize: 16, fontWeight: '600' },
  ocrHint: { fontSize: 13, color: '#6B7280', marginTop: 8, fontStyle: 'italic' },
  ocrBox: { marginTop: 12, padding: 10, backgroundColor: '#F3F4F6', borderRadius: 8 },
  ocrTitle: { fontSize: 12, fontWeight: 'bold', color: '#6B7280', marginBottom: 4 },
  ocrText: { fontSize: 13, color: '#374151' },
});
