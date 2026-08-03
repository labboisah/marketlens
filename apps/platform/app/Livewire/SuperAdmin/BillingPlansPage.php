<?php

namespace App\Livewire\SuperAdmin;

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\PlanFeature;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Ramsey\Uuid\Uuid;

#[Layout('layouts.super-admin')]
class BillingPlansPage extends Component
{
    use AuthorizesRequests;

    public ?int $editingPlanId = null;
    public string $name = '';
    public string $slug = '';
    public string $description = '';
    public string $billingInterval = 'monthly';
    public int $priceMinor = 0;
    public string $currency = 'NGN';
    public int $trialDays = 0;
    public bool $isActive = true;
    public array $editingFeatures = [];
    public array $originalFeatureIds = [];
    public array $removedFeatureIds = [];

    public function mount(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['editingPlanId', 'name', 'slug', 'description', 'billingInterval', 'priceMinor', 'currency', 'trialDays', 'isActive', 'editingFeatures', 'originalFeatureIds', 'removedFeatureIds']);

        $this->billingInterval = 'monthly';
        $this->currency = config('marketlens.currency.default', 'NGN');
        $this->isActive = true;
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        return view('livewire.super-admin.billing-plans-page', [
            'plans' => Plan::query()->withCount('features')->orderBy('sort_order')->get(),
        ])->title('Billing plans');
    }

    public function createPlan(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:160', 'alpha_dash', Rule::unique('plans', 'slug')],
            'description' => ['nullable', 'string', 'max:1000'],
            'billingInterval' => ['required', Rule::in(['monthly', 'annual'])],
            'priceMinor' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'trialDays' => ['required', 'integer', 'min:0'],
            'isActive' => ['boolean'],
        ]);

        $plan = Plan::query()->create([
            'uuid' => Uuid::uuid4()->toString(),
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? '',
            'billing_interval' => $validated['billingInterval'],
            'price_minor' => $validated['priceMinor'],
            'currency' => strtoupper($validated['currency']),
            'trial_days' => $validated['trialDays'],
            'sort_order' => (Plan::query()->max('sort_order') ?? 0) + 1,
            'is_active' => $validated['isActive'],
        ]);

        foreach ($this->editingFeatures as $featureRow) {
            if (empty(trim($featureRow['name'] ?? ''))) {
                continue;
            }

            PlanFeature::query()->create([
                'plan_id' => $plan->id,
                'name' => $featureRow['name'],
                'tooltip' => $featureRow['tooltip'] ?? null,
                'key' => str($featureRow['name'])->slug()->limit(80)->toString(),
                'is_highlighted' => (bool) ($featureRow['is_highlighted'] ?? false),
                'sort_order' => $featureRow['sort_order'] ?? 0,
            ]);
        }

        $this->reset(['editingPlanId', 'name', 'slug', 'description', 'billingInterval', 'priceMinor', 'currency', 'trialDays', 'isActive', 'editingFeatures']);
        $this->billingInterval = 'monthly';
        $this->currency = config('marketlens.currency.default', 'NGN');
        $this->isActive = true;

        $this->dispatch('notify', tone: 'success', title: 'Plan created', message: 'The subscription plan has been added.');
    }

    public function togglePlanActive(Plan $plan): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $plan->forceFill(['is_active' => ! $plan->is_active])->save();

        $this->dispatch(
            'notify',
            tone: 'success',
            title: $plan->is_active ? 'Plan enabled' : 'Plan disabled',
            message: 'The plan status has been updated.'
        );
    }

    public function startEditingPlan(Plan $plan): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $this->editingPlanId = $plan->id;
        $this->name = $plan->name;
        $this->slug = $plan->slug;
        $this->description = $plan->description;
        $this->billingInterval = $plan->billing_interval;
        $this->priceMinor = (int) $plan->getRawOriginal('price_minor');
        $this->currency = $plan->currency;
        $this->trialDays = $plan->trial_days;
        $this->isActive = $plan->is_active;
        $this->editingFeatures = $plan->features()->orderBy('sort_order')->get()->map(fn (PlanFeature $feature): array => [
            'id' => $feature->id,
            'name' => $feature->name,
            'tooltip' => $feature->tooltip,
            'is_highlighted' => $feature->is_highlighted,
            'sort_order' => $feature->sort_order,
        ])->all();
    }

    public function addFeatureRow(): void
    {
        $this->editingFeatures[] = [
            'id' => null,
            'name' => '',
            'tooltip' => '',
            'is_highlighted' => false,
            'sort_order' => count($this->editingFeatures) + 1,
        ];
    }

    public function removeFeatureRow(int $index): void
    {
        if (! isset($this->editingFeatures[$index])) {
            return;
        }

        unset($this->editingFeatures[$index]);
        $this->editingFeatures = array_values($this->editingFeatures);
    }

    public function saveEditedPlan(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:160', 'alpha_dash', Rule::unique('plans', 'slug')->ignore($this->editingPlanId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'billingInterval' => ['required', Rule::in(['monthly', 'annual'])],
            'priceMinor' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'trialDays' => ['required', 'integer', 'min:0'],
            'isActive' => ['boolean'],
            'editingFeatures.*.name' => ['nullable', 'string', 'max:160'],
            'editingFeatures.*.tooltip' => ['nullable', 'string', 'max:500'],
            'editingFeatures.*.is_highlighted' => ['boolean'],
        ]);

        $plan = Plan::query()->findOrFail($this->editingPlanId);
        $plan->forceFill([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? '',
            'billing_interval' => $validated['billingInterval'],
            'price_minor' => $validated['priceMinor'],
            'currency' => strtoupper($validated['currency']),
            'trial_days' => $validated['trialDays'],
            'is_active' => $validated['isActive'],
        ])->save();

        foreach ($this->editingFeatures as $featureRow) {
            if (! empty($featureRow['id']) && empty(trim($featureRow['name'] ?? ''))) {
                PlanFeature::query()->where('id', $featureRow['id'])->delete();
                continue;
            }

            if (empty(trim($featureRow['name'] ?? ''))) {
                continue;
            }

            if (! empty($featureRow['id'])) {
                PlanFeature::query()->findOrFail($featureRow['id'])->forceFill([
                    'name' => $featureRow['name'],
                    'tooltip' => $featureRow['tooltip'] ?? null,
                    'is_highlighted' => (bool) $featureRow['is_highlighted'],
                    'sort_order' => $featureRow['sort_order'] ?? 0,
                ])->save();
                continue;
            }

            PlanFeature::query()->create([
                'plan_id' => $plan->id,
                'name' => $featureRow['name'],
                'tooltip' => $featureRow['tooltip'] ?? null,
                'key' => str($featureRow['name'])->slug()->limit(80)->toString(),
                'is_highlighted' => (bool) $featureRow['is_highlighted'],
                'sort_order' => $featureRow['sort_order'] ?? 0,
            ]);
        }

        $this->dispatch('notify', tone: 'success', title: 'Plan updated', message: 'The subscription plan was updated successfully.');
        $this->reset(['editingPlanId']);
    }

    public function cancelEditing(): void
    {
        $this->reset(['editingPlanId', 'editingFeatures']);
        $this->billingInterval = 'monthly';
        $this->currency = config('marketlens.currency.default', 'NGN');
        $this->isActive = true;
    }
}
