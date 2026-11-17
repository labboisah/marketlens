<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Licensing\Models\BusinessLicenseAssociation;
use App\Domain\Licensing\Services\KernelBridgeLicenseSyncService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class KernelBridgeWebhookController extends Controller
{
    public function __invoke(Request $request, KernelBridgeLicenseSyncService $licenses): JsonResponse
    {
        $secret = (string) config('kernelbridge.webhook_secret');

        if ($secret === '') {
            return $this->respond('webhook_not_configured', 503);
        }

        $body = $request->getContent();

        if (! $this->validSignature((string) $request->header('X-KernelBridge-Signature'), $body, $secret)) {
            return $this->respond('invalid_signature', 401);
        }

        $event = json_decode($body, true);
        $eventId = $event['id'] ?? null;
        $subscriptionUuid = $event['data']['subscription_uuid'] ?? null;

        if (! is_string($eventId) || ! is_string($subscriptionUuid)) {
            return $this->respond('invalid_event', 422);
        }

        $dedupeKey = 'kernelbridge-webhook:'.$eventId;

        if (! Cache::add($dedupeKey, true, now()->addDay())) {
            return $this->respond('duplicate_event', 200);
        }

        $license = BusinessLicenseAssociation::query()
            ->with('business')
            ->where('provider', 'kernelbridge')
            ->where('subscription_uuid', $subscriptionUuid)
            ->first();

        if (! $license?->business) {
            return $this->respond('unknown_subscription', 202);
        }

        try {
            $licenses->refreshEntitlements($license->business);
        } catch (Throwable $exception) {
            // Release the dedupe key so KernelBridge's retry can reprocess the event.
            Cache::forget($dedupeKey);
            Log::warning('KernelBridge webhook refresh failed.', ['event_id' => $eventId, 'error' => $exception->getMessage()]);

            return $this->respond('refresh_failed', 502);
        }

        return $this->respond('processed', 200);
    }

    private function validSignature(string $header, string $body, string $secret): bool
    {
        if (! preg_match('/^t=(\d+),v1=([a-f0-9]{64})$/', $header, $match)) {
            return false;
        }

        if (abs(time() - (int) $match[1]) > (int) config('kernelbridge.webhook_tolerance_seconds', 300)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $match[1].'.'.$body, $secret), $match[2]);
    }

    private function respond(string $code, int $status): JsonResponse
    {
        return response()->json(['success' => $status < 400, 'code' => $code], $status);
    }
}
