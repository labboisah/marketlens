<?php

namespace App\Livewire\Legal;

use App\Domain\Legal\Models\LegalDocument;
use App\Domain\Legal\Services\LegalDocumentService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
class LegalDocumentPage extends Component
{
    public string $slug;

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    public function render(LegalDocumentService $legal): View
    {
        $document = $legal->publishedDocument($this->slug);

        abort_unless($document instanceof LegalDocument, 404);

        return view('livewire.legal.legal-document-page', [
            'document' => $document,
            'version' => $document->publishedVersion,
            'documents' => $legal->publishedDocuments(),
        ])->title($document->title.' | MarketLens Legal');
    }
}
