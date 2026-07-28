/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './resources/**/*.ts',
    './app/Livewire/**/*.php',
    './vendor/livewire/flux-pro/stubs/**/*.blade.php',
    './vendor/livewire/flux/stubs/**/*.blade.php',
  ],
  darkMode: ['class', '[data-theme="dark"]'],
  theme: {
    extend: {
      colors: {
        background: 'hsl(var(--ml-background) / <alpha-value>)',
        foreground: 'hsl(var(--ml-foreground) / <alpha-value>)',
        surface: 'hsl(var(--ml-surface) / <alpha-value>)',
        'surface-muted': 'hsl(var(--ml-surface-muted) / <alpha-value>)',
        border: 'hsl(var(--ml-border) / <alpha-value>)',
        muted: 'hsl(var(--ml-muted) / <alpha-value>)',
        primary: 'hsl(var(--ml-primary) / <alpha-value>)',
        accent: 'hsl(var(--ml-accent) / <alpha-value>)',
        teal: 'hsl(var(--ml-teal) / <alpha-value>)',
        success: 'hsl(var(--ml-success) / <alpha-value>)',
        warning: 'hsl(var(--ml-warning) / <alpha-value>)',
        danger: 'hsl(var(--ml-danger) / <alpha-value>)',
        information: 'hsl(var(--ml-information) / <alpha-value>)',
      },
      borderRadius: {
        sm: 'var(--ml-radius-sm)',
        md: 'var(--ml-radius-md)',
        lg: 'var(--ml-radius-lg)',
      },
      boxShadow: {
        sm: 'var(--ml-shadow-sm)',
        md: 'var(--ml-shadow-md)',
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'SFMono-Regular', 'Consolas', 'monospace'],
      },
    },
  },
  plugins: [],
};
