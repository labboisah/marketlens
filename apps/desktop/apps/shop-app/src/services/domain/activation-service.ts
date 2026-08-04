import type { AuditLogRepository } from '../../repositories/audit-log-repository';
import type { InstallationRepository, AppInstallationRecord } from '../../repositories/installation-repository';
import type { PlatformCredentialsRepository } from '../../repositories/platform-credentials-repository';
import type { ShopEnvironment } from '../../shared/environment';
import { encryptSecret } from '../security/secret-encryption';

export type ActivationInput = {
  activationCode: string;
  deviceUuid: string;
  deviceName: string;
  operatingSystem: string;
  appVersion: string;
  deviceFingerprint: string;
};

export type ActivationStatus =
  | 'not_initialized'
  | 'awaiting_activation'
  | 'activated'
  | 'suspended'
  | 'revoked'
  | 'subscription_expired'
  | 'update_required'
  | 'ready';

export type ActivationResult = {
  success: boolean;
  status: ActivationStatus;
  reason?: string;
  activationData?: {
    businessUuid: string;
    branchUuid: string;
    businessName: string;
    branchName: string;
    subscriptionState: string;
    planFeatures: string[];
    enabledModules: string[];
    branchSettings: Record<string, unknown>;
    syncProtocolVersion: string;
  };
};

export type InternalActivationResult = ActivationResult & {
  deviceCredential?: string;
};

export class ActivationService {
  constructor(
    private readonly installations: InstallationRepository,
    private readonly credentials: PlatformCredentialsRepository,
    private readonly auditLogs: AuditLogRepository,
    private readonly environment: ShopEnvironment,
  ) {}

  async completeActivation(input: ActivationInput): Promise<ActivationResult> {
    const installation = this.installations.findByDeviceUuid(input.deviceUuid);
    if (!installation) {
      return { success: false, status: 'not_initialized', reason: 'Installation not found.' };
    }

    if (installation.activationState === 'activated') {
      return { success: false, status: 'activated', reason: 'Already activated.' };
    }

    const platformResponse = await this.performPlatformActivation(input);
    if (!platformResponse.success) {
      return { success: false, status: 'awaiting_activation', reason: platformResponse.reason };
    }

    const updatedInstallation = this.installations.updateActivation(installation, {
      activationState: 'activated',
      subscriptionState: platformResponse.subscriptionState,
      businessUuid: platformResponse.businessUuid,
      branchUuid: platformResponse.branchUuid,
      businessName: platformResponse.businessName,
      branchName: platformResponse.branchName,
      planFeaturesJson: JSON.stringify(platformResponse.planFeatures),
      enabledModulesJson: JSON.stringify(platformResponse.enabledModules),
      branchSettingsJson: JSON.stringify(platformResponse.branchSettings),
      syncProtocolVersion: platformResponse.syncProtocolVersion,
      activatedAt: new Date().toISOString(),
      installationState: 'ready',
    });

    if (platformResponse.deviceCredential) {
      this.credentials.storeCredentials(updatedInstallation.uuid, encryptSecret(platformResponse.deviceCredential, this.environment.SHOP_JWT_SECRET));
    }

    this.auditLogs.record({
      action: 'activation.completed',
      subjectType: 'app_installation',
      subjectUuid: updatedInstallation.uuid,
      metadata: {
        businessUuid: platformResponse.businessUuid,
        branchUuid: platformResponse.branchUuid,
      },
    });

    return {
      success: true,
      status: 'activated',
      activationData: {
        businessUuid: platformResponse.businessUuid,
        branchUuid: platformResponse.branchUuid,
        businessName: platformResponse.businessName,
        branchName: platformResponse.branchName,
        subscriptionState: platformResponse.subscriptionState,
        planFeatures: platformResponse.planFeatures,
        enabledModules: platformResponse.enabledModules,
        branchSettings: platformResponse.branchSettings,
        syncProtocolVersion: platformResponse.syncProtocolVersion,
      },
    };
  }

  getStatus(deviceUuid?: string): ActivationStatus {
    const installation =
      typeof deviceUuid === 'string' && deviceUuid.length > 0
        ? this.installations.findByDeviceUuid(deviceUuid)
        : this.installations.findCurrent();

    return installation?.activationState ?? 'not_initialized';
  }

  revokePlatformCredentials(installationUuid: string, actorUserUuid?: string): void {
    this.credentials.revokeCredentials(installationUuid);
    this.auditLogs.record({ actorUserUuid, action: 'platform.credentials_revoked', subjectType: 'app_installation', subjectUuid: installationUuid });
  }

  async testConnection(platformBaseUrl: string): Promise<{ success: boolean; reason?: string }> {
    try {
      const response = await fetch(`${platformBaseUrl}/api/v1/activation/ping`, {
        method: 'GET',
        headers: { Accept: 'application/json' },
      });

      if (!response.ok) {
        return { success: false, reason: `Platform connection failed with ${response.status}` };
      }

      return { success: true };
    } catch (error) {
      return { success: false, reason: 'Could not reach Platform.' };
    }
  }

  private async performPlatformActivation(input: ActivationInput): Promise<InternalActivationResult> {
    // Placeholder implementation: should call the platform activation endpoint.
    return {
      success: true,
      status: 'activated',
      businessUuid: '00000000-0000-0000-0000-000000000001',
      branchUuid: '00000000-0000-0000-0000-000000000001',
      businessName: 'Demo Business',
      branchName: 'Main Branch',
      subscriptionState: 'active',
      planFeatures: ['sales', 'inventory'],
      enabledModules: ['sales', 'inventory', 'purchases'],
      branchSettings: {},
      deviceCredential: 'secure-device-token-placeholder',
      syncProtocolVersion: '1.0',
      reason: undefined,
    };
  }
}
