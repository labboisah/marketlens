import { Button, StatusBadge } from '@marketlens/ui';
import { roleShells, canSeeItem } from '../features/roles/role-shells';
import { useClientUiStore } from '../state/client-ui-store';
import { ConnectionStatusIndicator } from './ConnectionStatusIndicator';

export function PermissionAwareNavigation() {
  const activeRole = useClientUiStore((state) => state.activeRole);
  const permissions = useClientUiStore((state) => state.permissions);
  const setActiveRole = useClientUiStore((state) => state.setActiveRole);
  const activeShell = roleShells.find((shell) => shell.role === activeRole) ?? roleShells[0];
  const visibleItems = activeShell.items.filter((item) => canSeeItem(permissions, item));

  return (
    <aside className="border-r border-border bg-surface p-4">
      <div className="mb-4">
        <h1 className="text-lg font-bold">MarketLens Client</h1>
        <p className="text-sm text-muted">Role-based workstation</p>
      </div>

      <ConnectionStatusIndicator />

      <div className="mt-5 grid gap-2">
        {roleShells.map((shell) => (
          <Button
            key={shell.role}
            variant={shell.role === activeRole ? 'primary' : 'ghost'}
            className="justify-start"
            onClick={() => setActiveRole(shell.role)}
          >
            {shell.label}
          </Button>
        ))}
      </div>

      <nav className="mt-6 grid gap-2" aria-label={`${activeShell.label} navigation`}>
        {visibleItems.length > 0 ? (
          visibleItems.map((item) => (
            <a key={item.id} className="rounded-md px-3 py-2 text-sm font-medium hover:bg-surface-muted" href={`#${item.id}`}>
              {item.label}
            </a>
          ))
        ) : (
          <StatusBadge tone="warning">No visible features</StatusBadge>
        )}
      </nav>
    </aside>
  );
}
