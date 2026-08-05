import { Button, StatusBadge } from '@marketlens/ui';
import { useClientUiStore } from '../state/client-ui-store';

export function AccountDisabledScreen() {
  const setScreen = useClientUiStore((state) => state.setScreen);

  return (
    <section className="grid min-h-screen place-items-center p-6">
      <div className="w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-sm">
        <StatusBadge tone="danger">Account access unavailable</StatusBadge>
        <h1 className="mt-4 text-xl font-semibold">Account disabled or suspended</h1>
        <p className="mt-2 text-sm text-muted">This account cannot access workstation operations. Contact a shop administrator to restore access.</p>
        <div className="mt-5 flex justify-end">
          <Button variant="ghost" onClick={() => setScreen('login')}>Back to sign in</Button>
        </div>
      </div>
    </section>
  );
}
