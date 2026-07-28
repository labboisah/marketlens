<?php

namespace App\Livewire\Onboarding;

use App\Domain\Billing\Models\Plan;
use App\Domain\Onboarding\Models\OwnerOnboardingDraft;
use App\Domain\Onboarding\Services\CompleteOwnerOnboarding;
use App\Domain\Owners\Models\BusinessOwner;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.auth')]
class BusinessOnboardingWizard extends Component
{
    use WithFileUploads;

    public int $step = 1;

    public array $business = [
        'name' => '',
        'legal_name' => '',
        'business_type' => '',
        'registration_number' => '',
        'tax_number' => '',
        'phone' => '',
        'alternative_phone' => '',
        'email' => '',
        'address' => '',
        'state' => '',
        'local_government_area' => '',
        'default_currency' => 'NGN',
        'timezone' => 'Africa/Lagos',
        'preferred_language' => 'en',
        'logo_path' => '',
    ];

    public array $branch = [
        'name' => '',
        'code' => '',
        'phone' => '',
        'email' => '',
        'address' => '',
        'state' => '',
        'local_government_area' => '',
        'is_head_office' => true,
    ];

    public array $plan = [
        'plan_id' => null,
    ];

    public array $monitoring = [
        'daily_report_preference' => 'daily',
        'low_stock_alerts' => true,
        'cashier_shortage_alerts' => true,
        'synchronization_delay_alerts' => true,
        'security_alerts' => true,
        'email_notifications' => true,
        'push_notification_readiness' => true,
        'sms_placeholder' => false,
        'whatsapp_placeholder' => false,
    ];

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);

        if (BusinessOwner::query()->where('user_id', auth()->id())->exists()) {
            $this->redirectRoute('onboarding.completed');

            return;
        }

        $draft = $this->draft();
        $data = $draft->data ?? [];

        $this->step = $draft->current_step;
        $this->business = array_replace($this->business, $data['business'] ?? []);
        $this->branch = array_replace($this->branch, $data['branch'] ?? []);
        $this->plan = array_replace($this->plan, $data['plan'] ?? []);
        $this->monitoring = array_replace($this->monitoring, $data['monitoring'] ?? []);
    }

    public function nextStep(): void
    {
        $this->validate($this->rulesForStep($this->step));
        $this->step = min(5, $this->step + 1);
        $this->saveDraft();
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
        $this->saveDraft();
    }

    public function saveDraft(): void
    {
        $this->draft()->forceFill([
            'current_step' => $this->step,
            'data' => $this->payload(),
        ])->save();
    }

    public function complete(CompleteOwnerOnboarding $onboarding): void
    {
        foreach (range(1, 4) as $step) {
            $this->validate($this->rulesForStep($step));
        }

        $this->saveDraft();
        $onboarding->handle(auth()->user(), $this->draft());

        $this->redirectRoute('onboarding.completed');
    }

    public function render(): View
    {
        return view('livewire.onboarding.business-onboarding-wizard', [
            'plans' => Plan::query()->with('features')->active()->orderBy('sort_order')->get(),
            'businessTypes' => [
                'supermarket' => 'Supermarket',
                'provision_shop' => 'Provision shop',
                'wholesale_distributor' => 'Wholesale distributor',
                'pharmacy' => 'Pharmacy',
                'building_materials' => 'Building materials',
                'electronics' => 'Electronics',
                'cosmetics' => 'Cosmetics',
                'agricultural_products' => 'Agricultural products',
                'spare_parts' => 'Spare parts',
                'bookshop_stationery' => 'Bookshop and stationery',
            ],
        ]);
    }

    private function draft(): OwnerOnboardingDraft
    {
        return OwnerOnboardingDraft::query()->firstOrCreate(
            ['user_id' => auth()->id()],
            ['current_step' => 1, 'data' => $this->payload()],
        );
    }

    private function payload(): array
    {
        return [
            'business' => $this->business,
            'branch' => $this->branch,
            'plan' => $this->plan,
            'monitoring' => $this->monitoring,
        ];
    }

    private function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'business.name' => ['required', 'string', 'max:255'],
                'business.legal_name' => ['nullable', 'string', 'max:255'],
                'business.business_type' => ['required', 'string', 'max:120'],
                'business.registration_number' => ['nullable', 'string', 'max:120'],
                'business.tax_number' => ['nullable', 'string', 'max:120'],
                'business.phone' => ['required', 'string', 'max:32'],
                'business.alternative_phone' => ['nullable', 'string', 'max:32'],
                'business.email' => ['required', 'email:rfc', 'max:255'],
                'business.address' => ['required', 'string', 'max:500'],
                'business.state' => ['required', 'string', 'max:120'],
                'business.local_government_area' => ['required', 'string', 'max:120'],
                'business.default_currency' => ['required', 'string', 'size:3'],
                'business.timezone' => ['required', 'timezone'],
                'business.preferred_language' => ['required', Rule::in(['en', 'ha'])],
                'business.logo_path' => ['nullable', 'string', 'max:255'],
            ],
            2 => [
                'branch.name' => ['required', 'string', 'max:255'],
                'branch.code' => ['required', 'string', 'max:40'],
                'branch.phone' => ['required', 'string', 'max:32'],
                'branch.email' => ['nullable', 'email:rfc', 'max:255'],
                'branch.address' => ['required', 'string', 'max:500'],
                'branch.state' => ['required', 'string', 'max:120'],
                'branch.local_government_area' => ['required', 'string', 'max:120'],
                'branch.is_head_office' => ['boolean'],
            ],
            3 => [
                'plan.plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            ],
            4 => [
                'monitoring.daily_report_preference' => ['required', Rule::in(['daily', 'weekly', 'none'])],
                'monitoring.low_stock_alerts' => ['boolean'],
                'monitoring.cashier_shortage_alerts' => ['boolean'],
                'monitoring.synchronization_delay_alerts' => ['boolean'],
                'monitoring.security_alerts' => ['boolean'],
                'monitoring.email_notifications' => ['boolean'],
                'monitoring.push_notification_readiness' => ['boolean'],
                'monitoring.sms_placeholder' => ['boolean'],
                'monitoring.whatsapp_placeholder' => ['boolean'],
            ],
            default => [],
        };
    }
}
