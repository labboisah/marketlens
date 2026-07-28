import { StatusBadge } from '@marketlens/ui';
import { X } from 'lucide-react';
import { useClientUiStore } from '../state/client-ui-store';

export function NotificationCenter() {
  const notifications = useClientUiStore((state) => state.notifications);
  const dismissNotification = useClientUiStore((state) => state.dismissNotification);

  return (
    <aside className="fixed bottom-4 right-4 z-50 grid w-96 max-w-[calc(100vw-2rem)] gap-2">
      {notifications.map((notification) => (
        <section key={notification.id} className="rounded-md border border-border bg-surface p-3 shadow-md">
          <div className="flex items-start justify-between gap-3">
            <div>
              <StatusBadge tone={notification.tone}>{notification.title}</StatusBadge>
              <p className="mt-2 text-sm text-muted">{notification.message}</p>
            </div>
            <button
              type="button"
              className="rounded-sm p-1 text-muted hover:bg-surface-muted"
              aria-label="Dismiss notification"
              onClick={() => dismissNotification(notification.id)}
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        </section>
      ))}
    </aside>
  );
}
