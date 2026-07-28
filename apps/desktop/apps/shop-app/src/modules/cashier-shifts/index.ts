import { createModuleShell } from '../module-shell';

export const cashierShiftsModule = createModuleShell({
  name: 'Cashier Shifts',
  key: 'cashier-shifts',
  status: 'scaffolded',
  ownsTransactions: true,
  auditable: true,
  synchronized: true,
  localOnly: false,
});
