import { defineConfig } from 'vite';

export default defineConfig({
  build: {
    emptyOutDir: false,
    lib: {
      entry: 'src/main/index.ts',
      formats: ['es'],
    },
    rollupOptions: {
      external: [
        'better-sqlite3',
        'dotenv',
        'drizzle-orm/better-sqlite3',
        'electron',
        'fastify',
        'node:crypto',
        'node:fs',
        'node:path',
        'node:url',
        'pino',
        'socket.io',
      ],
    },
  },
});
