import './bootstrap';


Alpine.data('marketlensShell', () => ({
  sidebarOpen: false,
  theme: localStorage.getItem('marketlens-theme') || 'light',
  notifications: [],
  init() {
    document.documentElement.dataset.theme = this.theme;
    this.notificationListener = (event) => this.handleNotificationEvent(event);
    document.addEventListener('notify', this.notificationListener);
    window.addEventListener('notify', this.notificationListener);
    document.addEventListener('marketlens:notify', this.notificationListener);
    window.addEventListener('marketlens:notify', this.notificationListener);
  },
  toggleTheme() {
    this.theme = this.theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem('marketlens-theme', this.theme);
    document.documentElement.dataset.theme = this.theme;
  },
  handleNotificationEvent(event) {
    if (event.__marketlensNotificationHandled) {
      return;
    }

    event.__marketlensNotificationHandled = true;
    this.notify(event.detail || {});
  },
  notify(notification) {
    notification = Array.isArray(notification) ? (notification[0] || {}) : (notification || {});
    notification = notification.detail ? notification.detail : notification;
    notification = Array.isArray(notification) ? (notification[0] || {}) : notification;

    this.showFluxToast(notification);
  },
  showFluxToast(notification) {
    const variant = { information: 'info' }[notification.tone] || notification.tone || 'info';

    document.dispatchEvent(new CustomEvent('toast-show', {
      bubbles: true,
      detail: {
        duration: notification.timeout || 5000,
        slots: {
          heading: notification.title || 'MarketLens update',
          text: notification.message || '',
        },
        dataset: { variant },
      },
    }));
  },
  dismissNotification() {},
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



const actionSelector = 'button, a[role="button"]';
const navigationSelector = 'a[href]:not([target]):not([download])';
let pageProgressTimer;

const startPageProgress = () => {
  window.clearTimeout(pageProgressTimer);
  window.dispatchEvent(new CustomEvent('marketlens:page-progress-start'));
  pageProgressTimer = window.setTimeout(finishPageProgress, 8000);
};

const finishPageProgress = () => {
  window.clearTimeout(pageProgressTimer);
  window.dispatchEvent(new CustomEvent('marketlens:page-progress-finish'));
};

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

document.addEventListener('click', (event) => {
  const link = event.target.closest(navigationSelector);

  if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
    return;
  }

  const href = link.getAttribute('href') || '';

  if (href === '' || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || link.dataset.mlSkipLoading === 'true') {
    return;
  }

  startPageProgress();
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
document.addEventListener('livewire:navigate', startPageProgress);
document.addEventListener('livewire:navigated', finishPageProgress);
document.addEventListener('livewire:navigate-cancelled', finishPageProgress);
window.addEventListener('beforeunload', startPageProgress);
window.addEventListener('pageshow', finishPageProgress);
