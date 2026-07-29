import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('marketlensShell', () => ({
  sidebarOpen: false,
  theme: localStorage.getItem('marketlens-theme') || 'light',
  notifications: [],
  init() {
    document.documentElement.dataset.theme = this.theme;
    window.addEventListener('marketlens:notify', (event) => this.notify(event.detail || {}));
  },
  toggleTheme() {
    this.theme = this.theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem('marketlens-theme', this.theme);
    document.documentElement.dataset.theme = this.theme;
  },
  notify(notification) {
    const id = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    const item = {
      id,
      tone: ['success', 'warning', 'danger', 'information'].includes(notification.tone) ? notification.tone : 'information',
      title: notification.title || 'MarketLens update',
      message: notification.message || '',
      visible: true,
    };

    this.notifications.unshift(item);
    window.setTimeout(() => this.dismissNotification(id), notification.timeout || 5000);
  },
  dismissNotification(id) {
    const item = this.notifications.find((notification) => notification.id === id);

    if (!item) {
      return;
    }

    item.visible = false;
    window.setTimeout(() => {
      this.notifications = this.notifications.filter((notification) => notification.id !== id);
    }, 180);
  },
  notificationClasses(tone) {
    return {
      success: 'border-success/30 text-success',
      warning: 'border-warning/30 text-warning',
      danger: 'border-danger/30 text-danger',
      information: 'border-information/30 text-information',
    }[tone] || 'border-information/30 text-information';
  },
  notificationIconClasses(tone) {
    return {
      success: 'bg-success/10 text-success',
      warning: 'bg-warning/10 text-warning',
      danger: 'bg-danger/10 text-danger',
      information: 'bg-information/10 text-information',
    }[tone] || 'bg-information/10 text-information';
  },
}));

Alpine.start();

const actionSelector = 'button, a[role="button"]';

const hasLivewireAction = (element) => Array.from(element.attributes || []).some((attribute) => (
  attribute.name.startsWith('wire:click')
  || attribute.name.startsWith('wire:submit')
  || attribute.name.startsWith('wire:navigate')
));

const markActionLoading = (element) => {
  if (!element || element.dataset.mlSkipLoading === 'true') {
    return;
  }

  element.dataset.mlLoading = 'true';
  element.setAttribute('aria-busy', 'true');
  window.setTimeout(() => clearActionLoading(element), 2500);
};

const clearActionLoading = (element) => {
  if (!element) {
    return;
  }

  delete element.dataset.mlLoading;
  element.removeAttribute('aria-busy');
};

const clearActionLoaders = () => {
  document.querySelectorAll('[data-ml-loading="true"]').forEach(clearActionLoading);
};

document.addEventListener('click', (event) => {
  const action = event.target.closest(actionSelector);

  if (!action || action.disabled || action.getAttribute('aria-disabled') === 'true') {
    return;
  }

  const isSubmitButton = action.tagName === 'BUTTON' && (!action.type || action.type === 'submit');

  if (isSubmitButton || hasLivewireAction(action)) {
    markActionLoading(action);
  }
}, true);

document.addEventListener('livewire:init', () => {
  Livewire.on('notify', (payload) => {
    window.dispatchEvent(new CustomEvent('marketlens:notify', {
      detail: Array.isArray(payload) ? payload[0] : payload,
    }));
  });

  Livewire.hook('commit', ({ succeed, fail }) => {
    succeed(() => clearActionLoaders());
    fail(() => clearActionLoaders());
  });
});

document.addEventListener('livewire:navigated', clearActionLoaders);
