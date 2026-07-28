import type { BranchScoped, Uuid } from '@marketlens/shared-types';

export const syncContractVersion = 'v1';

export type SyncEnvelope = BranchScoped & {
  batchId: Uuid;
  records: readonly unknown[];
};
