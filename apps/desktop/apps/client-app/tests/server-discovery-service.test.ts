import { describe, expect, it } from 'vitest';
import { isBusinessChange, requiresPinnedIdentityViolation } from '../src/renderer/src/services/server-discovery-service';

describe('server discovery safety guards', () => {
  it('detects pinned identity mismatches when enforcement is enabled', () => {
    const violation = requiresPinnedIdentityViolation(
      {
        currentServerUrl: 'http://192.168.1.20:4090',
        recentServers: [],
        requirePinnedServerMatch: true,
        pinnedServerIdentity: {
          businessUuid: '00000000-0000-4000-8000-000000000001',
          branchUuid: '00000000-0000-4000-8000-000000000010',
        },
      },
      {
        applicationVersion: '1.0.0',
        databaseVersion: '3.45.0',
        businessUuid: '00000000-0000-4000-8000-000000000999',
        businessName: 'Other Business',
        branchUuid: '00000000-0000-4000-8000-000000000010',
        branchName: 'Main Branch',
        shopDeviceUuid: '00000000-0000-4000-8000-000000000099',
        shopDeviceName: 'Shop Device',
        shopServerName: 'Shop Device',
        serverIp: '192.168.1.50',
        serverPort: 4090,
        serverStatus: 'healthy',
        shopActivationStatus: 'active',
        subscriptionStatus: 'active',
        platformConnectivity: 'online',
        lastSynchronization: null,
        pendingSyncRecordCount: 0,
        authorizedClientAppCount: 1,
        enabledModules: ['sales'],
        disabledModules: [],
      },
    );

    expect(violation).toBe(true);
  });

  it('flags cross-business server switches', () => {
    const changed = isBusinessChange(
      {
        businessUuid: '00000000-0000-4000-8000-000000000001',
      },
      {
        applicationVersion: '1.0.0',
        databaseVersion: '3.45.0',
        businessUuid: '00000000-0000-4000-8000-000000000002',
        businessName: 'Business B',
        branchUuid: '00000000-0000-4000-8000-000000000020',
        branchName: 'Second Branch',
        shopDeviceUuid: '00000000-0000-4000-8000-000000000055',
        shopDeviceName: 'Second Shop Device',
        shopServerName: 'Second Shop Device',
        serverIp: '192.168.1.30',
        serverPort: 4090,
        serverStatus: 'healthy',
        shopActivationStatus: 'active',
        subscriptionStatus: 'active',
        platformConnectivity: 'online',
        lastSynchronization: null,
        pendingSyncRecordCount: 0,
        authorizedClientAppCount: 1,
        enabledModules: ['sales'],
        disabledModules: [],
      },
    );

    expect(changed).toBe(true);
  });
});