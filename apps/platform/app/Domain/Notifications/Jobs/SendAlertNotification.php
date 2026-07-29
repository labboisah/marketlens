<?php

namespace App\Domain\Notifications\Jobs;

use App\Domain\Notifications\Models\PlatformNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendAlertNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $notificationId,
        public int $userId,
        public string $channel,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $notification = PlatformNotification::query()->find($this->notificationId);

        if (! $notification) {
            return;
        }

        Log::info('MarketLens alert notification queued for external delivery.', [
            'notification_id' => $notification->id,
            'user_id' => $this->userId,
            'channel' => $this->channel,
            'type' => $notification->type,
            'severity' => $notification->severity,
        ]);

        $delivered = collect($notification->delivered_channels ?? [])
            ->push($this->channel)
            ->unique()
            ->values()
            ->all();

        $notification->forceFill(['delivered_channels' => $delivered])->save();
    }
}
