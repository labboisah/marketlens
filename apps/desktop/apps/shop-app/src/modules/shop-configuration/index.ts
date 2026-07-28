import { createModuleShell } from '../module-shell';

export const shopConfigurationModule = createModuleShell({
  name: 'Shop Configuration',
  key: 'shop-configuration',
  status: 'scaffolded',
  ownsTransactions: false,
  auditable: true,
  synchronized: true,
  localOnly: false,
});
