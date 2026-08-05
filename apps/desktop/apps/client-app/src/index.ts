export const clientAppBoundary = {
  opensSqliteDirectly: false,
  ownsBusinessDatabase: false,
  writesThroughShopApi: true,
  discoversShopServer: true,
  storesSensitiveSessionWithSafeStorage: true,
  rendererHasUnrestrictedNodeAccess: false,
} as const;

export type { ClientPreloadApi } from './preload-contract';
export { shellNavigationItems, startingRolePresets } from './renderer/src/features/navigation/application-shell-navigation';
