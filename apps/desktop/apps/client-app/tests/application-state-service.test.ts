import { describe, expect, it } from 'vitest';
import { resolveApplicationState } from '../src/renderer/src/services/application-state-service';

function baseContext() {
  return {
    shopBaseUrl: 'http://127.0.0.1:4090',
    connectionState: 'connected' as const,
    deviceAuthorizationState: 'authorized' as const,
    sessionState: 'active' as const,
    accountStatus: 'active' as const,
    passwordChangeRequired: false,
    activeRole: 'cashier' as const,
    permissions: ['pos.access'],
    currentVersion: '1.0.0',
    updateRequired: false,
    shopStatus: {
      applicationVersion: '1.0.0',
      databaseVersion: '3.45.0',
      businessUuid: '00000000-0000-4000-8000-000000000001',
      businessName: 'MarketLens Retail',
      branchUuid: '00000000-0000-4000-8000-000000000010',
      branchName: 'Main Branch',
      shopDeviceUuid: '00000000-0000-4000-8000-000000000099',
      shopDeviceName: 'Front Desk Shop Server',
      shopServerName: 'Front Desk Shop Server',
      serverIp: '192.168.1.20',
      serverPort: 4090,
      serverStatus: 'healthy',
      shopActivationStatus: 'active',
      subscriptionStatus: 'active',
      platformConnectivity: 'online',
      lastSynchronization: null,
      pendingSyncRecordCount: 0,
      authorizedClientAppCount: 1,
      enabledModules: ['sales', 'cashier_shifts', 'inventory', 'purchases', 'reporting', 'customers'],
      disabledModules: [],
    },
  };
}

describe('central Client App application state resolver', () => {
  it('resolves not_configured when no Shop App address exists', () => {
    const result = resolveApplicationState({
      ...baseContext(),
      shopBaseUrl: '   ',
      connectionState: 'disconnected',
      deviceAuthorizationState: 'unpaired',
      sessionState: 'missing',
      accountStatus: 'active',
      passwordChangeRequired: false,
      permissions: [],
    });

    expect(result.state).toBe('not_configured');
    expect(result.screen).toBe('connection');
  });

  it('resolves searching_for_shop_server while the workstation is connecting', () => {
    const result = resolveApplicationState({
      ...baseContext(),
      connectionState: 'connecting',
      deviceAuthorizationState: 'unpaired',
      sessionState: 'missing',
      accountStatus: 'active',
      passwordChangeRequired: false,
      permissions: [],
    });

    expect(result.state).toBe('searching_for_shop_server');
    expect(result.screen).toBe('connection');
  });

  it('resolves awaiting_pairing when the device has not been confirmed', () => {
    const result = resolveApplicationState({
      ...baseContext(),
      deviceAuthorizationState: 'pending',
      sessionState: 'missing',
      accountStatus: 'active',
      passwordChangeRequired: false,
      permissions: [],
    });

    expect(result.state).toBe('awaiting_pairing');
    expect(result.screen).toBe('pairing');
  });

  it('resolves paired when the device is authorized but the user is not signed in', () => {
    const result = resolveApplicationState({
      ...baseContext(),
      deviceAuthorizationState: 'paired',
      sessionState: 'missing',
      accountStatus: 'active',
      passwordChangeRequired: false,
      permissions: [],
    });

    expect(result.state).toBe('paired');
    expect(result.screen).toBe('login');
  });

  it('resolves session_expired before ready when the secure session is no longer active', () => {
    const result = resolveApplicationState({
      ...baseContext(),
      sessionState: 'expired',
    });

    expect(result.state).toBe('session_expired');
    expect(result.screen).toBe('session_expired');
  });

  it('resolves account_disabled when the account status is not active', () => {
    const result = resolveApplicationState({
      ...baseContext(),
      accountStatus: 'suspended',
    });

    expect(result.state).toBe('account_disabled');
    expect(result.screen).toBe('account_disabled');
  });

  it('resolves password_change_required when the session requires a password update', () => {
    const result = resolveApplicationState({
      ...baseContext(),
      passwordChangeRequired: true,
    });

    expect(result.state).toBe('password_change_required');
    expect(result.screen).toBe('password_change');
  });

  it('resolves subscription_expired when the Shop App reports an expired subscription', () => {
    const result = resolveApplicationState({
      ...baseContext(),
      shopStatus: {
        ...baseContext().shopStatus,
        subscriptionStatus: 'expired',
      },
    });

    expect(result.state).toBe('subscription_expired');
  });

  it('resolves module_disabled when the current role depends on disabled modules', () => {
    const result = resolveApplicationState({
      ...baseContext(),
      activeRole: 'cashier',
      shopStatus: {
        ...baseContext().shopStatus,
        enabledModules: ['inventory'],
        disabledModules: ['sales', 'cashier_shifts'],
      },
    });

    expect(result.state).toBe('module_disabled');
  });

  it('resolves update_required before ready when the client must update', () => {
    const result = resolveApplicationState({
      ...baseContext(),
      updateRequired: true,
    });

    expect(result.state).toBe('update_required');
  });

  it('resolves ready when connection, pairing, session, permissions, and modules are all valid', () => {
    const result = resolveApplicationState(baseContext());

    expect(result.state).toBe('ready');
    expect(result.screen).toBe('workspace');
  });
});