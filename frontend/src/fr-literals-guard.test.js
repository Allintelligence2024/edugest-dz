import { describe, it, expect } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// Garde-fou 5.6-phase-3 : les littéraux français codés en dur migrent vers
// t() (docs/SPRINT5_ARCHITECTURE.md § 5.6, phase 3).
// Cliquet : le nombre de fichiers concernés ne doit pas augmenter, chaque lot
// de la phase 3 le baisse d'autant.
//
// Heuristique : un caractère accentué français hors commentaires suffit à
// compter un fichier (les commentaires du dépôt sont en français). Limites
// assumées et documentées :
//  - les valeurs d'API type `statut=non_justifiée` (énums backend français)
//    comptent ici ; elles sont tranchées au moment de convertir leur fichier ;
//  - le socle i18n, les dictionnaires et le code mort documenté sont exclus.
const PLAFOND_FICHIERS = 60;

// Code mort détecté au lot 1 (plus aucun import nulle part — voir § 5.6) :
// on ne traduit pas du code qui ne s'affiche pas ; sa suppression est notée
// pour un lot de nettoyage dédié.
const EXCLUSIONS = [
  /(^|\/)test\//,        // infrastructure de test
  /\.test\.[jm]sx?$/,    // fichiers de tests
  /(^|\/)__tests__\//,
  /src[\\/]lang[\\/]/,   // dictionnaires
  /src[\\/]i18n\.js$/,   // socle (labels LANG_META, locales)
  /context[\\/]I18nContext\.jsx$/, // façade (LANG_META)
  /components[\\/]Header\.jsx$/,   // code mort (remplacé par layout/Topbar)
  /components[\\/]DataTable\.jsx$/, // code mort (remplacé par common/DataTable)
  /services[\\/]api\.js$/,          // code mort (remplacé par api/)
];

const ACCENTS = /[éèêëàâäîïôöûüçÉÈÊËÀÂÄÎÏÔÖÛÜÇ]/;
const RACINE = path.dirname(fileURLToPath(import.meta.url));

// Retire les commentaires en préservant les chaînes (les `//` des URL
// comptent comme du code, les apostrophes des commentaires français ne
// doivent pas être pris pour des délimiteurs).
function sansCommentaires(src) {
  let sortie = '';
  let i = 0;
  let chaine = null;
  let bloc = false;
  let ligne = false;
  while (i < src.length) {
    const c = src[i];
    const d = src[i + 1];
    if (ligne) {
      if (c === '\n') { ligne = false; sortie += c; }
      i += 1;
    } else if (bloc) {
      if (c === '*' && d === '/') { bloc = false; i += 2; }
      else { if (c === '\n') sortie += '\n'; i += 1; }
    } else if (chaine) {
      sortie += c;
      if (c === '\\') { sortie += d ?? ''; i += 2; continue; }
      if (c === chaine) chaine = null;
      i += 1;
    } else if (c === '/' && d === '/') { ligne = true; i += 2; }
    else if (c === '/' && d === '*') { bloc = true; i += 2; }
    else if (c === "'" || c === '"' || c === '`') { chaine = c; sortie += c; i += 1; }
    else { sortie += c; i += 1; }
  }
  return sortie;
}

function ratisser(dir, trouves = []) {
  for (const entree of fs.readdirSync(dir, { withFileTypes: true })) {
    const chemin = path.join(dir, entree.name);
    if (entree.isDirectory()) {
      ratisser(chemin, trouves);
    } else if (/\.[jm]sx?$/.test(entree.name) && !EXCLUSIONS.some((re) => re.test(chemin))) {
      const src = sansCommentaires(fs.readFileSync(chemin, 'utf8'));
      if (ACCENTS.test(src)) trouves.push(path.relative(RACINE, chemin));
    }
  }
  return trouves;
}

describe('garde-fou littéraux français (5.6-phase-3)', () => {
  it('ne laisse pas augmenter les fichiers non traduits', () => {
    const trouves = ratisser(RACINE).sort();
    expect(
      trouves.length,
      `${trouves.length} fichiers avec littéraux français (plafond ${PLAFOND_FICHIERS}) — ` +
        `voir § 5.6 phase 3 : ${trouves.slice(0, 5).join(', ')}…`
    ).toBeLessThanOrEqual(PLAFOND_FICHIERS);
  });
});
