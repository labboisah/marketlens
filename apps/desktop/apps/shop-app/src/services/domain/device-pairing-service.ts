import type { AuditLogRepository } from '../../repositories/audit-log-repository';
import type { DeviceRepository } from '../../repositories/device-repository';

export type PairDeviceInput = {
  deviceName: string;
  pairingCode: string;
};

export class DevicePairingService {
  constructor(
    private readonly devices: DeviceRepository,
    private readonly auditLogs: AuditLogRepository,
    private readonly expectedPairingCode: string,
  ) {}

  async pair(input: PairDeviceInput) {
    if (!input.pairingCode) {
      return { paired: false, reason: 'Pairing code is required.' };
    }

    if (input.pairingCode !== this.expectedPairingCode) {
      return { paired: false, reason: 'The pairing code is invalid.' };
    }

    const device = await this.devices.registerPlaceholderDevice({
      name: input.deviceName,
      kind: 'client_app',
    });

    await this.devices.approveDevice(device.uuid);

    this.auditLogs.record({
      action: 'device.paired',
      subjectType: 'device',
      subjectUuid: device.uuid,
      metadata: {
        name: input.deviceName,
        kind: 'client_app',
      },
    });

    return {
      paired: true,
      deviceUuid: device.uuid,
      reason: 'Device paired successfully.',
    };
  }
}
