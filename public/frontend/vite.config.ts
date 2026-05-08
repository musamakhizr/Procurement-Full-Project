import { defineConfig } from 'vite'
import path from 'path'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  // ───────────────────────────────────────────────────────────────────────
  // ⚠️  DO NOT CHANGE `base` WITHOUT READING THIS  ⚠️
  // ───────────────────────────────────────────────────────────────────────
  // Production nginx (deploy/templates/nginx-hexugo.conf) serves built assets
  // at /frontend/dist/assets/* and falls back any non-API path to the SPA
  // index.html. The React app uses react-router for client-side routing,
  // so a deep URL like https://hexugo.com/admin/products loads the same
  // index.html and then needs to re-fetch the JS bundle.
  //
  //   base: '/frontend/dist/'   → emits  <script src="/frontend/dist/assets/x.js">
  //                                ✅ Absolute. Works from /, /admin, anywhere.
  //
  //   base: './'                → emits  <script src="./assets/x.js">
  //                                ❌ Relative. From /admin/products this becomes
  //                                /admin/products/assets/x.js → 404 → MIME error.
  //
  //   base: '/'                 → emits  <script src="/assets/x.js">
  //                                ❌ nginx has no /assets/* rule → falls back to
  //                                index.html → MIME error.
  //
  // If you need a different path, also update deploy/templates/nginx-hexugo.conf
  // location blocks AND deploy a config refresh.
  // ───────────────────────────────────────────────────────────────────────
  base: '/',
  plugins: [
    // The React and Tailwind plugins are both required for Make, even if
    // Tailwind is not being actively used – do not remove them
    react(),
    tailwindcss(),
  ],
  resolve: {
    alias: {
      // Alias @ to the app directory
      '@': path.resolve(__dirname, './app'),
    },
  },

  // File types to support raw imports. Never add .css, .tsx, or .ts files to this.
  assetsInclude: ['**/*.svg', '**/*.csv'],
})
