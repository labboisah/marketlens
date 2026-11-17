<?php

namespace App\Livewire\Platform\Branches;

use App\Domain\Modules\Services\ModuleAccessService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BranchContext;
use App\Domain\Tenancy\Services\BranchManagement;
use App\Domain\Tenancy\Services\BusinessContext;
use App\Enums\BranchModuleStatus;
use App\Enums\BranchStatus;
use App\Enums\BranchOperatingMode;
use App\Enums\BusinessModule;
use App\Enums\SettlementFrequency;
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

    /** @var array<int, string> */
    public array $operatingModes = ['sales'];
    /** @var array<string, mixed> */
    public array $moduleSettings = [];

    public function mount(BranchContext $branchContext): void
    {
        $this->selectedBranchId = $branchContext->id();
        $this->moduleSettings = $this->defaultModuleSettings();
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
            'moduleAccess' => $selectedBranch ? app(ModuleAccessService::class)->resolveAll($business, $selectedBranch) : [],
            'businessModuleAccess' => app(ModuleAccessService::class)->resolveAll($business),
            'businessModules' => BusinessModule::cases(),
            'branchModuleStatuses' => BranchModuleStatus::cases(),
            'settlementFrequencies' => SettlementFrequency::cases(),
            'operatingModeOptions' => BranchOperatingMode::cases(),
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
        $this->dispatch('notify', tone: 'success', title: 'Branch created', message: 'The branch is ready for cloud-side monitoring and device setup.');
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
        $this->operatingModes = $branch->operating_modes ?? ['sales'];
        $this->moduleSettings = $this->defaultModuleSettings($branch);
    }

    public function updateBranch(BranchManagement $branchManagement): void
    {
        $branch = Branch::query()->findOrFail($this->editingBranchId);
        $this->authorize('manage', $branch);

        $branchManagement->update($branch, auth()->user(), $this->validatedData($branch));

        $this->selectedBranchId = $branch->id;
        $this->resetForm();
        session()->flash('branch_status', 'Branch updated successfully.');
        $this->dispatch('notify', tone: 'success', title: 'Branch updated', message: 'The branch details were saved successfully.');
    }

    public function activateBranch(int $branchId, BranchManagement $branchManagement): void
    {
        $branch = Branch::query()->findOrFail($branchId);
        $this->authorize('manage', $branch);
        $branchManagement->activate($branch, auth()->user());
        $this->dispatch('notify', tone: 'success', title: 'Branch activated', message: 'Business Server device activation can continue for this branch.');
    }

    public function suspendBranch(int $branchId, BranchManagement $branchManagement): void
    {
        $branch = Branch::query()->findOrFail($branchId);
        $this->authorize('manage', $branch);
        $branchManagement->suspend($branch, auth()->user());
        $this->dispatch('notify', tone: 'warning', title: 'Branch suspended', message: 'This branch cannot activate new Business Server devices while suspended.');
    }

    public function designateHeadOffice(int $branchId, BranchManagement $branchManagement): void
    {
        $branch = Branch::query()->findOrFail($branchId);
        $this->authorize('manage', $branch);
        $branchManagement->designateHeadOffice($branch, auth()->user());
        $this->dispatch('notify', tone: 'success', title: 'Head office updated', message: 'The selected branch is now the head office.');
    }

    public function archiveBranch(int $branchId, BranchManagement $branchManagement): void
    {
        $branch = Branch::query()->findOrFail($branchId);
        $this->authorize('manage', $branch);
        $branchManagement->archive($branch, auth()->user());
        $this->dispatch('notify', tone: 'danger', title: 'Branch archived', message: 'The branch is hidden from active operations, but synchronized history is preserved.');
    }


    public function saveModuleSettings(BranchManagement $branchManagement): void
    {
        $branch = Branch::query()->findOrFail($this->selectedBranchId);
        $this->authorize('manage', $branch);

        $validated = $this->validate([
            'moduleSettings.services.status' => ['required', Rule::enum(BranchModuleStatus::class)],
            'moduleSettings.services.default_branch_rule' => ['nullable', 'string', 'max:160'],
            'moduleSettings.services.require_manager_approval' => ['boolean'],
            'moduleSettings.services.settlement_frequency' => ['required', Rule::enum(SettlementFrequency::class)],
            'moduleSettings.services.branch_service_pricing' => ['boolean'],
            'moduleSettings.shareholders.status' => ['required', Rule::enum(BranchModuleStatus::class)],
            'moduleSettings.shareholders.default_branch_rule' => ['nullable', 'string', 'max:160'],
            'moduleSettings.shareholders.require_manager_approval' => ['boolean'],
            'moduleSettings.shareholders.settlement_frequency' => ['required', Rule::enum(SettlementFrequency::class)],
            'moduleSettings.shareholders.branch_service_pricing' => ['boolean'],
        ]);

        $branchManagement->updateModuleSettings($branch, auth()->user(), $validated['moduleSettings']);
        $this->moduleSettings = $this->defaultModuleSettings($branch->refresh());

        $this->dispatch('notify', tone: 'success', title: 'Branch module settings saved', message: 'Branch module inheritance and overrides were updated.');
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
            'operatingModes' => ['required', 'array', 'min:1'],
            'operatingModes.*' => ['required', 'string', 'distinct', Rule::enum(BranchOperatingMode::class)],
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
            'operating_modes' => array_values($validated['operatingModes']),
        ];
    }


    /** @return array<string, mixed> */
    private function defaultModuleSettings(?Branch $branch = null): array
    {
        return array_replace_recursive([
            'production' => ['status' => BranchModuleStatus::Inherit->value, 'default_branch_rule' => '', 'require_manager_approval' => true, 'settlement_frequency' => SettlementFrequency::Monthly->value, 'branch_service_pricing' => false],
            'services' => [
                'status' => BranchModuleStatus::Inherit->value,
                'default_branch_rule' => '',
                'require_manager_approval' => false,
                'settlement_frequency' => SettlementFrequency::Monthly->value,
                'branch_service_pricing' => false,
            ],
            'shareholders' => [
                'status' => BranchModuleStatus::Inherit->value,
                'default_branch_rule' => '',
                'require_manager_approval' => false,
                'settlement_frequency' => SettlementFrequency::Monthly->value,
                'branch_service_pricing' => false,
            ],
        ], $branch?->module_settings ?? []);
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
        $this->operatingModes = ['sales'];
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
