import type { DeviceRepository } from '../../repositories/device-repository';

export type PairDeviceInput = {
  deviceName: string;
  pairingCode: string;
};

export class DevicePairingService {
  constructor(private readonly devices: DeviceRepository) {}

  async pair(input: PairDeviceInput) {
    if (!input.pairingCode) {
      return { paired: false, reason: 'Pairing code is required.' };
    }

    const device = await this.devices.registerPlaceholderDevice({
      name: input.deviceName,
      kind: 'client_app',
    });

    return {
      paired: false,
      reason: 'Device approval workflow is not implemented yet.',
      deviceUuid: device.uuid,
    };
  }
}
