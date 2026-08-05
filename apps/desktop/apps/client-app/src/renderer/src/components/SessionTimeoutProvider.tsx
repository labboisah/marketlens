import { useEffect, type PropsWithChildren } from 'react';
import { useClientUiStore } from '../state/client-ui-store';
import { logoutFromShop } from '../services/shop-api-client';

const lockAfterInactivityMs = 10 * 60 * 1000;
const autoLogoutAfterInactivityMs = 30 * 60 * 1000;

export function SessionTimeoutProvider({ children }: PropsWithChildren) {
  const screen = useClientUiStore((state) => state.screen);
  const shopBaseUrl = useClientUiStore((state) => state.shopBaseUrl);
  const connectionState = useClientUiStore((state) => state.connectionState);
  const setLock = useClientUiStore((state) => state.setLock);
  const resetSession = useClientUiStore((state) => state.resetSession);
  const notify = useClientUiStore((state) => state.notify);

  useEffect(() => {
    if (screen !== 'workspace' || connectionState !== 'connected') {
      return undefined;
    }

    let lockTimer = window.setTimeout(() => {
      setLock('Session locked after inactivity.');
      notify({
        tone: 'warning',
        title: 'Workstation locked',
        message: 'Unlock with your password to continue.',
      });
    }, lockAfterInactivityMs);

    let logoutTimer = window.setTimeout(() => {
      const performLogout = async () => {
        const session = await window.marketlensClient.session.load();
        if (session) {
          await logoutFromShop(shopBaseUrl, session.token, session.refreshToken).catch(() => undefined);
        }
        await window.marketlensClient.session.clear();
        resetSession();
        notify({
          tone: 'warning',
          title: 'Session expired',
          message: 'Signed out automatically after prolonged inactivity.',
        });
      };

      void performLogout();
    }, autoLogoutAfterInactivityMs);

    const resetTimers = () => {
      window.clearTimeout(lockTimer);
      window.clearTimeout(logoutTimer);
      lockTimer = window.setTimeout(() => {
        setLock('Session locked after inactivity.');
        notify({
          tone: 'warning',
          title: 'Workstation locked',
          message: 'Unlock with your password to continue.',
        });
      }, lockAfterInactivityMs);
      logoutTimer = window.setTimeout(() => {
        const performLogout = async () => {
          const session = await window.marketlensClient.session.load();
          if (session) {
            await logoutFromShop(shopBaseUrl, session.token, session.refreshToken).catch(() => undefined);
          }
          await window.marketlensClient.session.clear();
          resetSession();
          notify({
            tone: 'warning',
            title: 'Session expired',
            message: 'Signed out automatically after prolonged inactivity.',
          });
        };

        void performLogout();
      }, autoLogoutAfterInactivityMs);
    };

    const events: Array<keyof WindowEventMap> = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll'];
    events.forEach((eventName) => window.addEventListener(eventName, resetTimers, { passive: true }));

    return () => {
      events.forEach((eventName) => window.removeEventListener(eventName, resetTimers));
      window.clearTimeout(lockTimer);
      window.clearTimeout(logoutTimer);
    };
  }, [connectionState, notify, resetSession, screen, setLock, shopBaseUrl]);

  return children;
}
