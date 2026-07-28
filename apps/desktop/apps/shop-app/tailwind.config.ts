import { marketlensTailwindPreset } from '@marketlens/ui/tailwind-preset';
import type { Config } from 'tailwindcss';

export default {
  presets: [marketlensTailwindPreset],
  content: ['./src/renderer/**/*.{ts,tsx}', '../../packages/ui/src/**/*.{ts,tsx}'],
} satisfies Config;
