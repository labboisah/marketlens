<?php

namespace App\Livewire\Platform\Branches;

use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BranchContext;
use App\Domain\Tenancy\Services\BranchManagement;
use App\Domain\Tenancy\Services\BusinessContext;
use App\Enums\BranchStatus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class BranchManagementPage extends Component
{
    use AuthorizesRequests;

    public ?int $editingBranchId = null;

    public ?int $selectedBranchId = null;

    public string $code = '';

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $state = '';

    public string $localGovernmentArea = '';

    public bool $isHeadOffice = false;

    public function mount(BranchContext $branchContext): void
    {
        $this->selectedBranchId = $branchContext->id();
    }

    public function render(BusinessContext $businessContext, BranchManagement $branchManagement)
    {
        $business = $this->currentBusiness($businessContext);

        $this->authorize('manage', $business);

        $branches = $business->branches()
            ->withCount('devices')
            ->orderByDesc('is_head_office')
            ->orderBy('name')
            ->get();

        $selectedBranch = $branches->firstWhere('id', $this->selectedBranchId) ?? $branches->first();

        return view('livewire.platform.branches.branch-management-page', [
            'business' => $business,
            'branches' => $branches,
            'selectedBranch' => $selectedBranch,
            'maxBranches' => $branchManagement->maxBranches($business),
            'activeBranchCount' => $branches->where('status', '!=', BranchStatus::Archived)->count(),
        ]);
    }

    public function selectBranch(int $branchId): void
    {
        $branch = Branch::query()->findOrFail($branchId);
        $this->authorize('view', $branch);
        $this->selectedBranchId = $branch->id;
    }

    public function createBranch(BusinessContext $businessContext, BranchManagement $branchManagement): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manage', $business);

        $branch = $branchManagement->create($business, auth()->user(), $this->validatedData());

        $this->selectedBranchId = $branch->id;
        $this->resetForm();
        session()->flash('branch_status', 'Branch created successfully.');
    }

    public function editBranch(int $branchId): void
    {
        $branch = Branch::query()->findOrFail($branchId);
        $this->authorize('manage', $branch);

        $this->editingBranchId = $branch->id;
        $this->selectedBranchId = $branch->id;
        $this->code = (string) $branch->code;
        $this->name = $branch->name;
        $this->phone = (string) $branch->phone;
        $this->email = (string) $branch->email;
        $this->address = (string) $branch->address;
        $this->state = (string) $branch->state;
        $this->localGovernmentArea = (string) $branch->local_government_area;
        $this->isHeadOffice = (bool) $branch->is_head_office;
    }

    public function updateBranch(BranchManagement $branchManagement): void
    {
        $branch = Branch::query()->findOrFail($this->editingBranchId);
        $this->authorize('manage', $branch);

        $branchManagement->update($branch, auth()->user(), $this->validatedData($branch));

        $this->selectedBranchId = $branch->id;
        $this->resetForm();
        session()->flash('branch_status', 'Branch updated successfully.');
    }

    public function activateBranch(int $branchId, BranchManagement $branchManagement): void
    {
        $branch = Branch::query()->findOrFail($branchId);
        $this->authorize('manage', $branch);
        $branchManagement->activate($branch, auth()->user());
    }

    public function suspendBranch(int $branchId, BranchManagement $branchManagement): void
    {
        $branch = Branch::query()->findOrFail($branchId);
        $this->authorize('manage', $branch);
        $branchManagement->suspend($branch, auth()->user());
    }

    public function designateHeadOffice(int $branchId, BranchManagement $branchManagement): void
    {
        $branch = Branch::query()->findOrFail($branchId);
        $this->authorize('manage', $branch);
        $branchManagement->designateHeadOffice($branch, auth()->user());
    }

    public function archiveBranch(int $branchId, BranchManagement $branchManagement): void
    {
        $branch = Branch::query()->findOrFail($branchId);
        $this->authorize('manage', $branch);
        $branchManagement->archive($branch, auth()->user());
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(?Branch $branch = null): array
    {
        $validated = $this->validate([
            'code' => [
                'nullable',
                'string',
                'max:40',
                Rule::unique('branches', 'code')
                    ->where('business_id', $branch?->business_id ?? app(BusinessContext::class)->id())
                    ->ignore($branch?->id),
            ],
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:500'],
            'state' => ['nullable', 'string', 'max:120'],
            'localGovernmentArea' => ['nullable', 'string', 'max:120'],
            'isHeadOffice' => ['boolean'],
        ]);

        return [
            'code' => filled($validated['code']) ? strtoupper($validated['code']) : null,
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?: null,
            'email' => $validated['email'] ?: null,
            'address' => $validated['address'] ?: null,
            'state' => $validated['state'] ?: null,
            'local_government_area' => $validated['localGovernmentArea'] ?: null,
            'is_head_office' => (bool) $validated['isHeadOffice'],
        ];
    }

    private function resetForm(): void
    {
        $this->editingBranchId = null;
        $this->code = '';
        $this->name = '';
        $this->phone = '';
        $this->email = '';
        $this->address = '';
        $this->state = '';
        $this->localGovernmentArea = '';
        $this->isHeadOffice = false;
        $this->resetValidation();
    }

    private function currentBusiness(BusinessContext $businessContext): Business
    {
        return Business::query()
            ->with(['currentSubscription.plan.features'])
            ->whereKey($businessContext->id() ?: auth()->user()->ownedBusinesses()->value('businesses.id'))
            ->firstOrFail();
    }
}
