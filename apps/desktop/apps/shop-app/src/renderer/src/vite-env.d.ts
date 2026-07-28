/// <reference types="vite/client" />

import type { ShopPreloadApi } from '../../preload/types';

declare global {
  interface Window {
    marketlensShop: ShopPreloadApi;
  }
}

export {};
