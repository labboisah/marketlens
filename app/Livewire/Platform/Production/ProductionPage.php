<?php

declare(strict_types=1);

namespace App\Livewire\Platform\Production;

use App\Domain\Production\ProductionMonitoring;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BranchContext;
use App\Domain\Tenancy\Services\BusinessContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class ProductionPage extends Component
{
    public const SECTIONS = [
        'all' => ['Production', null],
        'machines' => ['Machines and utilization', ['machine']],
        'items' => ['Items and consumption', ['material']],
        'batches' => ['Production history', ['batch', 'loss']],
        'performance' => ['Product performance', ['batch', 'sale', 'return']],
        'costs' => ['Production costs', ['batch', 'expense', 'allocation']],
        'consumption' => ['Items consumption', ['batch']],
        'expense-analysis' => ['Expense analysis', ['expense']],
        'machine-utilization' => ['Machine utilization', ['machine', 'batch']],
        'store' => ['Store and stock levels', ['batch', 'transfer']],
        'expenses' => ['Production expenses', ['expense']],
        'benefits' => ['Benefit analysis', ['sale', 'allocation', 'settlement']],
        'sharing-rules' => ['Product sharing rules', ['rule']],
        'staff' => ['Staff participation and benefits', ['staff', 'allocation']],
        'machine-benefits' => ['Machine benefits', ['allocation']],
        'business-benefits' => ['Business benefits', ['allocation']],
        'sync' => ['Production synchronization', null],
    ];

    #[Url]
    public string $section = 'all';

    public function render(BusinessContext $businessContext, BranchContext $branchContext, ProductionMonitoring $monitoring): View
    {
        $business = Business::query()->findOrFail($businessContext->id());

        abort_unless(isset(self::SECTIONS[$this->section]), 422);
        $data = $monitoring->data(auth()->user(), $business, $branchContext->id());
        abort_if(in_array($this->section, ['expenses', 'benefits'], true) && ! $data['finance'], 403);
        [$title, $kinds] = self::SECTIONS[$this->section];
        if ($kinds !== null) {
            $data['records'] = $data['records']->whereIn('kind', $kinds);
        }
        if ($this->section === 'store') {
            $data['records'] = $data['records']->filter(fn ($row) => $row['kind'] === 'transfer' || ($row['data']['state'] ?? '') === 'completed');
        }

        return view('livewire.platform.production.production-page', [
            'data' => $data,
            'sectionTitle' => $title,
            'currency' => $business->default_currency,
        ])->title('Production');
    }
}
