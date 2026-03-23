import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

// Standalone widget build — outputs a single JS file that registers
// the <social-portfolio> custom element on any page.
export default defineConfig({
  plugins: [vue({ customElement: true })],
  resolve: {
    alias: { '@': resolve(__dirname, 'src') },
  },
  build: {
    lib: {
      entry: resolve(__dirname, 'src/widget-entry.ts'),
      name: 'SocialPortfolio',
      fileName: 'social-portfolio',
      formats: ['es', 'iife'],
    },
    outDir: 'dist/widget',
    rollupOptions: {
      external: [],
    },
  },
})
