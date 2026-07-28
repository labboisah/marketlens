export const shopAppBoundary = {
  ownsLocalDatabase: true,
  exposesLocalApi: true,
  acceptsOperationalWrites: true,
  rendererHasUnrestrictedNodeAccess: false,
  operatesWithoutInternet: true,
} as const;

export type { ShopPreloadApi } from './preload-contract';
export { shopModules } from './modules';
