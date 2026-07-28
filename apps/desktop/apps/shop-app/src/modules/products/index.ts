import { createModuleShell } from '../module-shell';

export const productsModule = createModuleShell({
  name: 'Products',
  key: 'products',
  status: 'scaffolded',
  ownsTransactions: false,
  auditable: true,
  synchronized: true,
  localOnly: false,
});
