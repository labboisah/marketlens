import { auditLogsModule } from './audit-logs';
import { authenticationModule } from './authentication';
import { backupModule } from './backup';
import { branchesModule } from './branches';
import { cashierShiftsModule } from './cashier-shifts';
import { customersModule } from './customers';
import { devicesModule } from './devices';
import { expensesModule } from './expenses';
import { inventoryModule } from './inventory';
import { permissionsModule } from './permissions';
import { productsModule } from './products';
import { purchasesModule } from './purchases';
import { rolesModule } from './roles';
import { salesModule } from './sales';
import { shopConfigurationModule } from './shop-configuration';
import { suppliersModule } from './suppliers';
import { synchronizationModule } from './synchronization';
import { systemHealthModule } from './system-health';
import { unitsModule } from './units';
import { usersModule } from './users';
import { warehousesModule } from './warehouses';

export const shopModules = [
  shopConfigurationModule,
  branchesModule,
  warehousesModule,
  usersModule,
  rolesModule,
  permissionsModule,
  devicesModule,
  authenticationModule,
  productsModule,
  unitsModule,
  inventoryModule,
  salesModule,
  purchasesModule,
  customersModule,
  suppliersModule,
  expensesModule,
  cashierShiftsModule,
  auditLogsModule,
  synchronizationModule,
  backupModule,
  systemHealthModule,
] as const;

export type ShopModule = (typeof shopModules)[number];
