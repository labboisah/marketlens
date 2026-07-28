export const socketEvents = {
  serverStatus: 'server.status',
  deviceConnected: 'device.connected',
  deviceDisconnected: 'device.disconnected',
  userLoggedIn: 'user.logged_in',
  syncStatus: 'sync.status',
} as const;

export type ShopSocketEvent = (typeof socketEvents)[keyof typeof socketEvents];
