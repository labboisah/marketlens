import { StatusBadge } from '@marketlens/ui';
import { roleShells, canSeeItem } from '../features/roles/role-shells';
import { useClientUiStore } from '../state/client-ui-store';
import { ConnectionStatusIndicator } from '../components/ConnectionStatusIndicator';

export function RoleWorkspace() {
  const activeRole = useClientUiStore((state) => state.activeRole);
  const permissions = useClientUiStore((state) => state.permissions);
  const shell = roleShells.find((roleShell) => roleShell.role === activeRole) ?? roleShells[0];

  return (
    <section className="min-w-0 p-6">
      <header className="flex items-start justify-between gap-4 border-b border-border pb-4">
        <div>
          <h1 className="text-2xl font-semibold">{shell.label}</h1>
          <p className="mt-1 text-sm text-muted">Workflow shells only. Transactions require Shop App confirmation.</p>
        </div>
        <ConnectionStatusIndicator />
      </header>

      <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {shell.items.map((item) => {
          const visible = canSeeItem(permissions, item);

          return (
            <article key={item.id} id={item.id} className="rounded-lg border border-border bg-surface p-4 shadow-sm">
              <div className="flex items-start justify-between gap-3">
                <h2 className="font-semibold">{item.label}</h2>
                <StatusBadge tone={visible ? 'success' : 'warning'}>{visible ? 'Allowed' : 'Hidden'}</StatusBadge>
              </div>
              <p className="mt-3 text-sm text-muted">
                Shell created for permission <span className="font-mono">{item.permission}</span>. Full workflow is not
                implemented yet.
              </p>
            </article>
          );
        })}
      </div>
    </section>
  );
}
