/// <reference types="vite/client" />

import type { ClientPreloadApi } from '../../preload/types';

declare global {
  interface Window {
    marketlensClient: ClientPreloadApi;
  }
}

export {};
