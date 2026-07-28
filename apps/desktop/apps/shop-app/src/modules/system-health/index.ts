import { createModuleShell } from '../module-shell';

export const systemHealthModule = createModuleShell({
  name: 'System Health',
  key: 'system-health',
  status: 'scaffolded',
  ownsTransactions: false,
  auditable: false,
  synchronized: false,
  localOnly: true,
});
