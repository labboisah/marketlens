import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('marketlensShell', () => ({
  sidebarOpen: false,
  theme: localStorage.getItem('marketlens-theme') || 'light',
  init() {
    document.documentElement.dataset.theme = this.theme;
  },
  toggleTheme() {
    this.theme = this.theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem('marketlens-theme', this.theme);
    document.documentElement.dataset.theme = this.theme;
  },
}));

Alpine.start();
