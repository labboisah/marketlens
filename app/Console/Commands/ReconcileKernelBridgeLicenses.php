<?php

namespace App\Console\Commands;

use App\Domain\Licensing\Models\BusinessLicenseAssociation;
use App\Domain\Licensing\Services\KernelBridgeLicenseSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

final class ReconcileKernelBridgeLicenses extends Command
{
    protected $signature = 'kernelbridge:reconcile-licenses';

    protected $description = 'Refresh every linked KernelBridge license so dropped webhooks cannot leave stale projections.';

    public function handle(KernelBridgeLicenseSyncService $licenses): int
    {
        $refreshed = 0;
        $failed = 0;

        BusinessLicenseAssociation::query()
            ->with('business')
            ->where('provider', 'kernelbridge')
            ->whereNotNull('subscription_uuid')
            ->each(function (BusinessLicenseAssociation $license) use ($licenses, &$refreshed, &$failed): void {
                if (! $license->business) {
                    return;
                }

                try {
                    $licenses->refreshEntitlements($license->business);
                    $refreshed++;
                } catch (Throwable $exception) {
                    $failed++;
                    $license->forceFill(['provider_metadata' => [
                        ...(array) $license->provider_metadata,
                        'last_reconcile_error' => Str::limit($exception->getMessage(), 200),
                        'last_reconcile_error_at' => now()->toIso8601String(),
                    ]])->save();
                }
            });

        $this->info("Refreshed {$refreshed} license(s); {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
