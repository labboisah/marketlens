import { create } from 'zustand';

export type ClientScreen = 'connection' | 'pairing' | 'login' | 'workspace';
export type ConnectionState = 'connected' | 'connecting' | 'disconnected';
export type ClientRole = 'cashier' | 'storekeeper' | 'manager' | 'accountant';
export type NotificationTone = 'success' | 'warning' | 'danger' | 'information';

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
  activeRole: ClientRole;
  permissions: string[];
  deviceUuid?: string;
  notifications: ClientNotification[];
  setScreen(screen: ClientScreen): void;
  setConnectionState(state: ConnectionState): void;
  setShopBaseUrl(baseUrl: string): void;
  setActiveRole(role: ClientRole): void;
  setPermissions(permissions: string[]): void;
  setDeviceUuid(deviceUuid: string): void;
  notify(notification: Omit<ClientNotification, 'id'>): void;
  dismissNotification(id: string): void;
}

export const useClientUiStore = create<ClientUiState>((set) => ({
  screen: 'connection',
  connectionState: 'disconnected',
  shopBaseUrl: 'http://127.0.0.1:4090',
  activeRole: 'cashier',
  permissions: [],
  notifications: [],
  setScreen: (screen) => set({ screen }),
  setConnectionState: (connectionState) => set({ connectionState }),
  setShopBaseUrl: (shopBaseUrl) => set({ shopBaseUrl }),
  setActiveRole: (activeRole) => set({ activeRole }),
  setPermissions: (permissions) => set({ permissions }),
  setDeviceUuid: (deviceUuid) => set({ deviceUuid }),
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
