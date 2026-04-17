import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
  plugins: [ vue() ],
  build: {
    outDir: 'build',
    emptyOutDir: true,
    cssCodeSplit: false, // All CSS lands in build/index.css — WP only enqueues one file
    rollupOptions: {
      input: 'src/main.js',
      output: {
        entryFileNames: 'index.js',
        assetFileNames: 'index.[ext]',
      },
    },
  },
  resolve: {
    alias: { '@': path.resolve( __dirname, 'src' ) },
  },
})
