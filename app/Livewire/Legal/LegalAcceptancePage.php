<?php

namespace App\Livewire\Legal;

use App\Domain\Legal\Services\LegalDocumentService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class LegalAcceptancePage extends Component
{
    /** @var array<int, bool> */
    public array $acceptedVersions = [];

    public function render(LegalDocumentService $legal): View
    {
        $requiredVersions = $legal->requiredVersionsFor(auth()->user());

        foreach ($requiredVersions as $version) {
            $this->acceptedVersions[$version->id] ??= false;
        }

        return view('livewire.legal.legal-acceptance-page', [
            'requiredVersions' => $requiredVersions,
            'documents' => $legal->publishedDocuments(),
        ])->title('Legal acceptance required');
    }

    public function accept(LegalDocumentService $legal): void
    {
        $requiredVersions = $legal->requiredVersionsFor(auth()->user());
        $acceptedIds = collect($this->acceptedVersions)
            ->filter()
            ->keys()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($requiredVersions->pluck('id')->sort()->values()->all() !== collect($acceptedIds)->sort()->values()->all()) {
            throw ValidationException::withMessages([
                'acceptedVersions' => 'Accept every required legal document version before continuing.',
            ]);
        }

        $legal->acceptVersions(auth()->user(), $acceptedIds, request());

        $this->dispatch('notify', tone: 'success', title: 'Legal documents accepted', message: 'Your acceptance was recorded.');
        $this->redirect(route('platform.dashboard'), navigate: true);
    }
}

