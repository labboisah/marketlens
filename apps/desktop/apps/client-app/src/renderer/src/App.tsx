import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useMemo } from 'react';
import { ErrorBoundary } from './components/ErrorBoundary';
import { NotificationCenter } from './components/NotificationCenter';
import { PermissionAwareNavigation } from './components/PermissionAwareNavigation';
import { SessionTimeoutProvider } from './components/SessionTimeoutProvider';
import { ConnectionScreen } from './screens/ConnectionScreen';
import { DevicePairingScreen } from './screens/DevicePairingScreen';
import { LoginScreen } from './screens/LoginScreen';
import { RoleWorkspace } from './screens/RoleWorkspace';
import { useClientUiStore } from './state/client-ui-store';

export function App() {
  const queryClient = useMemo(() => new QueryClient(), []);
  const screen = useClientUiStore((state) => state.screen);

  return (
    <QueryClientProvider client={queryClient}>
      <ErrorBoundary>
        <SessionTimeoutProvider>
          <main className="min-h-screen bg-background text-foreground">
            {screen === 'connection' ? <ConnectionScreen /> : null}
            {screen === 'pairing' ? <DevicePairingScreen /> : null}
            {screen === 'login' ? <LoginScreen /> : null}
            {screen === 'workspace' ? (
              <div className="grid min-h-screen grid-cols-[260px_1fr]">
                <PermissionAwareNavigation />
                <RoleWorkspace />
              </div>
            ) : null}
            <NotificationCenter />
          </main>
        </SessionTimeoutProvider>
      </ErrorBoundary>
    </QueryClientProvider>
  );
}
