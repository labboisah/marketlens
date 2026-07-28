import { defineConfig } from 'vite';

export default defineConfig({
  build: {
    emptyOutDir: false,
    lib: {
      entry: 'src/main/index.ts',
      formats: ['es'],
    },
    rollupOptions: {
      external: ['electron', 'node:fs', 'node:path', 'node:url'],
    },
  },
});
