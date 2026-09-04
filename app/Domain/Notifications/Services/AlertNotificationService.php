<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Notifications\Jobs\SendAlertNotification;
use App\Domain\Notifications\Models\AlertNotificationPreference;
use App\Domain\Notifications\Models\PlatformNotification;
use App\Domain\Tenancy\Models\Business;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class AlertNotificationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Business $business, array $data): PlatformNotification
    {
        $definition = config('alerts.types.'.($data['type'] ?? ''), []);
        $category = (string) ($data['category'] ?? $definition['category'] ?? 'general');
        $severity = (string) ($data['severity'] ?? $definition['severity'] ?? 'information');
        $dedupeKey = $data['dedupe_key'] ?? $this->dedupeKey($business->id, $data, $category);

        $existing = PlatformNotification::query()
            ->where('business_id', $business->id)
            ->where('dedupe_key', $dedupeKey)
            ->whereNull('resolved_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($business, $data, $category, $severity, $dedupeKey): PlatformNotification {
            $channels = $this->enabledChannels($business, $category);

            $notification = PlatformNotification::query()->create([
                'business_id' => $business->id,
                'branch_id' => $data['branch_id'] ?? null,
                'type' => $data['type'],
                'severity' => $severity,
                'category' => $category,
                'status' => 'open',
                'title' => $data['title'],
                'body' => $data['body'] ?? null,
                'related_record_type' => $data['related_record_type'] ?? null,
                'related_record_uuid' => $data['related_record_uuid'] ?? null,
                'dedupe_key' => $dedupeKey,
                'channels' => $channels,
                'delivered_channels' => in_array('in_app', $channels, true) ? ['in_app'] : [],
            ]);

            $this->queueExternalDeliveries($business, $notification, $channels);
            $this->audit(null, $business->id, $notification->branch_id, 'alert.created', $notification, [
                'type' => $notification->type,
                'severity' => $notification->severity,
                'category' => $notification->category,
            ]);

            return $notification;
        });
    }

    public function markRead(int $businessId, int $notificationId): void
    {
        $this->notification($businessId, $notificationId)
            ->forceFill(['read_at' => now()])
            ->save();
    }

    public function markUnread(int $businessId, int $notificationId): void
    {
        $this->notification($businessId, $notificationId)
            ->forceFill(['read_at' => null])
            ->save();
    }

    public function resolve(int $businessId, int $notificationId, User $actor, string $note): void
    {
        $notification = $this->notification($businessId, $notificationId);

        $notification->forceFill([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_note' => $note,
            'resolved_by' => $actor->id,
        ])->save();

        $this->audit($actor, $businessId, $notification->branch_id, 'alert.resolved', $notification, [
            'resolution_note' => $note,
        ]);
    }

    public function unreadCount(int $businessId): int
    {
        return PlatformNotification::query()
            ->where('business_id', $businessId)
            ->whereNull('read_at')
            ->count();
    }

    public function setPreference(Business $business, User $user, string $category, string $channel, bool $enabled): AlertNotificationPreference
    {
        return AlertNotificationPreference::query()->updateOrCreate(
            [
                'business_id' => $business->id,
                'user_id' => $user->id,
                'category' => $category,
                'channel' => $channel,
            ],
            ['enabled' => $enabled],
        );
    }

    /**
     * @return list<string>
     */
    public function enabledChannels(Business $business, string $category): array
    {
        $defaults = collect(config('alerts.channels'))
            ->filter()
            ->keys()
            ->all();

        $ownerIds = $business->owners()->pluck('users.id');
        $disabled = AlertNotificationPreference::query()
            ->where('business_id', $business->id)
            ->whereIn('user_id', $ownerIds)
            ->where('category', $category)
            ->where('enabled', false)
            ->pluck('channel')
            ->all();

        return collect($defaults)
            ->reject(fn (string $channel): bool => in_array($channel, $disabled, true))
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function counts(int $businessId): array
    {
        return [
            'unread' => $this->unreadCount($businessId),
            'open' => PlatformNotification::query()->where('business_id', $businessId)->whereNull('resolved_at')->count(),
            'resolved' => PlatformNotification::query()->where('business_id', $businessId)->whereNotNull('resolved_at')->count(),
            'danger' => PlatformNotification::query()->where('business_id', $businessId)->where('severity', 'danger')->whereNull('resolved_at')->count(),
            'warning' => PlatformNotification::query()->where('business_id', $businessId)->where('severity', 'warning')->whereNull('resolved_at')->count(),
        ];
    }

    public function categories(): Collection
    {
        return collect(config('alerts.types'))->pluck('category')->unique()->sort()->values();
    }

    public function channels(): Collection
    {
        return collect(config('alerts.channels'))->keys();
    }

    private function queueExternalDeliveries(Business $business, PlatformNotification $notification, array $channels): void
    {
        $externalChannels = collect($channels)->reject(fn (string $channel): bool => $channel === 'in_app');

        if ($externalChannels->isEmpty()) {
            return;
        }

        $business->owners()->get()->each(function (User $owner) use ($externalChannels, $notification): void {
            $externalChannels->each(fn (string $channel) => Queue::push(new SendAlertNotification($notification->id, $owner->id, $channel)));
        });
    }

    private function notification(int $businessId, int $notificationId): PlatformNotification
    {
        return PlatformNotification::query()
            ->where('business_id', $businessId)
            ->findOrFail($notificationId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function dedupeKey(int $businessId, array $data, string $category): string
    {
        return implode(':', [
            $businessId,
            $data['branch_id'] ?? 'all',
            $category,
            $data['type'] ?? 'alert',
            $data['related_record_type'] ?? 'record',
            $data['related_record_uuid'] ?? str($data['title'] ?? 'alert')->slug()->toString(),
        ]);
    }

    private function audit(?User $actor, int $businessId, ?int $branchId, string $action, PlatformNotification $notification, array $metadata = []): void
    {
        AuditLog::query()->create([
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => PlatformNotification::class,
            'subject_id' => $notification->id,
            'metadata' => $metadata,
        ]);
    }
}
