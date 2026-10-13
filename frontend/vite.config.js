import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  // Čas buildu – v Profilu je vidět, jestli PWA běží na nejnovější verzi
  define: {
    __BUILD_TIME__: JSON.stringify(new Date().toISOString()),
  },
  plugins: [
    vue(),
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: ['beer.svg', 'apple-touch-icon.png'],
      workbox: {
        importScripts: ['sw-push.js'],
      },
      manifest: {
        name: 'Pivní Konto',
        short_name: 'Pivní Konto',
        description: 'Sledování konzumace piva mezi přáteli',
        theme_color: '#f59e0b',
        background_color: '#1f2937',
        display: 'standalone',
        icons: [
          {
            src: 'pwa-192x192.png',
            sizes: '192x192',
            type: 'image/png'
          },
          {
            src: 'pwa-512x512.png',
            sizes: '512x512',
            type: 'image/png'
          }
        ]
      }
    })
  ],
  server: {
    host: '0.0.0.0',
    port: 5173
  },
  build: {
    outDir: 'dist'
  }
})
