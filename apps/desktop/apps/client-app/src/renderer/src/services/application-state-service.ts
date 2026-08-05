import type { ShopSystemStatus } from '@marketlens/api-contracts';
import type { ClientRole, ConnectionState, DeviceAuthorizationState, SessionState } from '../state/client-ui-store';

export type ClientApplicationState =
  | 'not_configured'
  | 'searching_for_shop_server'
  | 'awaiting_pairing'
  | 'paired'
  | 'disconnected'
  | 'session_expired'
  | 'account_disabled'
  | 'password_change_required'
  | 'authenticated'
  | 'shop_suspended'
  | 'subscription_expired'
  | 'module_disabled'
  | 'update_required'
  | 'ready';

export interface ApplicationStateContext {
  shopBaseUrl: string;
  connectionState: ConnectionState;
  deviceAuthorizationState: DeviceAuthorizationState;
  sessionState: SessionState;
  accountStatus: 'active' | 'inactive' | 'suspended' | 'disabled';
  passwordChangeRequired: boolean;
  activeRole: ClientRole;
  permissions: string[];
  currentVersion: string;
  updateRequired: boolean;
  shopStatus?: ShopSystemStatus;
}

export interface ResolvedApplicationState {
  state: ClientApplicationState;
  screen: 'connection' | 'pairing' | 'login' | 'workspace' | 'session_expired' | 'account_disabled' | 'password_change';
  message: string;
  connected: boolean;
}

const roleModules: Record<ClientRole, string[]> = {
  cashier: ['sales', 'cashier_shifts'],
  storekeeper: ['inventory', 'purchases'],
  manager: ['sales', 'reporting'],
  accountant: ['reporting', 'customers', 'purchases'],
};

function routeForState(state: ClientApplicationState): ResolvedApplicationState['screen'] {
  if (state === 'not_configured' || state === 'searching_for_shop_server' || state === 'disconnected') {
    return 'connection';
  }

  if (state === 'awaiting_pairing') {
    return 'pairing';
  }

  if (state === 'paired') {
    return 'login';
  }

  if (state === 'session_expired') {
    return 'session_expired';
  }

  if (state === 'account_disabled') {
    return 'account_disabled';
  }

  if (state === 'password_change_required') {
    return 'password_change';
  }

  return 'workspace';
}

function messageForState(state: ClientApplicationState, context: ApplicationStateContext): string {
  switch (state) {
    case 'not_configured':
      return 'Enter the Shop App address before this workstation can connect.';
    case 'searching_for_shop_server':
      return 'Searching for the MarketLens Shop App on the local network.';
    case 'awaiting_pairing':
      return 'This workstation must be paired and authorized by the Shop App before sign-in.';
    case 'paired':
      return 'This workstation is paired. Sign in through the Shop App to continue.';
    case 'disconnected':
      return 'The Shop App is unreachable. Check LAN connectivity and the Shop App local API status.';
    case 'session_expired':
      return 'Your session expired. Sign in again to continue using this workstation.';
    case 'account_disabled':
      return 'This account is suspended or disabled and cannot access workstation operations.';
    case 'password_change_required':
      return 'Password change is required before this workstation can continue.';
    case 'authenticated':
      return 'Authentication succeeded. Final workstation checks are still being resolved.';
    case 'shop_suspended':
      return 'The connected Shop App is suspended and cannot accept operational workstations.';
    case 'subscription_expired':
      return 'The connected shop subscription is expired. Operational access is restricted.';
    case 'module_disabled':
      return `The ${context.activeRole} workstation is blocked because required Shop App modules are disabled.`;
    case 'update_required':
      return `Client App version ${context.currentVersion} requires an update before this workstation can continue.`;
    case 'ready':
      return 'The workstation is connected, authenticated, and ready for confirmed Shop App operations.';
  }
}

function hasRequiredModules(activeRole: ClientRole, shopStatus?: ShopSystemStatus): boolean {
  if (!shopStatus) {
    return true;
  }

  const requiredModules = roleModules[activeRole];
  if (requiredModules.length === 0) {
    return true;
  }

  return requiredModules.every((moduleKey) => shopStatus.enabledModules.includes(moduleKey));
}

export function resolveApplicationState(context: ApplicationStateContext): ResolvedApplicationState {
  let state: ClientApplicationState;

  if (!context.shopBaseUrl.trim()) {
    state = 'not_configured';
  } else if (context.connectionState === 'connecting') {
    state = 'searching_for_shop_server';
  } else if (context.connectionState === 'disconnected') {
    state = 'disconnected';
  } else if (context.shopStatus?.shopActivationStatus && context.shopStatus.shopActivationStatus !== 'active') {
    state = 'shop_suspended';
  } else if (context.shopStatus?.subscriptionStatus && ['expired', 'suspended', 'inactive', 'past_due'].includes(context.shopStatus.subscriptionStatus.toLowerCase())) {
    state = 'subscription_expired';
  } else if (context.updateRequired) {
    state = 'update_required';
  } else if (context.deviceAuthorizationState === 'unpaired' || context.deviceAuthorizationState === 'pending' || context.deviceAuthorizationState === 'unknown') {
    state = 'awaiting_pairing';
  } else if (context.accountStatus !== 'active') {
    state = 'account_disabled';
  } else if (context.passwordChangeRequired) {
    state = 'password_change_required';
  } else if (context.sessionState === 'expired') {
    state = 'session_expired';
  } else if (context.deviceAuthorizationState === 'paired' || context.deviceAuthorizationState === 'authorized') {
    if (context.sessionState !== 'active') {
      state = 'paired';
    } else if (!hasRequiredModules(context.activeRole, context.shopStatus)) {
      state = 'module_disabled';
    } else if (context.permissions.length === 0) {
      state = 'authenticated';
    } else {
      state = 'ready';
    }
  } else {
    state = 'awaiting_pairing';
  }

  return {
    state,
    screen: routeForState(state),
    message: messageForState(state, context),
    connected: context.connectionState === 'connected',
  };
}