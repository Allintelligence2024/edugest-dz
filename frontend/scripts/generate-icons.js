/**
 * Script pour convertir les icônes SVG en PNG.
 *
 * Usage : node scripts/generate-icons.js
 * Prérequis : npm install sharp --save-dev
 *
 * Si sharp n'est pas disponible, les SVG suffisent pour le dev.
 * En production, utiliser un vrai générateur d'icônes PWA.
 */

const fs = require('fs');
const path = require('path');

const iconsDir = path.join(__dirname, '..', 'public', 'icons');

console.log('Icônes PWA dans public/icons/ :');
console.log('  pwa-192x192.svg         (192x192 px)');
console.log('  pwa-512x512.svg         (512x512 px)');
console.log('  pwa-maskable-512x512.svg (512x512 px)');
console.log('  apple-touch-icon.svg     (180x180 px)');
console.log('');
console.log('Pour la production, convertissez ces SVG en PNG avec :');
console.log('  npx pwa-asset-generator favicon.svg public/icons/');
console.log('  ou installez sharp : npm install sharp --save-dev');
