import { createModuleShell } from '../module-shell';

export const unitsModule = createModuleShell({
  name: 'Units',
  key: 'units',
  status: 'scaffolded',
  ownsTransactions: false,
  auditable: true,
  synchronized: true,
  localOnly: false,
});
