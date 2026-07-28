export const clientAppBoundary = {
  opensSqliteDirectly: false,
  writesThroughShopApi: true,
  discoversShopServer: true,
  rendererHasUnrestrictedNodeAccess: false,
} as const;

export type { ClientPreloadApi } from './preload-contract';
