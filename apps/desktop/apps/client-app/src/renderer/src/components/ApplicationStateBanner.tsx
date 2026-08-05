import { Button, StatusBadge } from '@marketlens/ui';
import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { LogoutConfirmation } from './LogoutConfirmation';
import { logoutFromShop } from '../services/shop-api-client';
import { useClientUiStore } from '../state/client-ui-store';

const toneByState = {
  not_configured: 'warning',
  searching_for_shop_server: 'information',
  awaiting_pairing: 'warning',
  paired: 'information',
  disconnected: 'danger',
  session_expired: 'warning',
  account_disabled: 'danger',
  password_change_required: 'warning',
  authenticated: 'information',
  shop_suspended: 'danger',
  subscription_expired: 'danger',
  module_disabled: 'warning',
  update_required: 'warning',
  ready: 'success',
} as const;

function labelForState(state: string): string {
  return state.split('_').map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(' ');
}

export function ApplicationStateBanner() {
  const [showLogoutConfirmation, setShowLogoutConfirmation] = useState(false);
  const applicationState = useClientUiStore((state) => state.applicationState);
  const applicationStateMessage = useClientUiStore((state) => state.applicationStateMessage);
  const activeRole = useClientUiStore((state) => state.activeRole);
  const permissions = useClientUiStore((state) => state.permissions);
  const connectionState = useClientUiStore((state) => state.connectionState);
  const setLock = useClientUiStore((state) => state.setLock);
  const resetSession = useClientUiStore((state) => state.resetSession);
  const setSessionState = useClientUiStore((state) => state.setSessionState);
  const shopStatus = useClientUiStore((state) => state.shopStatus);
  const lastSuccessfulConnectionAt = useClientUiStore((state) => state.lastSuccessfulConnectionAt);
  const notify = useClientUiStore((state) => state.notify);

  const logout = useMutation({
    mutationFn: async () => {
      const session = await window.marketlensClient.session.load();
      if (session) {
        await logoutFromShop(session.shopBaseUrl, session.token, session.refreshToken).catch(() => undefined);
      }
      await window.marketlensClient.session.clear();
    },
    onSuccess: () => {
      setShowLogoutConfirmation(false);
      setSessionState('expired');
      resetSession();
      notify({
        tone: 'information',
        title: 'Signed out',
        message: 'You have been signed out from this workstation.',
      });
    },
    onError: () => {
      notify({
        tone: 'danger',
        title: 'Logout failed',
        message: 'Authentication failed. Please try again.',
      });
    },
  });

  return (
    <section className="border-b border-border bg-surface px-4 py-3">
      <div className="flex flex-wrap items-center gap-3">
        <StatusBadge tone={toneByState[applicationState]}>{labelForState(applicationState)}</StatusBadge>
        <span className="text-sm text-muted">{applicationStateMessage}</span>
        <span className="text-xs text-muted">Role: {activeRole}</span>
        <span className="text-xs text-muted">Permissions: {permissions.length}</span>
        <span className="text-xs text-muted">Business: {shopStatus?.businessName ?? 'Unknown'}</span>
        <span className="text-xs text-muted">Branch: {shopStatus?.branchName ?? 'Unknown'}</span>
        <span className="text-xs text-muted">Shop device: {shopStatus?.shopDeviceName ?? 'Unknown'}</span>
        <span className="text-xs text-muted">Server IP: {shopStatus?.serverIp ?? 'Unknown'}</span>
        <span className="text-xs text-muted">Server port: {shopStatus?.serverPort ?? 'Unknown'}</span>
        <span className="text-xs text-muted">Connection: {connectionState}</span>
        <span className="text-xs text-muted">Last success: {lastSuccessfulConnectionAt ?? 'Never'}</span>
        <Button variant="ghost" onClick={() => setLock('Workstation locked manually.')}>Lock screen</Button>
        <Button variant="ghost" onClick={() => setShowLogoutConfirmation(true)}>Logout</Button>
      </div>
      <LogoutConfirmation
        open={showLogoutConfirmation}
        pending={logout.isPending}
        onCancel={() => setShowLogoutConfirmation(false)}
        onConfirm={() => logout.mutate()}
      />
    </section>
  );
}