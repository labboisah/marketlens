export const shopAppBoundary = {
  ownsLocalDatabase: true,
  exposesLocalApi: true,
  acceptsOperationalWrites: true,
  rendererHasUnrestrictedNodeAccess: false,
} as const;

export type { ShopPreloadApi } from './preload-contract';
