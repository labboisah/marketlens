import marketlensPreset from '@marketlens/ui/tailwind-preset';
import type { Config } from 'tailwindcss';

export default {
  presets: [marketlensPreset],
  content: ['./src/renderer/index.html', './src/renderer/src/**/*.{ts,tsx}', '../../packages/ui/src/**/*.{ts,tsx}'],
} satisfies Config;
