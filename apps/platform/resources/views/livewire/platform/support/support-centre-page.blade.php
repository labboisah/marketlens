<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Support and help centre"
    description="Search help articles, review frequently asked questions, and manage business support tickets."
  />

  <section class="grid gap-4 xl:grid-cols-[1fr_0.8fr]">
    <x-app.card title="Knowledge base" description="Search published guidance before opening a ticket.">
      <div class="grid gap-3 md:grid-cols-[1fr_220px]">
        <label class="block text-sm font-medium" for="support-search">
          Search help articles
          <input id="support-search" wire:model.live.debounce.300ms="search" class="ml-field mt-1 w-full" placeholder="Search devices, sync, billing, reports...">
        </label>
        <label class="block text-sm font-medium" for="support-category">
          Category
          <select id="support-category" wire:model.live="category" class="ml-field mt-1 w-full">
            <option value="">All categories</option>
            @foreach ($knowledgeCategories as $knowledgeCategory)
              <option value="{{ $knowledgeCategory->slug }}">{{ $knowledgeCategory->name }}</option>
            @endforeach
          </select>
        </label>
      </div>

      <div class="mt-4 grid gap-3 lg:grid-cols-2">
        @forelse ($articles as $article)
          <article class="rounded-md border border-border p-3">
            <div class="flex flex-wrap items-center gap-2">
              <p class="font-semibold">{{ $article->title }}</p>
              @if ($article->is_faq)
                <x-app.status-badge>FAQ</x-app.status-badge>
              @endif
            </div>
            <p class="mt-1 text-xs font-medium text-muted">{{ $article->category?->name }}</p>
            <p class="mt-2 text-sm text-muted">{{ $article->summary }}</p>
            <p class="mt-3 text-sm">{{ $article->body }}</p>
          </article>
        @empty
          <x-app.empty-state title="No help articles found" message="Try another search term or submit a support ticket." />
        @endforelse
      </div>
    </x-app.card>

    <x-app.card title="Submit support ticket" description="Attachments are validated and stored privately.">
      <form wire:submit="submitTicket" class="space-y-3">
        <div class="grid gap-3 sm:grid-cols-2">
          <label class="block text-sm font-medium" for="ticket-category">
            Ticket category
            <select id="ticket-category" wire:model="ticketCategoryId" class="ml-field mt-1 w-full">
              @foreach ($ticketCategories as $ticketCategory)
                <option value="{{ $ticketCategory->id }}">{{ $ticketCategory->name }}</option>
              @endforeach
            </select>
            @error('ticketCategoryId') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
          </label>
          <label class="block text-sm font-medium" for="ticket-priority">
            Priority
            <select id="ticket-priority" wire:model="priority" class="ml-field mt-1 w-full">
              <option value="low">Low</option>
              <option value="normal">Normal</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
            @error('priority') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
          </label>
        </div>

        <label class="block text-sm font-medium" for="ticket-subject">
          Subject
          <input id="ticket-subject" wire:model="subject" class="ml-field mt-1 w-full" placeholder="Short issue summary">
          @error('subject') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </label>

        <label class="block text-sm font-medium" for="ticket-description">
          Description
          <textarea id="ticket-description" wire:model="description" class="ml-field mt-1 w-full" rows="5" placeholder="Describe what happened and what you expected."></textarea>
          @error('description') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </label>

        <div class="grid gap-3 sm:grid-cols-3">
          <label class="block text-sm font-medium" for="related-branch">
            Related branch
            <select id="related-branch" wire:model="relatedBranchId" class="ml-field mt-1 w-full">
              <option value="">None</option>
              @foreach ($branches as $branch)
                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
              @endforeach
            </select>
            @error('relatedBranchId') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
          </label>
          <label class="block text-sm font-medium" for="related-device">
            Related Business Server device
            <select id="related-device" wire:model="relatedDeviceId" class="ml-field mt-1 w-full">
              <option value="">None</option>
              @foreach ($devices as $device)
                <option value="{{ $device->id }}">{{ $device->name }}{{ $device->branch ? ' - '.$device->branch->name : '' }}</option>
              @endforeach
            </select>
            @error('relatedDeviceId') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
          </label>
          <label class="block text-sm font-medium" for="related-sync-batch">
            Related synchronization batch
            <select id="related-sync-batch" wire:model="relatedSyncBatchId" class="ml-field mt-1 w-full">
              <option value="">None</option>
              @foreach ($syncBatches as $syncBatch)
                <option value="{{ $syncBatch->id }}">{{ str($syncBatch->uuid)->limit(12) }} - {{ ucfirst($syncBatch->status->value) }}</option>
              @endforeach
            </select>
            @error('relatedSyncBatchId') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
          </label>
        </div>

        <label class="block text-sm font-medium" for="ticket-attachments">
          Attachments
          <input id="ticket-attachments" type="file" wire:model="attachments" multiple class="ml-field mt-1 w-full" accept=".jpg,.jpeg,.png,.pdf,.txt,.csv">
          <span class="mt-1 block text-xs text-muted">Allowed: JPG, PNG, PDF, TXT, CSV. Maximum 5 MB each.</span>
          @error('attachments') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
          @error('attachments.*') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </label>

        <button type="submit" class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white">Submit ticket</button>
      </form>
    </x-app.card>
  </section>

  <section class="grid gap-4 xl:grid-cols-[0.8fr_1.2fr]">
    <x-app.card title="Ticket history" description="Business support tickets are scoped to the selected tenant.">
      @forelse ($tickets as $ticket)
        <button type="button" wire:click="selectTicket({{ $ticket->id }})" class="mb-3 block w-full rounded-md border p-3 text-left {{ $selectedTicket?->id === $ticket->id ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50' }}">
          <span class="flex flex-wrap items-center justify-between gap-2">
            <span class="font-semibold">{{ $ticket->subject }}</span>
            <x-app.status-badge tone="{{ in_array($ticket->status->value, ['resolved', 'closed'], true) ? 'success' : ($ticket->priority === 'urgent' ? 'danger' : 'warning') }}">{{ ucfirst($ticket->status->value) }}</x-app.status-badge>
          </span>
          <span class="mt-1 block font-mono text-xs text-muted">{{ $ticket->ticket_number }}</span>
          <span class="mt-1 block text-xs text-muted">{{ $ticket->category?->name }} - {{ ucfirst($ticket->priority) }} - {{ $ticket->last_message_at?->diffForHumans() ?? $ticket->created_at->diffForHumans() }}</span>
        </button>
      @empty
        <x-app.empty-state title="No support tickets" message="Submitted support tickets and conversation history will appear here." />
      @endforelse
    </x-app.card>

    <x-app.card title="Ticket conversation" description="Replies and attachment metadata stay linked to the ticket.">
      @if ($selectedTicket)
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="font-semibold">{{ $selectedTicket->subject }}</p>
            <p class="font-mono text-xs text-muted">{{ $selectedTicket->ticket_number }} / {{ $selectedTicket->uuid }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            @foreach ($statuses as $status)
              <button type="button" wire:click="updateTicketStatus({{ $selectedTicket->id }}, '{{ $status->value }}')" class="rounded-md border border-border px-2 py-1 text-xs font-medium {{ $selectedTicket->status === $status ? 'bg-primary text-white' : 'text-foreground hover:border-primary/50' }}">
                {{ ucfirst($status->value) }}
              </button>
            @endforeach
          </div>
        </div>

        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
          <div><dt class="text-muted">Branch</dt><dd>{{ $selectedTicket->branch?->name ?? 'None' }}</dd></div>
          <div><dt class="text-muted">Device</dt><dd>{{ $selectedTicket->device?->name ?? 'None' }}</dd></div>
          <div><dt class="text-muted">Sync batch</dt><dd class="font-mono">{{ $selectedTicket->syncBatch?->uuid ? str($selectedTicket->syncBatch->uuid)->limit(16) : 'None' }}</dd></div>
        </dl>

        <div class="mt-4 space-y-3">
          @foreach ($selectedTicket->messages as $message)
            <div class="rounded-md border border-border p-3">
              <div class="flex flex-wrap justify-between gap-2 text-xs text-muted">
                <span>{{ $message->user?->name ?? 'Support' }}</span>
                <span>{{ $message->created_at->format('M j, Y H:i') }}</span>
              </div>
              <p class="mt-2 whitespace-pre-line text-sm">{{ $message->body }}</p>
              @if ($message->attachments->isNotEmpty())
                <div class="mt-3 flex flex-wrap gap-2">
                  @foreach ($message->attachments as $attachment)
                    <span class="rounded-md border border-border px-2 py-1 text-xs text-muted">{{ $attachment->original_name }} / {{ number_format($attachment->size_bytes / 1024, 1) }} KB</span>
                  @endforeach
                </div>
              @endif
            </div>
          @endforeach
        </div>

        <form wire:submit="reply" class="mt-4 space-y-3">
          <label class="block text-sm font-medium" for="ticket-reply">
            Reply
            <textarea id="ticket-reply" wire:model="replyBody" class="ml-field mt-1 w-full" rows="4"></textarea>
            @error('replyBody') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
          </label>
          <label class="block text-sm font-medium" for="reply-attachments">
            Reply attachments
            <input id="reply-attachments" type="file" wire:model="replyAttachments" multiple class="ml-field mt-1 w-full" accept=".jpg,.jpeg,.png,.pdf,.txt,.csv">
            @error('replyAttachments.*') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
          </label>
          <button type="submit" class="rounded-md border border-primary/40 px-3 py-2 text-sm font-semibold text-primary">Add reply</button>
        </form>
      @else
        <x-app.empty-state title="No ticket selected" message="Choose a support ticket to review the conversation." />
      @endif
    </x-app.card>
  </section>

  <x-app.card title="Frequently asked questions" description="Published FAQ articles from the support knowledge base.">
    <div class="grid gap-3 lg:grid-cols-2">
      @forelse ($faqs as $faq)
        <article class="rounded-md border border-border p-3">
          <p class="font-semibold">{{ $faq->title }}</p>
          <p class="mt-2 text-sm text-muted">{{ $faq->body }}</p>
        </article>
      @empty
        <x-app.empty-state title="No FAQs found" message="FAQs matching your search will appear here." />
      @endforelse
    </div>
  </x-app.card>
</div>

