import { Button, StatusBadge } from '@marketlens/ui';
import { useClientUiStore } from '../state/client-ui-store';

export function SessionExpiredScreen() {
  const setScreen = useClientUiStore((state) => state.setScreen);

  return (
    <section className="grid min-h-screen place-items-center p-6">
      <div className="w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-sm">
        <StatusBadge tone="warning">Session expired</StatusBadge>
        <h1 className="mt-4 text-xl font-semibold">Sign in required</h1>
        <p className="mt-2 text-sm text-muted">Your session has expired due to timeout or inactivity. Sign in again to continue.</p>
        <div className="mt-5 flex justify-end">
          <Button onClick={() => setScreen('login')}>Go to sign in</Button>
        </div>
      </div>
    </section>
  );
}
