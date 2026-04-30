import { defineConfig } from 'vite'
import path from 'path'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  // Absolute asset URLs — required because React Router serves the SPA index.html
  // at any path (e.g. /marketplace, /admin/products). With relative './' assets,
  // the browser would request /marketplace/assets/... which doesn't exist.
  // Nginx maps /frontend/dist/assets/* to public/frontend/dist/assets/*.
  base: '/frontend/dist/',
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
