export const socketEvents = {
  serverStatus: 'server.status',
  deviceConnected: 'device.connected',
  deviceDisconnected: 'device.disconnected',
  userLoggedIn: 'user.logged_in',
  userLoggedOut: 'user.logged_out',
  devicePairingRequested: 'device.pairing_requested',
  deviceAuthorized: 'device.authorized',
  deviceRevoked: 'device.revoked',
  purchaseCreated: 'purchase.created',
  purchaseReceived: 'purchase.received',
  purchasePaymentRecorded: 'purchase.payment_recorded',
  syncStatus: 'sync.status',
} as const;

export type ShopSocketEvent = (typeof socketEvents)[keyof typeof socketEvents];
