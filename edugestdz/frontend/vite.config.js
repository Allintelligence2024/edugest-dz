import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { VitePWA } from 'vite-plugin-pwa'
import path from 'path'
import { fileURLToPath } from 'url'

/* global process */

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

export default defineConfig({
  plugins: [
    react(),

    VitePWA({
      registerType: 'autoUpdate',
      injectRegister: 'auto',

      workbox: {
        globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],

        runtimeCaching: [
          {
            urlPattern: /^https?:\/\/.*\/api\/v1\/(eleves|finance|planning|analytics)/,
            handler: 'StaleWhileRevalidate',
            options: {
              cacheName: 'edugest-api-cache',
              expiration: {
                maxEntries:    50,
                maxAgeSeconds: 300,
              },
              cacheableResponse: {
                statuses: [0, 200],
              },
            },
          },
          {
            urlPattern: /^https?:\/\/.*\/api\/v1\/(notes|absences|bulletins)/,
            handler: 'NetworkFirst',
            options: {
              cacheName: 'edugest-critical-cache',
              networkTimeoutSeconds: 5,
              expiration: {
                maxEntries:    30,
                maxAgeSeconds: 600,
              },
              cacheableResponse: {
                statuses: [0, 200],
              },
            },
          },
          {
            urlPattern: /^https:\/\/fonts\.googleapis\.com/,
            handler: 'CacheFirst',
            options: {
              cacheName: 'google-fonts-cache',
              expiration: {
                maxEntries:    10,
                maxAgeSeconds: 60 * 60 * 24 * 365,
              },
            },
          },
        ],

        navigateFallbackDenylist: [
          /^\/api\//,
          /^\/admin\//,
        ],
      },

      manifest: {
        name:             'EduGest DZ — Gestion Scolaire',
        short_name:       'EduGest DZ',
        description:      'Plateforme SaaS de gestion des établissements éducatifs algériens',
        theme_color:      '#2563eb',
        background_color: '#070B14',
        display:          'standalone',
        orientation:      'portrait',
        start_url:        '/',
        lang:             'fr',
        scope:            '/',

        icons: [
          {
            src:     '/icons/pwa-192x192.png',
            sizes:   '192x192',
            type:    'image/png',
            purpose: 'any',
          },
          {
            src:     '/icons/pwa-512x512.png',
            sizes:   '512x512',
            type:    'image/png',
            purpose: 'any',
          },
          {
            src:     '/icons/pwa-maskable-512x512.png',
            sizes:   '512x512',
            type:    'image/png',
            purpose: 'maskable',
          },
        ],

        shortcuts: [
          {
            name:  'Tableau de bord',
            url:   '/dashboard',
            icons: [{ src: '/icons/shortcut-dashboard.png', sizes: '96x96' }],
          },
          {
            name:  'Élèves',
            url:   '/eleves',
            icons: [{ src: '/icons/shortcut-eleves.png', sizes: '96x96' }],
          },
          {
            name:  'Finance',
            url:   '/finance',
            icons: [{ src: '/icons/shortcut-finance.png', sizes: '96x96' }],
          },
        ],

        categories: ['education', 'productivity'],
      },

      devOptions: {
        enabled: false,
        type:    'module',
      },
    }),
  ],

  resolve: {
    alias: {
      '@':          path.resolve(__dirname, './src'),
      '@api':       path.resolve(__dirname, './src/api'),
      '@components':path.resolve(__dirname, './src/components'),
      '@pages':     path.resolve(__dirname, './src/pages'),
      '@hooks':     path.resolve(__dirname, './src/hooks'),
      '@context':   path.resolve(__dirname, './src/context'),
    },
  },

  server: {
    host: 'localhost',
    port: 5173,
    open: true,
    proxy: {
      '/api': {
        target: process.env.VITE_API_URL || 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
})
