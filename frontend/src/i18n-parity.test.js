import { describe, it, expect } from 'vitest';
import fr from './lang/fr.json';
import ar from './lang/ar.json';
import en from './lang/en.json';
import dz from './lang/dz.json';

// Garde-fou 5.6-phase-3 : les quatre dictionnaires doivent porter exactement
// les mêmes clés de traduction. Sans lui, une clé ajoutée au fr et oubliée
// ailleurs replie silencieusement sur le français — invisible à l'écran.
//
// Convention : les clés préfixées `_` (ex. `_comment` en dz) sont des
// métadonnées de fichier, pas des traductions — elles sont ignorées.
const DICTS = { fr, ar, en, dz };

const realKeys = (dict) =>
  Object.keys(dict).filter((k) => !k.startsWith('_')).sort();

describe('garde-fou parité des dictionnaires (5.6-phase-3)', () => {
  it('fr, ar, en et dz exposent le même jeu de clés', () => {
    const ref = realKeys(DICTS.fr);
    for (const [code, dict] of Object.entries(DICTS)) {
      const keys = realKeys(dict);
      const manquantes = ref.filter((k) => !keys.includes(k));
      const enTrop = keys.filter((k) => !ref.includes(k));
      expect(
        manquantes.length + enTrop.length,
        `${code} : ${manquantes.length} clés manquantes (${manquantes.slice(0, 5).join(', ')}…), ` +
          `${enTrop.length} clés absentes de fr (${enTrop.slice(0, 5).join(', ')}…)`
      ).toBe(0);
    }
  });

  it('aucune traduction vide (le repli fr doit rester explicite)', () => {
    const vides = [];
    for (const [code, dict] of Object.entries(DICTS)) {
      for (const [k, v] of Object.entries(dict)) {
        if (!k.startsWith('_') && typeof v === 'string' && v.trim() === '') {
          vides.push(`${code}.${k}`);
        }
      }
    }
    expect(vides, `traductions vides : ${vides.join(', ')}`).toEqual([]);
  });
});
