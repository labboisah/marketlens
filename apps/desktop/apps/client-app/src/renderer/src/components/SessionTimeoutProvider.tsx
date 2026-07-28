import { useEffect, type PropsWithChildren } from 'react';
import { useClientUiStore } from '../state/client-ui-store';

const sessionTimeoutMs = 30 * 60 * 1000;

export function SessionTimeoutProvider({ children }: PropsWithChildren) {
  const screen = useClientUiStore((state) => state.screen);
  const setScreen = useClientUiStore((state) => state.setScreen);
  const notify = useClientUiStore((state) => state.notify);

  useEffect(() => {
    if (screen !== 'workspace') {
      return undefined;
    }

    const timeout = window.setTimeout(() => {
      void window.marketlensClient.session.clear();
      setScreen('login');
      notify({
        tone: 'warning',
        title: 'Session expired',
        message: 'Sign in again to continue using this workstation.',
      });
    }, sessionTimeoutMs);

    return () => window.clearTimeout(timeout);
  }, [notify, screen, setScreen]);

  return children;
}
