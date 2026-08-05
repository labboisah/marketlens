import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useEffect, useMemo, useState } from 'react';
import { ApplicationStateBanner } from './components/ApplicationStateBanner';
import { ErrorBoundary } from './components/ErrorBoundary';
import { NotificationCenter } from './components/NotificationCenter';
import { PermissionAwareNavigation } from './components/PermissionAwareNavigation';
import { SessionTimeoutProvider } from './components/SessionTimeoutProvider';
import { StateNoticeScreen } from './screens/StateNoticeScreen';
import { SessionExpiredScreen } from './screens/SessionExpiredScreen';
import { AccountDisabledScreen } from './screens/AccountDisabledScreen';
import { PasswordChangeScreen } from './screens/PasswordChangeScreen';
import { LockScreen } from './screens/LockScreen';
import { ConnectionScreen } from './screens/ConnectionScreen';
import { DevicePairingScreen } from './screens/DevicePairingScreen';
import { LoginScreen } from './screens/LoginScreen';
import { RoleWorkspace } from './screens/RoleWorkspace';
import { checkDeviceStatus, checkShopStatus, readAuthenticatedSession, readAuthState, refreshShopSession } from './services/shop-api-client';
import { resolveApplicationState } from './services/application-state-service';
import { useClientUiStore } from './state/client-ui-store';

export function App() {
  const queryClient = useMemo(() => new QueryClient(), []);
  const screen = useClientUiStore((state) => state.screen);
  const shopBaseUrl = useClientUiStore((state) => state.shopBaseUrl);
  const connectionState = useClientUiStore((state) => state.connectionState);
  const applicationState = useClientUiStore((state) => state.applicationState);
  const applicationStateMessage = useClientUiStore((state) => state.applicationStateMessage);
  const activeRole = useClientUiStore((state) => state.activeRole);
  const permissions = useClientUiStore((state) => state.permissions);
  const setPermissions = useClientUiStore((state) => state.setPermissions);
  const setRoles = useClientUiStore((state) => state.setRoles);
  const setCurrentUser = useClientUiStore((state) => state.setCurrentUser);
  const setAccountStatus = useClientUiStore((state) => state.setAccountStatus);
  const setPasswordChangeRequired = useClientUiStore((state) => state.setPasswordChangeRequired);
  const setSessionState = useClientUiStore((state) => state.setSessionState);
  const deviceUuid = useClientUiStore((state) => state.deviceUuid);
  const deviceAuthorizationState = useClientUiStore((state) => state.deviceAuthorizationState);
  const sessionState = useClientUiStore((state) => state.sessionState);
  const accountStatus = useClientUiStore((state) => state.accountStatus);
  const passwordChangeRequired = useClientUiStore((state) => state.passwordChangeRequired);
  const shopStatus = useClientUiStore((state) => state.shopStatus);
  const currentVersion = useClientUiStore((state) => state.currentVersion);
  const updateRequired = useClientUiStore((state) => state.updateRequired);
  const bootstrap = useClientUiStore((state) => state.bootstrap);
  const notify = useClientUiStore((state) => state.notify);
  const resetSession = useClientUiStore((state) => state.resetSession);
  const setApplicationState = useClientUiStore((state) => state.setApplicationState);
  const setScreen = useClientUiStore((state) => state.setScreen);
  const [isBootstrapping, setIsBootstrapping] = useState(true);

  useEffect(() => {
    let cancelled = false;

    const restore = async () => {
      try {
        const [preferences, connectionSettings, session, appInfo, updateStatus] = await Promise.all([
          window.marketlensClient.preferences.load(),
          window.marketlensClient.connectionSettings.load(),
          window.marketlensClient.session.load(),
          window.marketlensClient.appInfo.get(),
          window.marketlensClient.updates.check(),
        ]);
        const activeRole = preferences.preferredRole;
        const resolvedBaseUrl = connectionSettings.currentServerUrl || preferences.shopBaseUrl;

        const statusResult = resolvedBaseUrl
          ? await checkShopStatus(resolvedBaseUrl).catch(() => null)
          : null;

        const baseBootstrap = {
          shopBaseUrl: resolvedBaseUrl,
          activeRole,
          currentVersion: appInfo.version,
          updateRequired: updateStatus.updateAvailable,
          connectionState: statusResult ? 'connected' as const : 'disconnected' as const,
          shopStatus: statusResult?.status,
          lastSuccessfulConnectionAt: connectionSettings.lastSuccessfulConnectionAt,
        };

        if (!session) {
          bootstrap({
            ...baseBootstrap,
            screen: 'connection',
            deviceAuthorizationState: 'unpaired',
            sessionState: 'missing',
            shiftStatus: 'closed',
          });
          return;
        }

        const bootShopBaseUrl = session.shopBaseUrl || resolvedBaseUrl;
        const [confirmedStatus, resolvedDeviceAuthorizationState] = await Promise.all([
          checkShopStatus(bootShopBaseUrl).catch(() => null),
          session.deviceUuid
            ? checkDeviceStatus(bootShopBaseUrl, session.deviceUuid).catch(() => 'unknown' as const)
            : Promise.resolve('unpaired' as const),
        ]);

        let accessToken = session.token;
        let refreshToken = session.refreshToken;
        let expiresAt = session.expiresAt;

        let authState;
        let authSessionState;

        try {
          [authState, authSessionState] = await Promise.all([
            readAuthState(bootShopBaseUrl, accessToken),
            readAuthenticatedSession(bootShopBaseUrl, accessToken),
          ]);
        } catch {
          const refreshed = await refreshShopSession(bootShopBaseUrl, refreshToken);
          accessToken = refreshed.accessToken;
          refreshToken = refreshed.refreshToken;
          expiresAt = refreshed.expiresAt;
          [authState, authSessionState] = await Promise.all([
            readAuthState(bootShopBaseUrl, accessToken),
            readAuthenticatedSession(bootShopBaseUrl, accessToken),
          ]);
        }

        await window.marketlensClient.session.save({
          ...session,
          shopBaseUrl: bootShopBaseUrl,
          token: accessToken,
          refreshToken,
          expiresAt,
          accountStatus: authState.accountStatus,
          passwordChangeRequired: authState.passwordChangeRequired,
        });

        if (!authSessionState.authenticated || !authSessionState.user || !authState.authenticated || !authState.user) {
          await window.marketlensClient.session.clear();
          bootstrap({
            ...baseBootstrap,
            screen: 'session_expired',
            deviceUuid: session.deviceUuid,
            deviceAuthorizationState: resolvedDeviceAuthorizationState,
            sessionState: 'expired',
            shiftStatus: 'closed',
          });
          return;
        }

        bootstrap({
          shopBaseUrl: bootShopBaseUrl,
          activeRole,
          screen: 'workspace',
          permissions: authState.permissions,
          roles: authState.roles,
          username: authState.user.username,
          rememberDevice: session.rememberDevice,
          accountStatus: authState.accountStatus,
          passwordChangeRequired: authState.passwordChangeRequired,
          deviceUuid: session.deviceUuid,
          deviceAuthorizationState: resolvedDeviceAuthorizationState,
          sessionState: 'active',
          shopStatus: confirmedStatus?.status,
          lastSuccessfulConnectionAt: connectionSettings.lastSuccessfulConnectionAt,
          currentVersion: appInfo.version,
          updateRequired: updateStatus.updateAvailable,
          currentUser: authState.user,
          connectionState: confirmedStatus ? 'connected' : 'disconnected',
          shiftStatus: 'open',
        });
      } catch (error) {
        await window.marketlensClient.session.clear();
        resetSession();
        notify({
          tone: 'warning',
          title: 'Session cleared',
          message: error instanceof Error ? error.message : 'Stored workstation session could not be restored.',
        });
      } finally {
        if (!cancelled) {
          setIsBootstrapping(false);
        }
      }
    };

    void restore();

    return () => {
      cancelled = true;
    };
  }, [bootstrap, notify, resetSession]);

  useEffect(() => {
    const resolved = resolveApplicationState({
      shopBaseUrl,
      connectionState,
      deviceAuthorizationState,
      sessionState,
      accountStatus,
      passwordChangeRequired,
      activeRole,
      permissions,
      currentVersion,
      updateRequired,
      shopStatus,
    });

    if (applicationState !== resolved.state || applicationStateMessage !== resolved.message) {
      setApplicationState(resolved.state, resolved.message);
    }
    if (screen !== 'lock' && screen !== resolved.screen) {
      setScreen(resolved.screen);
    }
  }, [accountStatus, activeRole, applicationState, applicationStateMessage, connectionState, currentVersion, deviceAuthorizationState, passwordChangeRequired, permissions, screen, sessionState, setApplicationState, setScreen, shopBaseUrl, shopStatus, updateRequired]);

  useEffect(() => {
    if (sessionState !== 'active' || !shopBaseUrl) {
      return undefined;
    }

    const interval = window.setInterval(() => {
      const refreshAuth = async () => {
        const stored = await window.marketlensClient.session.load();
        if (!stored) {
          return;
        }

        const refreshWindowMs = 5 * 60 * 1000;
        let token = stored.token;
        let refreshToken = stored.refreshToken;
        let expiresAt = stored.expiresAt;

        if (new Date(stored.expiresAt).getTime() - Date.now() <= refreshWindowMs) {
          const refreshed = await refreshShopSession(stored.shopBaseUrl, stored.refreshToken);
          token = refreshed.accessToken;
          refreshToken = refreshed.refreshToken;
          expiresAt = refreshed.expiresAt;
        }

        const [authState, authSessionState] = await Promise.all([
          readAuthState(stored.shopBaseUrl, token),
          readAuthenticatedSession(stored.shopBaseUrl, token),
        ]);

        if (!authState.authenticated || !authState.user || !authSessionState.authenticated || !authSessionState.user) {
          throw new Error('Authentication failed.');
        }

        await window.marketlensClient.session.save({
          ...stored,
          token,
          refreshToken,
          expiresAt,
          username: authState.user.username,
          accountStatus: authState.accountStatus,
          passwordChangeRequired: authState.passwordChangeRequired,
          permissions: authState.permissions,
          roles: authState.roles,
        });

        setPermissions(authState.permissions);
        setRoles(authState.roles);
        setCurrentUser(authState.user);
        setAccountStatus(authState.accountStatus);
        setPasswordChangeRequired(authState.passwordChangeRequired);
      };

      void refreshAuth().catch(async () => {
        await window.marketlensClient.session.clear();
        setSessionState('expired');
      });
    }, 60 * 1000);

    return () => window.clearInterval(interval);
  }, [sessionState, setAccountStatus, setCurrentUser, setPasswordChangeRequired, setPermissions, setRoles, setSessionState, shopBaseUrl]);

  if (isBootstrapping) {
    return <main className="grid min-h-screen place-items-center bg-background text-foreground">Restoring workstation...</main>;
  }

  const showStateNotice = ['authenticated', 'shop_suspended', 'subscription_expired', 'module_disabled', 'update_required'].includes(applicationState);

  return (
    <QueryClientProvider client={queryClient}>
      <ErrorBoundary>
        <SessionTimeoutProvider>
          <main className="min-h-screen bg-background text-foreground">
            <ApplicationStateBanner />
            {screen === 'connection' ? <ConnectionScreen /> : null}
            {screen === 'pairing' ? <DevicePairingScreen /> : null}
            {screen === 'login' ? <LoginScreen /> : null}
            {screen === 'session_expired' ? <SessionExpiredScreen /> : null}
            {screen === 'account_disabled' ? <AccountDisabledScreen /> : null}
            {screen === 'password_change' ? <PasswordChangeScreen /> : null}
            {screen === 'lock' ? <LockScreen /> : null}
            {screen === 'workspace' && showStateNotice ? <StateNoticeScreen title={applicationState} message={applicationStateMessage} /> : null}
            {screen === 'workspace' && !showStateNotice ? (
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
