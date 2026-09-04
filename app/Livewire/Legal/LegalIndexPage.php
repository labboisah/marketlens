<?php

namespace App\Livewire\Legal;

use App\Domain\Legal\Services\LegalDocumentService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
class LegalIndexPage extends Component
{
    public function render(LegalDocumentService $legal): View
    {
        return view('livewire.legal.legal-index-page', [
            'documents' => $legal->publishedDocuments(),
        ])->title('Legal Centre | MarketLens');
    }
}
