import { createModuleShell } from '../module-shell';

export const devicesModule = createModuleShell({
  name: 'Devices',
  key: 'devices',
  status: 'scaffolded',
  ownsTransactions: false,
  auditable: true,
  synchronized: true,
  localOnly: false,
});
