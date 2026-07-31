<?php

namespace App\Livewire\Platform\Support;

use App\Domain\Devices\Models\Device;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\SupportTicketCategory;
use App\Domain\Support\Services\SupportCentreService;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BusinessContext;
use App\Enums\SupportTicketStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class SupportCentrePage extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = '';

    public ?int $selectedTicketId = null;

    public ?int $ticketCategoryId = null;

    public string $priority = 'normal';

    public string $subject = '';

    public string $description = '';

    public string $relatedBranchId = '';

    public string $relatedDeviceId = '';

    public string $relatedSyncBatchId = '';

    /** @var array<int, mixed> */
    public array $attachments = [];

    public string $replyBody = '';

    /** @var array<int, mixed> */
    public array $replyAttachments = [];

    public function mount(BusinessContext $businessContext, SupportCentreService $support): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('view', $business);

        $support->ensureDefaults();
        $this->ticketCategoryId = SupportTicketCategory::query()
            ->where('slug', request('category', 'general'))
            ->value('id') ?: SupportTicketCategory::query()->where('slug', 'general')->value('id');
        $this->relatedBranchId = filled(request('branch_id')) ? (string) request('branch_id') : '';
        $this->relatedDeviceId = filled(request('device_id')) ? (string) request('device_id') : '';
        $this->relatedSyncBatchId = filled(request('sync_batch_id')) ? (string) request('sync_batch_id') : '';
    }

    public function render(BusinessContext $businessContext, SupportCentreService $support): View
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('view', $business);

        $tickets = $support->tickets($business);
        $this->selectedTicketId ??= $tickets->first()?->id;

        return view('livewire.platform.support.support-centre-page', [
            'business' => $business,
            'knowledgeCategories' => $support->knowledgeCategories(),
            'articles' => $support->articles($this->search ?: null, $this->category ?: null),
            'faqs' => $support->faqs($this->search ?: null),
            'ticketCategories' => $support->ticketCategories(),
            'tickets' => $tickets,
            'selectedTicket' => $tickets->firstWhere('id', $this->selectedTicketId),
            'branches' => $business->branches()->orderBy('name')->get(),
            'devices' => Device::query()->where('business_id', $business->id)->with('branch')->orderBy('name')->get(),
            'syncBatches' => SyncBatch::query()->where('business_id', $business->id)->latest('received_at')->limit(30)->get(),
            'statuses' => SupportTicketStatus::cases(),
        ])->title('Support and help centre');
    }

    public function selectTicket(int $ticketId, BusinessContext $businessContext): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->selectedTicketId = SupportTicket::query()->where('business_id', $business->id)->whereKey($ticketId)->value('id');
    }

    public function submitTicket(BusinessContext $businessContext, SupportCentreService $support): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('view', $business);

        $validated = $this->validate($this->ticketRules($business));

        $ticket = $support->createTicket($business, auth()->user(), [
            'support_ticket_category_id' => (int) $validated['ticketCategoryId'],
            'branch_id' => filled($validated['relatedBranchId'] ?? null) ? (int) $validated['relatedBranchId'] : null,
            'device_id' => filled($validated['relatedDeviceId'] ?? null) ? (int) $validated['relatedDeviceId'] : null,
            'sync_batch_id' => filled($validated['relatedSyncBatchId'] ?? null) ? (int) $validated['relatedSyncBatchId'] : null,
            'subject' => $validated['subject'],
            'priority' => $validated['priority'],
            'description' => $validated['description'],
            'metadata' => ['source' => 'platform_support_centre'],
        ], $this->attachments);

        $this->reset(['subject', 'description', 'attachments']);
        $this->priority = 'normal';
        $this->selectedTicketId = $ticket->id;

        $this->dispatch('notify', tone: 'success', title: 'Support ticket submitted', message: 'Your ticket was created with its conversation history and secure attachments.');
    }

    public function reply(BusinessContext $businessContext, SupportCentreService $support): void
    {
        $business = $this->currentBusiness($businessContext);
        $validated = $this->validate([
            'selectedTicketId' => ['required', 'integer'],
            'replyBody' => ['required', 'string', 'min:3', 'max:5000'],
            'replyAttachments' => ['array', 'max:5'],
            'replyAttachments.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,txt,csv'],
        ]);

        $ticket = SupportTicket::query()
            ->where('business_id', $business->id)
            ->whereKey($validated['selectedTicketId'])
            ->firstOrFail();

        $support->addMessage($business, auth()->user(), $ticket, $validated['replyBody'], $this->replyAttachments);
        $this->reset(['replyBody', 'replyAttachments']);

        $this->dispatch('notify', tone: 'success', title: 'Reply added', message: 'The support ticket conversation was updated.');
    }

    public function updateTicketStatus(BusinessContext $businessContext, SupportCentreService $support, int $ticketId, string $status): void
    {
        $business = $this->currentBusiness($businessContext);
        $ticket = SupportTicket::query()->where('business_id', $business->id)->whereKey($ticketId)->firstOrFail();
        $statusEnum = SupportTicketStatus::tryFrom($status);

        if (! $statusEnum) {
            $this->addError('ticketStatus', 'Choose a valid ticket status.');

            return;
        }

        $support->updateStatus($business, auth()->user(), $ticket, $statusEnum);
        $this->dispatch('notify', tone: 'success', title: 'Ticket status updated', message: 'The support ticket status was saved.');
    }

    /** @return array<string, mixed> */
    private function ticketRules(Business $business): array
    {
        return [
            'ticketCategoryId' => ['required', 'integer', Rule::exists('support_ticket_categories', 'id')->where('is_active', true)],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'subject' => ['required', 'string', 'min:4', 'max:160'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'relatedBranchId' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('business_id', $business->id)],
            'relatedDeviceId' => ['nullable', 'integer', Rule::exists('devices', 'id')->where('business_id', $business->id)],
            'relatedSyncBatchId' => ['nullable', 'integer', Rule::exists('sync_batches', 'id')->where('business_id', $business->id)],
            'attachments' => ['array', 'max:5'],
            'attachments.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,txt,csv'],
        ];
    }

    private function currentBusiness(BusinessContext $businessContext): Business
    {
        return auth()->user()->accessibleBusinesses()
            ->where('id', $businessContext->id() ?: auth()->user()->accessibleBusinesses()->first()?->id)
            ->firstOrFail();
    }
}
