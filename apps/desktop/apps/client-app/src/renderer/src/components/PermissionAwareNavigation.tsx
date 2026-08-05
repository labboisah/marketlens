import { useEffect, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Button, StatusBadge } from '@marketlens/ui';
import {
  resolveVisibleShellNavigation,
  roleLabel,
  shellNavigationGroupLabels,
  startingRolePresets,
} from '../features/navigation/application-shell-navigation';
import { useClientUiStore } from '../state/client-ui-store';
import { ConnectionStatusIndicator } from './ConnectionStatusIndicator';
import { getServicesModuleAccess } from '../services/services-service';

export function PermissionAwareNavigation() {
  const activeRole = useClientUiStore((state) => state.activeRole);
  const activeNavigationId = useClientUiStore((state) => state.activeNavigationId);
  const permissions = useClientUiStore((state) => state.permissions);
  const shopStatus = useClientUiStore((state) => state.shopStatus);
  const shiftStatus = useClientUiStore((state) => state.shiftStatus);
  const username = useClientUiStore((state) => state.username);
  const shopBaseUrl = useClientUiStore((state) => state.shopBaseUrl);
  const deviceUuid = useClientUiStore((state) => state.deviceUuid);
  const setActiveNavigationId = useClientUiStore((state) => state.setActiveNavigationId);
  const setActiveRole = useClientUiStore((state) => state.setActiveRole);
  const sessionQuery = useQuery({
    queryKey: ['navigation-session'],
    enabled: Boolean(shopBaseUrl && deviceUuid),
    queryFn: async () => window.marketlensClient.session.load() as Promise<{ shopBaseUrl: string; token: string; deviceUuid: string }>,
  });

  const servicesAccessQuery = useQuery({
    queryKey: ['services-module-access-navigation'],
    enabled: Boolean(sessionQuery.data?.token && shopBaseUrl && deviceUuid),
    queryFn: async () => getServicesModuleAccess({
      shopBaseUrl: sessionQuery.data?.shopBaseUrl || shopBaseUrl,
      accessToken: sessionQuery.data!.token,
      deviceUuid: sessionQuery.data?.deviceUuid || deviceUuid || '',
    }),
  });

  const visibleItems = useMemo(() => {
    const shellItems = resolveVisibleShellNavigation({ permissions, shopStatus, shiftStatus }).filter((item) => item.visible);
    if (servicesAccessQuery.data && !servicesAccessQuery.data.enabled) {
      return shellItems.filter((item) => item.id !== 'services-workflow' && item.group !== 'services' && item.group !== 'staff_earnings');
    }
    if (!servicesAccessQuery.data && sessionQuery.data?.token) {
      return shellItems.filter((item) => item.id !== 'services-workflow' && item.group !== 'services' && item.group !== 'staff_earnings');
    }
    return shellItems;
  }, [permissions, sessionQuery.data?.token, servicesAccessQuery.data, shopStatus, shiftStatus]);

  const visibleGroups = Object.entries(shellNavigationGroupLabels)
    .map(([groupId, label]) => ({
      groupId,
      label,
      items: visibleItems.filter((item) => item.group === groupId),
    }))
    .filter((group) => group.items.length > 0);

  useEffect(() => {
    if (visibleItems.length === 0) {
      return;
    }

    const activeStillVisible = visibleItems.some((item) => item.id === activeNavigationId);
    if (!activeStillVisible) {
      setActiveNavigationId(visibleItems[0].id);
    }
  }, [activeNavigationId, setActiveNavigationId, visibleItems]);

  return (
    <aside className="flex min-h-screen flex-col border-r border-border bg-surface p-4">
      <div className="mb-4">
        <h1 className="text-lg font-bold">MarketLens Client</h1>
        <p className="text-sm text-muted">Permission-driven workstation shell</p>
      </div>

      <ConnectionStatusIndicator />

      <div className="mt-4 rounded-md border border-border p-3">
        <p className="text-xs font-semibold uppercase tracking-wide text-muted">Starting role</p>
        <p className="mt-1 text-sm text-muted">Changes preferred shell emphasis only.</p>
        <select
          className="mt-2 w-full rounded-md border border-border bg-background px-2 py-2 text-sm"
          value={activeRole}
          onChange={async (event) => {
            const nextRole = event.target.value as typeof activeRole;
            setActiveRole(nextRole);
            const preferences = await window.marketlensClient.preferences.load();
            await window.marketlensClient.preferences.save({
              ...preferences,
              preferredRole: nextRole,
            });
          }}
        >
          {startingRolePresets.map((preset) => (
            <option key={preset.value} value={preset.value}>{preset.label}</option>
          ))}
        </select>
      </div>

      <nav className="mt-5 flex-1 space-y-4 overflow-y-auto" aria-label="Main navigation groups">
        {visibleGroups.length > 0 ? (
          visibleGroups.map((group) => (
            <section key={group.groupId}>
              <p className="px-2 text-xs font-semibold uppercase tracking-wide text-muted">{group.label}</p>
              <div className="mt-1 grid gap-1">
                {group.items.map((item) => (
                  <button
                    key={item.id}
                    type="button"
                    className={`w-full rounded-md px-3 py-2 text-left text-sm ${item.id === activeNavigationId ? 'bg-primary text-white' : 'hover:bg-surface-muted'}`}
                    onClick={() => setActiveNavigationId(item.id)}
                  >
                    {item.label}
                  </button>
                ))}
              </div>
            </section>
          ))
        ) : (
          <StatusBadge tone="warning">No modules are available for current permissions and settings.</StatusBadge>
        )}
      </nav>

      <div className="mt-4 rounded-md border border-border p-3 text-xs text-muted">
        <p className="font-semibold text-foreground">{username ?? 'Workstation user'}</p>
        <p>Role preset: {roleLabel(activeRole)}</p>
      </div>

      <div className="mt-3 grid gap-2">
        <Button variant="ghost" onClick={() => setActiveNavigationId('dashboard-home')}>Go to dashboard</Button>
      </div>
    </aside>
  );
}
