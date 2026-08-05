import { create } from 'zustand';
import type { ClientRole as ContractClientRole, ShopAccountStatus, ShopSystemStatus, ShopUserSummary } from '@marketlens/api-contracts';
import type { ClientApplicationState } from '../services/application-state-service';

export type ClientScreen = 'connection' | 'pairing' | 'login' | 'workspace' | 'session_expired' | 'account_disabled' | 'password_change' | 'lock';
export type ConnectionState = 'connected' | 'connecting' | 'disconnected';
export type ClientRole = Extract<ContractClientRole, 'cashier' | 'storekeeper' | 'manager' | 'accountant'>;
export type NotificationTone = 'success' | 'warning' | 'danger' | 'information';
export type DeviceAuthorizationState = 'unpaired' | 'pending' | 'paired' | 'authorized' | 'revoked' | 'unknown';
export type SessionState = 'missing' | 'active' | 'expired';

export interface ClientNotification {
  id: string;
  tone: NotificationTone;
  title: string;
  message: string;
}

interface ClientUiState {
  screen: ClientScreen;
  connectionState: ConnectionState;
  shopBaseUrl: string;
  applicationState: ClientApplicationState;
  applicationStateMessage: string;
  activeRole: ClientRole;
  permissions: string[];
  roles: string[];
  deviceUuid?: string;
  username?: string;
  rememberDevice: boolean;
  accountStatus: ShopAccountStatus;
  passwordChangeRequired: boolean;
  deviceAuthorizationState: DeviceAuthorizationState;
  sessionState: SessionState;
  lockReason?: string;
  shopStatus?: ShopSystemStatus;
  lastSuccessfulConnectionAt?: string;
  currentVersion: string;
  updateRequired: boolean;
  currentUser?: ShopUserSummary;
  notifications: ClientNotification[];
  setScreen(screen: ClientScreen): void;
  setConnectionState(state: ConnectionState): void;
  setShopBaseUrl(baseUrl: string): void;
  setApplicationState(state: ClientApplicationState, message: string): void;
  setActiveRole(role: ClientRole): void;
  setPermissions(permissions: string[]): void;
  setRoles(roles: string[]): void;
  setUsername(username?: string): void;
  setRememberDevice(value: boolean): void;
  setAccountStatus(status: ShopAccountStatus): void;
  setPasswordChangeRequired(required: boolean): void;
  setDeviceUuid(deviceUuid: string): void;
  setDeviceAuthorizationState(state: DeviceAuthorizationState): void;
  setSessionState(state: SessionState): void;
  setLock(reason?: string): void;
  clearLock(): void;
  setShopStatus(status?: ShopSystemStatus): void;
  setLastSuccessfulConnectionAt(value?: string): void;
  setCurrentVersion(version: string): void;
  setUpdateRequired(required: boolean): void;
  setCurrentUser(user?: ShopUserSummary): void;
  bootstrap(input: {
    shopBaseUrl: string;
    activeRole: ClientRole;
    screen: ClientScreen;
    applicationState?: ClientApplicationState;
    applicationStateMessage?: string;
    permissions?: string[];
    roles?: string[];
    username?: string;
    rememberDevice?: boolean;
    accountStatus?: ShopAccountStatus;
    passwordChangeRequired?: boolean;
    deviceUuid?: string;
    deviceAuthorizationState?: DeviceAuthorizationState;
    sessionState?: SessionState;
    shopStatus?: ShopSystemStatus;
    lastSuccessfulConnectionAt?: string;
    currentVersion?: string;
    updateRequired?: boolean;
    currentUser?: ShopUserSummary;
    connectionState?: ConnectionState;
  }): void;
  resetSession(): void;
  notify(notification: Omit<ClientNotification, 'id'>): void;
  dismissNotification(id: string): void;
}

export const useClientUiStore = create<ClientUiState>((set) => ({
  screen: 'connection',
  connectionState: 'disconnected',
  shopBaseUrl: 'http://127.0.0.1:4090',
  applicationState: 'not_configured',
  applicationStateMessage: 'Connect this workstation to a MarketLens Shop App.',
  activeRole: 'cashier',
  permissions: [],
  roles: [],
  username: undefined,
  rememberDevice: false,
  accountStatus: 'active',
  passwordChangeRequired: false,
  deviceAuthorizationState: 'unpaired',
  sessionState: 'missing',
  lockReason: undefined,
  lastSuccessfulConnectionAt: undefined,
  currentVersion: '0.0.0',
  updateRequired: false,
  notifications: [],
  setScreen: (screen) => set({ screen }),
  setConnectionState: (connectionState) => set({ connectionState }),
  setShopBaseUrl: (shopBaseUrl) => set({ shopBaseUrl }),
  setApplicationState: (applicationState, applicationStateMessage) => set({ applicationState, applicationStateMessage }),
  setActiveRole: (activeRole) => set({ activeRole }),
  setPermissions: (permissions) => set({ permissions }),
  setRoles: (roles) => set({ roles }),
  setUsername: (username) => set({ username }),
  setRememberDevice: (rememberDevice) => set({ rememberDevice }),
  setAccountStatus: (accountStatus) => set({ accountStatus }),
  setPasswordChangeRequired: (passwordChangeRequired) => set({ passwordChangeRequired }),
  setDeviceUuid: (deviceUuid) => set({ deviceUuid }),
  setDeviceAuthorizationState: (deviceAuthorizationState) => set({ deviceAuthorizationState }),
  setSessionState: (sessionState) => set({ sessionState }),
  setLock: (lockReason) => set({ lockReason, screen: 'lock' }),
  clearLock: () => set({ lockReason: undefined, screen: 'workspace' }),
  setShopStatus: (shopStatus) => set({ shopStatus }),
  setLastSuccessfulConnectionAt: (lastSuccessfulConnectionAt) => set({ lastSuccessfulConnectionAt }),
  setCurrentVersion: (currentVersion) => set({ currentVersion }),
  setUpdateRequired: (updateRequired) => set({ updateRequired }),
  setCurrentUser: (currentUser) => set({ currentUser }),
  bootstrap: (input) =>
    set({
      shopBaseUrl: input.shopBaseUrl,
      activeRole: input.activeRole,
      screen: input.screen,
      applicationState: input.applicationState ?? 'not_configured',
      applicationStateMessage: input.applicationStateMessage ?? 'Connect this workstation to a MarketLens Shop App.',
      permissions: input.permissions ?? [],
      roles: input.roles ?? [],
      username: input.username,
      rememberDevice: input.rememberDevice ?? false,
      accountStatus: input.accountStatus ?? 'active',
      passwordChangeRequired: input.passwordChangeRequired ?? false,
      deviceUuid: input.deviceUuid,
      deviceAuthorizationState: input.deviceAuthorizationState ?? 'unpaired',
      sessionState: input.sessionState ?? 'missing',
      shopStatus: input.shopStatus,
      lastSuccessfulConnectionAt: input.lastSuccessfulConnectionAt,
      currentVersion: input.currentVersion ?? '0.0.0',
      updateRequired: input.updateRequired ?? false,
      currentUser: input.currentUser,
      connectionState: input.connectionState ?? 'disconnected',
    }),
  resetSession: () =>
    set({
      screen: 'login',
      applicationState: 'session_expired',
      applicationStateMessage: 'Your session expired. Sign in again to continue using this workstation.',
      permissions: [],
      roles: [],
      username: undefined,
      passwordChangeRequired: false,
      sessionState: 'expired',
      currentUser: undefined,
      lockReason: undefined,
    }),
  notify: (notification) =>
    set((state) => ({
      notifications: [
        ...state.notifications,
        {
          ...notification,
          id: crypto.randomUUID(),
        },
      ],
    })),
  dismissNotification: (id) =>
    set((state) => ({
      notifications: state.notifications.filter((notification) => notification.id !== id),
    })),
}));
