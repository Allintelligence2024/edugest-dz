import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { colors, fontSizes } from '../theme';
import { useEnfants } from '../context/EnfantContext';

/**
 * PILOTE — Sélecteur d'enfant (chips horizontales).
 * Affiché seulement si le parent a ≥ 2 enfants. Sinon : nom statique.
 */
export default function EnfantSelector() {
  const { enfants, enfantActif, choisir, loading } = useEnfants();

  if (loading || enfants.length === 0) return null;

  if (enfants.length === 1) {
    const e = enfants[0];
    return (
      <View style={styles.solo}>
        <Text style={styles.soloText}>
          👦 {e.nom_complet ?? `${e.prenom ?? ''} ${e.nom ?? ''}`.trim()}
        </Text>
      </View>
    );
  }

  return (
    <View style={styles.row}>
      {enfants.map((e) => {
        const actif = enfantActif?.id === e.id;
        return (
          <TouchableOpacity
            key={e.id}
            testID={`enfant-chip-${e.id}`}
            style={[styles.chip, actif && styles.chipActif]}
            onPress={() => choisir(e.id)}
          >
            <Text style={[styles.chipText, actif && styles.chipTextActif]}>
              {e.prenom ?? e.nom_complet ?? '?'}
            </Text>
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 12 },
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.border,
  },
  chipActif: { backgroundColor: colors.primary, borderColor: colors.primary },
  chipText: { fontSize: fontSizes.sm, color: colors.text },
  chipTextActif: { color: '#fff', fontWeight: '700' },
  solo: { marginBottom: 12 },
  soloText: { fontSize: fontSizes.md, fontWeight: '600', color: colors.text },
});
