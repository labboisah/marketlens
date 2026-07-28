import { createModuleShell } from '../module-shell';

export const auditLogsModule = createModuleShell({
  name: 'Audit Logs',
  key: 'audit-logs',
  status: 'scaffolded',
  ownsTransactions: false,
  auditable: false,
  synchronized: true,
  localOnly: false,
});
