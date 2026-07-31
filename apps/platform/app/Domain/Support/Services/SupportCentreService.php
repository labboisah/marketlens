<?php

namespace App\Domain\Support\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Devices\Models\Device;
use App\Domain\Support\Models\SupportArticle;
use App\Domain\Support\Models\SupportKnowledgeCategory;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\SupportTicketCategory;
use App\Domain\Support\Models\SupportTicketMessage;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\SupportTicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportCentreService
{
    /** @return EloquentCollection<int, SupportKnowledgeCategory> */
    public function knowledgeCategories(): EloquentCollection
    {
        $this->ensureDefaults();

        return SupportKnowledgeCategory::query()
            ->where('is_active', true)
            ->with(['articles' => fn ($query) => $query->where('is_published', true)->orderBy('sort_order')->orderBy('title')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return EloquentCollection<int, SupportTicketCategory> */
    public function ticketCategories(): EloquentCollection
    {
        $this->ensureDefaults();

        return SupportTicketCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return EloquentCollection<int, SupportArticle> */
    public function articles(?string $search = null, ?string $categorySlug = null): EloquentCollection
    {
        $this->ensureDefaults();

        return SupportArticle::query()
            ->with('category')
            ->where('is_published', true)
            ->when($categorySlug, fn ($query) => $query->whereHas('category', fn ($category) => $category->where('slug', $categorySlug)))
            ->when($search, function ($query) use ($search): void {
                $term = '%'.strtolower($search).'%';
                $query->where(function ($nested) use ($term): void {
                    $nested->whereRaw('lower(title) like ?', [$term])
                        ->orWhereRaw('lower(summary) like ?', [$term])
                        ->orWhereRaw('lower(body) like ?', [$term]);
                });
            })
            ->orderBy('sort_order')
            ->orderBy('title')
            ->limit(25)
            ->get();
    }

    /** @return EloquentCollection<int, SupportArticle> */
    public function faqs(?string $search = null): EloquentCollection
    {
        return $this->articles($search)->where('is_faq', true)->values();
    }

    /** @return EloquentCollection<int, SupportTicket> */
    public function tickets(Business $business): EloquentCollection
    {
        return SupportTicket::query()
            ->with(['category', 'branch', 'device', 'syncBatch', 'messages.user', 'attachments'])
            ->where('business_id', $business->id)
            ->latest('last_message_at')
            ->latest()
            ->get();
    }

    /** @param array<int, mixed> $attachments */
    public function createTicket(Business $business, User $actor, array $data, array $attachments = []): SupportTicket
    {
        $this->validateRelatedEntities($business, $data);

        return DB::transaction(function () use ($business, $actor, $data, $attachments): SupportTicket {
            $messageBody = trim((string) ($data['description'] ?? ''));
            $ticket = SupportTicket::query()->create([
                'business_id' => $business->id,
                'support_ticket_category_id' => $data['support_ticket_category_id'],
                'created_by' => $actor->id,
                'branch_id' => $data['branch_id'] ?? null,
                'device_id' => $data['device_id'] ?? null,
                'sync_batch_id' => $data['sync_batch_id'] ?? null,
                'ticket_number' => $this->nextTicketNumber($business),
                'subject' => $data['subject'],
                'priority' => $data['priority'],
                'status' => SupportTicketStatus::Open,
                'description' => $messageBody,
                'last_message_at' => now(),
                'metadata' => $data['metadata'] ?? null,
            ]);

            $message = $ticket->messages()->create([
                'user_id' => $actor->id,
                'body' => $messageBody,
            ]);

            $this->storeAttachments($ticket, $message, $actor, $attachments);
            $this->audit($business, $actor, 'support.ticket_created', $ticket, ['priority' => $ticket->priority]);

            return $ticket->load(['category', 'messages.user', 'attachments']);
        });
    }

    /** @param array<int, mixed> $attachments */
    public function addMessage(Business $business, User $actor, SupportTicket $ticket, string $body, array $attachments = []): SupportTicketMessage
    {
        if ((int) $ticket->business_id !== (int) $business->id) {
            throw ValidationException::withMessages(['ticket' => 'The selected support ticket does not belong to this business.']);
        }

        return DB::transaction(function () use ($business, $actor, $ticket, $body, $attachments): SupportTicketMessage {
            $message = $ticket->messages()->create([
                'user_id' => $actor->id,
                'body' => trim($body),
            ]);

            $ticket->forceFill([
                'status' => SupportTicketStatus::Pending,
                'last_message_at' => now(),
                'closed_at' => null,
            ])->save();

            $this->storeAttachments($ticket, $message, $actor, $attachments);
            $this->audit($business, $actor, 'support.ticket_replied', $ticket);

            return $message->load('attachments');
        });
    }

    public function updateStatus(Business $business, User $actor, SupportTicket $ticket, SupportTicketStatus $status): void
    {
        if ((int) $ticket->business_id !== (int) $business->id) {
            throw ValidationException::withMessages(['ticket' => 'The selected support ticket does not belong to this business.']);
        }

        $ticket->forceFill([
            'status' => $status,
            'closed_at' => in_array($status, [SupportTicketStatus::Resolved, SupportTicketStatus::Closed], true) ? now() : null,
        ])->save();

        $this->audit($business, $actor, 'support.ticket_status_changed', $ticket, ['status' => $status->value]);
    }

    public function ensureDefaults(): void
    {
        foreach ($this->defaultKnowledgeCategories() as $index => $category) {
            SupportKnowledgeCategory::query()->updateOrCreate(
                ['slug' => $category['slug']],
                ['name' => $category['name'], 'description' => $category['description'], 'sort_order' => $index + 1, 'is_active' => true]
            );
        }

        foreach ($this->defaultTicketCategories() as $index => $category) {
            SupportTicketCategory::query()->updateOrCreate(
                ['slug' => $category['slug']],
                ['name' => $category['name'], 'description' => $category['description'], 'sort_order' => $index + 1, 'is_active' => true]
            );
        }

        foreach ($this->defaultArticles() as $index => $article) {
            $category = SupportKnowledgeCategory::query()->where('slug', $article['category'])->first();
            if (! $category) {
                continue;
            }

            SupportArticle::query()->updateOrCreate(
                ['slug' => $article['slug']],
                [
                    'support_knowledge_category_id' => $category->id,
                    'title' => $article['title'],
                    'summary' => $article['summary'],
                    'body' => $article['body'],
                    'is_faq' => $article['is_faq'],
                    'is_published' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }

    private function validateRelatedEntities(Business $business, array $data): void
    {
        if (! empty($data['branch_id']) && ! Branch::query()->where('business_id', $business->id)->whereKey($data['branch_id'])->exists()) {
            throw ValidationException::withMessages(['relatedBranchId' => 'The selected branch does not belong to this business.']);
        }

        if (! empty($data['device_id']) && ! Device::query()->where('business_id', $business->id)->whereKey($data['device_id'])->exists()) {
            throw ValidationException::withMessages(['relatedDeviceId' => 'The selected Shop App device does not belong to this business.']);
        }

        if (! empty($data['sync_batch_id']) && ! SyncBatch::query()->where('business_id', $business->id)->whereKey($data['sync_batch_id'])->exists()) {
            throw ValidationException::withMessages(['relatedSyncBatchId' => 'The selected synchronization batch does not belong to this business.']);
        }

        if (! SupportTicketCategory::query()->where('is_active', true)->whereKey($data['support_ticket_category_id'] ?? null)->exists()) {
            throw ValidationException::withMessages(['ticketCategoryId' => 'Choose a valid support category.']);
        }
    }

    /** @param array<int, mixed> $attachments */
    private function storeAttachments(SupportTicket $ticket, SupportTicketMessage $message, User $actor, array $attachments): void
    {
        foreach ($attachments as $file) {
            if (! $file) {
                continue;
            }

            $extension = $file->getClientOriginalExtension() ?: 'bin';
            $path = $file->storeAs(
                'support-ticket-attachments/'.$ticket->business_id.'/'.$ticket->uuid,
                (string) Str::uuid().'.'.$extension,
                'local'
            );
            $fullPath = Storage::disk('local')->path($path);

            $ticket->attachments()->create([
                'support_ticket_message_id' => $message->id,
                'uploaded_by' => $actor->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $file->getSize() ?: 0,
                'checksum' => is_file($fullPath) ? hash_file('sha256', $fullPath) : null,
            ]);
        }
    }

    private function nextTicketNumber(Business $business): string
    {
        $prefix = 'ML-'.$business->id.'-'.now()->format('Ymd').'-';
        $count = SupportTicket::query()
            ->where('business_id', $business->id)
            ->where('ticket_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->count() + 1;

        return $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    /** @param array<string, mixed> $metadata */
    private function audit(Business $business, User $actor, string $action, SupportTicket $ticket, array $metadata = []): void
    {
        AuditLog::query()->create([
            'business_id' => $business->id,
            'branch_id' => $ticket->branch_id,
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => SupportTicket::class,
            'subject_id' => $ticket->id,
            'metadata' => $metadata,
        ]);
    }

    /** @return array<int, array{name: string, slug: string, description: string}> */
    private function defaultKnowledgeCategories(): array
    {
        return [
            ['name' => 'Getting started', 'slug' => 'getting-started', 'description' => 'Account setup, branches, and first synchronization guidance.'],
            ['name' => 'Devices', 'slug' => 'devices', 'description' => 'Shop App activation, device limits, updates, and revocation.'],
            ['name' => 'Synchronization', 'slug' => 'synchronization', 'description' => 'Batch health, failed records, conflicts, and retry guidance.'],
            ['name' => 'Billing and subscription', 'slug' => 'billing', 'description' => 'Plans, usage limits, renewals, and billing-profile support.'],
            ['name' => 'Reports and exports', 'slug' => 'reports', 'description' => 'Report filters, export queues, secure links, and history.'],
        ];
    }

    /** @return array<int, array{name: string, slug: string, description: string}> */
    private function defaultTicketCategories(): array
    {
        return [
            ['name' => 'General support', 'slug' => 'general', 'description' => 'General product questions and account help.'],
            ['name' => 'Device issue', 'slug' => 'device', 'description' => 'Shop App activation, status, update, or revocation issues.'],
            ['name' => 'Synchronization issue', 'slug' => 'synchronization', 'description' => 'Failed batches, conflicts, delayed devices, or sync health.'],
            ['name' => 'Billing or subscription', 'slug' => 'billing', 'description' => 'Plan, renewal, cancellation, and payment-history help.'],
            ['name' => 'Reports or exports', 'slug' => 'reports', 'description' => 'Report filters, scheduled reports, CSV/PDF exports, and download links.'],
            ['name' => 'Account and access', 'slug' => 'access', 'description' => 'Monitoring users, sessions, security settings, and business settings.'],
        ];
    }

    /** @return array<int, array{category: string, slug: string, title: string, summary: string, body: string, is_faq: bool}> */
    private function defaultArticles(): array
    {
        return [
            ['category' => 'getting-started', 'slug' => 'choose-business-context', 'title' => 'Choosing the right business and branch context', 'summary' => 'Use the Platform context switcher before reviewing data or opening a ticket.', 'body' => 'Business and branch context controls what synchronized data appears across dashboards, reports, devices, and support tickets. Confirm the selected branch before raising a branch-specific issue.', 'is_faq' => false],
            ['category' => 'devices', 'slug' => 'shop-app-device-activation', 'title' => 'How Shop App device activation works', 'summary' => 'Activation codes are short-lived and credentials are only shown to the Shop App once.', 'body' => 'Generate a device activation from the Platform, enter it in the Shop App, and confirm the device appears as active. Revoke unused activations and compromised devices from the device page.', 'is_faq' => true],
            ['category' => 'synchronization', 'slug' => 'failed-sync-batch-triage', 'title' => 'What to send when a sync batch fails', 'summary' => 'Attach the redacted error report and select the related synchronization batch.', 'body' => 'Open Synchronization, review failed records, download the redacted error report, then create a support ticket from that page so the batch context is attached securely.', 'is_faq' => true],
            ['category' => 'billing', 'slug' => 'subscription-expiration-data-retention', 'title' => 'What happens when a subscription expires?', 'summary' => 'Premium features can be restricted without immediately deleting synchronized data.', 'body' => 'MarketLens keeps safe account and billing access available. Synchronized data is preserved according to the configured retention policy while plan limits and premium features are enforced.', 'is_faq' => true],
            ['category' => 'reports', 'slug' => 'large-export-queues', 'title' => 'Why large exports are queued', 'summary' => 'Large CSV and PDF exports run through queues and produce secure expiring links.', 'body' => 'Queued exports protect dashboard performance and keep large report files behind signed download links. Check report history after queue processing completes.', 'is_faq' => false],
        ];
    }
}
