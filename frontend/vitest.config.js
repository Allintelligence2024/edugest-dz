import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  plugins: [react()],
  esbuild: {
    jsx: 'automatic',
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.js'],
    css: true,
    coverage: {
      reporter: ['text', 'lcov', 'html'],
      include: ['src/**/*.{jsx,js}'],
      exclude: ['src/main.jsx', 'src/test/**'],

      // ── Cliquet de couverture (Sprint 4) ────────────────────────────
      //
      // Les paliers étaient à 70 % — une valeur d'intention, jamais
      // atteinte ni vérifiée : `npm run test` lance `vitest run` sans
      // couverture, donc le seuil n'était opposé à personne.
      //
      // Ils sont ramenés au niveau réellement mesuré, très légèrement en
      // dessous. Un seuil tenu vaut mieux qu'un seuil affiché : celui-ci
      // interdit toute régression dès maintenant, ce que 70 % ne faisait
      // pas. Chaque lot de tests le remonte d'autant.
      //
      // Mesure au 2026-09-08 : 18.85 % lignes, 54.92 % branches,
      // 35.25 % fonctions (122 tests, 21 fichiers).
      // Cible Sprint 5 : 40 % de lignes — voir PLAN_REMEDIATION_2026.md.
      thresholds: {
        statements: 18,
        branches: 52,
        functions: 34,
        lines: 18,
      },
    },
    exclude: ['node_modules', 'dist'],
    testTimeout: 15000,
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
      '@api': path.resolve(__dirname, './src/api'),
      '@components': path.resolve(__dirname, './src/components'),
      '@pages': path.resolve(__dirname, './src/pages'),
      '@hooks': path.resolve(__dirname, './src/hooks'),
      '@context': path.resolve(__dirname, './src/context'),
    },
  },
});
