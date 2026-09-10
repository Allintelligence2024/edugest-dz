import { describe, it, expect } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// Garde-fou 5.6-phase-2 : les emoji JSX doivent migrer vers lucide-react
// (table de correspondance : docs/SPRINT5_ARCHITECTURE.md § 5.6).
// Phase 2 terminée : 0 emoji / 0 fichier. Le cliquet est verrouillé à 0,
// toute régression (nouvel emoji en dur) fait échouer la CI.
const PLAFOND_OCCURRENCES = 0;
const PLAFOND_FICHIERS = 0;

const EMOJI = /[\u{1F300}-\u{1FAFF}\u2600-\u27BF\u2B00-\u2BFF\uFE0F]/u;
const RACINE = path.dirname(fileURLToPath(import.meta.url));

function ratisser(dir, trouves = []) {
  for (const entree of fs.readdirSync(dir, { withFileTypes: true })) {
    const chemin = path.join(dir, entree.name);
    if (entree.isDirectory()) {
      ratisser(chemin, trouves);
    } else if (/\.jsx?$/.test(entree.name) && !entree.name.includes('.test.')) {
      const n = (fs.readFileSync(chemin, 'utf8').match(new RegExp(EMOJI.source, 'gu')) || []).length;
      if (n > 0) trouves.push([chemin, n]);
    }
  }
  return trouves;
}

describe('garde-fou emoji JSX (5.6-phase-2)', () => {
  it('ne laisse pas augmenter les emoji en dur', () => {
    const trouves = ratisser(RACINE);
    const occurrences = trouves.reduce((somme, [, n]) => somme + n, 0);
    const pires = trouves
      .sort((a, b) => b[1] - a[1])
      .slice(0, 5)
      .map(([chemin, n]) => `${n}× ${path.relative(RACINE, chemin)}`)
      .join(', ');
    expect(
      trouves.length,
      `${trouves.length} fichiers avec emoji (plafond ${PLAFOND_FICHIERS}) — voir § 5.6 : ${pires}`
    ).toBeLessThanOrEqual(PLAFOND_FICHIERS);
    expect(
      occurrences,
      `${occurrences} occurrences (plafond ${PLAFOND_OCCURRENCES}) — voir § 5.6 : ${pires}`
    ).toBeLessThanOrEqual(PLAFOND_OCCURRENCES);
  });
});
