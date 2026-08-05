import { useMemo, useState } from 'react';
import { Button, ShiftStatusIndicator, StatusBadge, SynchronizationStatusIndicator } from '@marketlens/ui';
import { ChevronDown, Bell, Search } from 'lucide-react';
import {
  resolveSyncState,
  resolveVisibleShellNavigation,
  roleLabel,
  shellNavigationGroupLabels,
  startingRolePresets,
} from '../features/navigation/application-shell-navigation';
import { useClientUiStore } from '../state/client-ui-store';
import { ConnectionStatusIndicator } from '../components/ConnectionStatusIndicator';

function updateBadge(updateRequired: boolean) {
  return updateRequired ? 'Update required' : 'Up to date';
}

export function RoleWorkspace() {
  const activeRole = useClientUiStore((state) => state.activeRole);
  const activeNavigationId = useClientUiStore((state) => state.activeNavigationId);
  const permissions = useClientUiStore((state) => state.permissions);
  const roles = useClientUiStore((state) => state.roles);
  const username = useClientUiStore((state) => state.username);
  const shiftStatus = useClientUiStore((state) => state.shiftStatus);
  const shopStatus = useClientUiStore((state) => state.shopStatus);
  const connectionState = useClientUiStore((state) => state.connectionState);
  const updateRequired = useClientUiStore((state) => state.updateRequired);
  const notifications = useClientUiStore((state) => state.notifications);
  const applicationStateMessage = useClientUiStore((state) => state.applicationStateMessage);
  const setActiveNavigationId = useClientUiStore((state) => state.setActiveNavigationId);
  const setScreen = useClientUiStore((state) => state.setScreen);
  const setActiveRole = useClientUiStore((state) => state.setActiveRole);
  const [theme, setTheme] = useState<string>(document.documentElement.getAttribute('data-theme') ?? 'light');
  const [searchText, setSearchText] = useState('');

  const visibleItems = useMemo(
    () =>
      resolveVisibleShellNavigation({
        permissions,
        shopStatus,
        shiftStatus,
      }).filter((item) => item.visible),
    [permissions, shopStatus, shiftStatus],
  );

  const activeItem = visibleItems.find((item) => item.id === activeNavigationId) ?? visibleItems[0];
  const activeGroupLabel = activeItem ? shellNavigationGroupLabels[activeItem.group] : 'Workspace';
  const syncState = resolveSyncState(connectionState, shopStatus);
  const searchResults = searchText.trim().length === 0
    ? []
    : visibleItems.filter((item) => {
        const haystack = `${item.label} ${item.description} ${item.actions.map((action) => action.label).join(' ')}`.toLowerCase();
        return haystack.includes(searchText.trim().toLowerCase());
      });

  const applyTheme = (nextTheme: string) => {
    document.documentElement.setAttribute('data-theme', nextTheme);
    localStorage.setItem('marketlens.client.theme', nextTheme);
    setTheme(nextTheme);
  };

  const selectRolePreset = async (nextRole: typeof activeRole) => {
    setActiveRole(nextRole);
    const preferences = await window.marketlensClient.preferences.load();
    await window.marketlensClient.preferences.save({
      ...preferences,
      preferredRole: nextRole,
    });
  };

  return (
    <section className="min-w-0 bg-background">
      <header className="sticky top-0 z-20 border-b border-border bg-background/95 px-6 py-4 backdrop-blur">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 className="text-xl font-semibold">{activeItem?.label ?? 'Client Workspace'}</h1>
            <p className="mt-1 text-sm text-muted">
              {shopStatus?.businessName ?? 'Unknown business'} / {shopStatus?.branchName ?? 'Unknown branch'}
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <ShiftStatusIndicator status={shiftStatus} />
            <ConnectionStatusIndicator />
            <SynchronizationStatusIndicator state={syncState} lastSyncedAt={shopStatus?.lastSynchronization ?? undefined} />
            <StatusBadge tone={updateRequired ? 'warning' : 'success'}>{updateBadge(updateRequired)}</StatusBadge>
            <div className="flex items-center gap-2 rounded-md border border-border px-2 py-1">
              <label htmlFor="theme-select" className="text-xs text-muted">Theme</label>
              <select
                id="theme-select"
                value={theme}
                onChange={(event) => applyTheme(event.target.value)}
                className="rounded bg-transparent text-sm"
              >
                <option value="light">Light</option>
                <option value="dark">Dark</option>
              </select>
            </div>
            <StatusBadge tone="information">
              <Bell className="h-4 w-4" />
              {notifications.length} notifications
            </StatusBadge>
            <details className="group relative">
              <summary className="flex cursor-pointer list-none items-center gap-2 rounded-md border border-border px-3 py-2 text-sm hover:bg-surface-muted">
                <span>{username ?? 'User'}</span>
                <ChevronDown className="h-4 w-4" />
              </summary>
              <div className="absolute right-0 mt-2 w-72 rounded-md border border-border bg-surface p-3 shadow-md">
                <p className="text-xs text-muted">Signed in roles</p>
                <p className="text-sm">{roles.length > 0 ? roles.join(', ') : 'No role claims returned'}</p>
                <p className="mt-2 text-xs text-muted">Starting role preset</p>
                <select
                  className="mt-1 w-full rounded-md border border-border bg-background px-2 py-2 text-sm"
                  value={activeRole}
                  onChange={(event) => {
                    void selectRolePreset(event.target.value as typeof activeRole);
                  }}
                >
                  {startingRolePresets.map((preset) => (
                    <option key={preset.value} value={preset.value}>{preset.label}</option>
                  ))}
                </select>
                <p className="mt-2 text-xs text-muted">Current preset: {roleLabel(activeRole)}</p>
              </div>
            </details>
          </div>
        </div>

        <div className="mt-3 flex flex-wrap items-center gap-3">
          <p className="text-xs text-muted">Dashboard / {activeGroupLabel} / {activeItem?.label ?? 'Workspace'}</p>
          <div className="relative max-w-lg flex-1">
            <Search className="pointer-events-none absolute left-2 top-2.5 h-4 w-4 text-muted" />
            <input
              value={searchText}
              onChange={(event) => setSearchText(event.target.value)}
              placeholder="Global search foundation: modules, actions, workflows"
              className="w-full rounded-md border border-border bg-surface py-2 pl-8 pr-3 text-sm"
            />
            {searchResults.length > 0 ? (
              <div className="absolute left-0 right-0 top-11 z-30 max-h-64 overflow-y-auto rounded-md border border-border bg-surface p-2 shadow-md">
                {searchResults.map((item) => (
                  <button
                    key={item.id}
                    type="button"
                    className="mb-1 w-full rounded-md px-2 py-2 text-left text-sm hover:bg-surface-muted"
                    onClick={() => {
                      setActiveNavigationId(item.id);
                      setSearchText('');
                    }}
                  >
                    <p className="font-medium">{item.label}</p>
                    <p className="text-xs text-muted">{item.description}</p>
                  </button>
                ))}
              </div>
            ) : null}
          </div>
        </div>
      </header>

      <div className="p-6">
        <section className="rounded-lg border border-border bg-surface p-5">
          <h2 className="text-lg font-semibold">{activeItem?.label ?? 'No module selected'}</h2>
          <p className="mt-1 text-sm text-muted">{activeItem?.description ?? applicationStateMessage}</p>

          {activeItem ? (
            <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
              {activeItem.visibleActions.map((action) => (
                <article key={action.id} className="rounded-md border border-border bg-background p-4">
                  <div className="flex items-center justify-between gap-2">
                    <h3 className="text-sm font-semibold">{action.label}</h3>
                    <StatusBadge tone={action.visible ? 'success' : 'warning'}>{action.visible ? 'Allowed' : 'Blocked'}</StatusBadge>
                  </div>
                  <p className="mt-2 text-xs text-muted">
                    Permission checks are always enforced by the Shop App local API on request execution.
                  </p>
                  {action.id === 'connection-open' ? (
                    <Button className="mt-3" variant="ghost" onClick={() => setScreen('connection')}>Open connection manager</Button>
                  ) : null}
                </article>
              ))}
            </div>
          ) : (
            <StatusBadge tone="warning">No modules available for current permission and module state.</StatusBadge>
          )}
        </section>

        <section className="mt-4 rounded-lg border border-border bg-surface p-5">
          <h2 className="text-base font-semibold">Visibility policy</h2>
          <p className="mt-2 text-sm text-muted">
            Module visibility is derived from user permissions, subscription state, and enabled module settings from the Shop App status.
            Branch and business policy gates are represented through effective enabled modules returned by the Shop App.
          </p>
          <div className="mt-3 flex flex-wrap gap-2">
            <StatusBadge tone="information">Server: {shopStatus?.shopServerName ?? 'Unknown'}</StatusBadge>
            <StatusBadge tone="neutral">IP: {shopStatus?.serverIp ?? 'Unknown'}</StatusBadge>
            <StatusBadge tone="neutral">Port: {shopStatus?.serverPort ?? 'Unknown'}</StatusBadge>
            <StatusBadge tone="neutral">Subscription: {shopStatus?.subscriptionStatus ?? 'unknown'}</StatusBadge>
          </div>
        </section>
      </div>

      <div className="hidden">
        {visibleItems.map((item) => {
          return (
            <section key={item.id} id={item.id} />
          );
        })}
      </div>
    </section>
  );
}
