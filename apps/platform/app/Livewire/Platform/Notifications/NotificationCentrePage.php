<?php

namespace App\Livewire\Platform\Notifications;

use App\Domain\Notifications\Models\AlertNotificationPreference;
use App\Domain\Notifications\Models\PlatformNotification;
use App\Domain\Notifications\Services\AlertNotificationService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BusinessContext;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class NotificationCentrePage extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $state = 'open';

    #[Url]
    public string $severity = 'all';

    #[Url]
    public string $category = 'all';

    #[Url]
    public string $branch = 'all';

    public ?int $resolvingNotificationId = null;

    public string $resolutionNote = '';

    /**
     * @var array<string, bool>
     */
    public array $preferences = [];

    public function mount(BusinessContext $businessContext): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->preferences = $this->preferencesFor($business);
    }

    public function render(BusinessContext $businessContext, AlertNotificationService $alerts): View
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('view', $business);

        $notifications = PlatformNotification::query()
            ->with(['branch'])
            ->where('business_id', $business->id)
            ->when($this->state === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($this->state === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->when($this->state === 'resolved', fn ($query) => $query->whereNotNull('resolved_at'))
            ->when($this->state === 'open', fn ($query) => $query->whereNull('resolved_at'))
            ->when($this->severity !== 'all', fn ($query) => $query->where('severity', $this->severity))
            ->when($this->category !== 'all', fn ($query) => $query->where('category', $this->category))
            ->when($this->branch !== 'all', fn ($query) => $query->where('branch_id', (int) $this->branch))
            ->latest()
            ->paginate(15);

        return view('livewire.platform.notifications.notification-centre-page', [
            'business' => $business,
            'branches' => Branch::query()->where('business_id', $business->id)->orderBy('name')->get(),
            'notifications' => $notifications,
            'counts' => $alerts->counts($business->id),
            'categories' => $alerts->categories(),
            'channels' => $alerts->channels(),
        ])->title('Notifications and alerts');
    }

    public function updatedState(): void
    {
        $this->resetPage();
    }

    public function updatedSeverity(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedBranch(): void
    {
        $this->resetPage();
    }

    public function markRead(int $notificationId, BusinessContext $businessContext, AlertNotificationService $alerts): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('view', $business);

        $alerts->markRead($business->id, $notificationId);
        $this->dispatch('notify', tone: 'success', title: 'Notification read', message: 'The alert was marked as read.');
    }

    public function markUnread(int $notificationId, BusinessContext $businessContext, AlertNotificationService $alerts): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('view', $business);

        $alerts->markUnread($business->id, $notificationId);
        $this->dispatch('notify', tone: 'information', title: 'Notification unread', message: 'The alert was marked as unread.');
    }

    public function startResolve(int $notificationId): void
    {
        $this->resolvingNotificationId = $notificationId;
        $this->resolutionNote = '';
    }

    public function resolveNotification(BusinessContext $businessContext, AlertNotificationService $alerts): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manage', $business);

        $this->validate(['resolutionNote' => ['required', 'string', 'min:3', 'max:500']]);
        $alerts->resolve($business->id, (int) $this->resolvingNotificationId, auth()->user(), $this->resolutionNote);

        $this->resolvingNotificationId = null;
        $this->resolutionNote = '';
        $this->dispatch('notify', tone: 'success', title: 'Alert resolved', message: 'The resolution note was saved.');
    }

    public function savePreferences(BusinessContext $businessContext, AlertNotificationService $alerts): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manage', $business);

        foreach ($this->preferences as $key => $enabled) {
            [$category, $channel] = explode('|', $key);
            $alerts->setPreference($business, auth()->user(), $category, $channel, (bool) $enabled);
        }

        $this->dispatch('notify', tone: 'success', title: 'Preferences saved', message: 'Notification delivery preferences were updated.');
    }

    private function currentBusiness(BusinessContext $businessContext): Business
    {
        return auth()->user()->ownedBusinesses()
            ->with(['branches'])
            ->whereKey($businessContext->id() ?: auth()->user()->ownedBusinesses()->value('businesses.id'))
            ->firstOrFail();
    }

    /**
     * @return array<string, bool>
     */
    private function preferencesFor(Business $business): array
    {
        $stored = AlertNotificationPreference::query()
            ->where('business_id', $business->id)
            ->where('user_id', auth()->id())
            ->get()
            ->keyBy(fn (AlertNotificationPreference $preference): string => $preference->category.'|'.$preference->channel);

        $preferences = [];

        foreach (collect(config('alerts.types'))->pluck('category')->unique() as $category) {
            foreach (collect(config('alerts.channels'))->keys() as $channel) {
                $preferences[$category.'|'.$channel] = $stored->get($category.'|'.$channel)?->enabled ?? (bool) config('alerts.channels.'.$channel);
            }
        }

        return $preferences;
    }
}
