<?php

namespace App\Livewire\Platform\Settings;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Notifications\Models\AlertNotificationPreference;
use App\Domain\Settings\Models\BusinessDataExport;
use App\Domain\Modules\Services\ModuleAccessService;
use App\Domain\Settings\Models\BusinessTrustedDevice;
use App\Domain\Settings\Services\BusinessSettingsService;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BusinessContext;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use App\Enums\BusinessModule;
use App\Enums\ServiceCalculationBasis;
use App\Enums\SettlementFrequency;
use App\Enums\ShareholderDistributionBasis;
use App\Enums\ShareholderOwnershipType;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class BusinessSettingsPage extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public string $name = '';

    public string $legalName = '';

    public ?TemporaryUploadedFile $logo = null;

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $state = '';

    public string $localGovernmentArea = '';

    public string $businessType = '';

    public string $defaultCurrency = 'NGN';

    public string $timezone = 'Africa/Lagos';

    public string $preferredLanguage = 'en';

    public ?int $defaultBranchId = null;

    public string $defaultDateRange = '30_days';

    public string $dashboardRefreshPreference = '60s';

    public int $staleDataWarningThreshold = 60;

    /** @var array<string, bool> */
    public array $summaryDisplayPreferences = [
        'sales' => true,
        'inventory' => true,
        'cashiers' => true,
        'synchronization' => true,
    ];

    /** @var array<string, bool> */
    public array $notificationPreferences = [];

    public string $currentPassword = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public string $closureReason = '';
    /** @var array<string, mixed> */
    public array $moduleSettings = [];

    public function mount(BusinessContext $businessContext): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('view', $business);

        $this->name = $business->name;
        $this->legalName = $business->legal_name ?? '';
        $this->phone = $business->phone ?? '';
        $this->email = $business->email ?? '';
        $this->address = $business->address ?? '';
        $this->state = $business->state ?? '';
        $this->localGovernmentArea = $business->local_government_area ?? '';
        $this->businessType = $business->business_type ?? '';
        $this->defaultCurrency = $business->default_currency ?? 'NGN';
        $this->timezone = $business->timezone ?? 'Africa/Lagos';
        $this->preferredLanguage = $business->preferred_language ?? 'en';
        $this->defaultBranchId = $business->default_branch_id;

        $preferences = $business->monitoring_preferences ?? [];
        $this->defaultDateRange = $preferences['default_date_range'] ?? '30_days';
        $this->dashboardRefreshPreference = $preferences['dashboard_refresh_preference'] ?? '60s';
        $this->staleDataWarningThreshold = (int) ($preferences['stale_data_warning_threshold'] ?? 60);
        $this->summaryDisplayPreferences = array_merge($this->summaryDisplayPreferences, $preferences['summary_display_preferences'] ?? []);
        $this->notificationPreferences = $this->defaultNotificationPreferences($business);
        $this->moduleSettings = $this->defaultModuleSettings($business);
    }

    public function render(BusinessContext $businessContext, BusinessSettingsService $settings): View
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('view', $business);

        return view('livewire.platform.settings.business-settings-page', [
            'business' => $business,
            'branches' => $business->branches()->orderBy('name')->get(),
            'notificationCategories' => $this->notificationCategories(),
            'notificationChannels' => $this->notificationChannels(),
            'platformSessions' => $this->platformSessions(),
            'monitoringSessions' => $this->monitoringSessions(),
            'trustedDevices' => BusinessTrustedDevice::query()
                ->where('business_id', $business->id)
                ->where('user_id', auth()->id())
                ->latest('trusted_at')
                ->limit(8)
                ->get(),
            'securitySetting' => $settings->securitySetting(auth()->user()),
            'dataExports' => BusinessDataExport::query()
                ->where('business_id', $business->id)
                ->latest('requested_at')
                ->limit(8)
                ->get(),
            'auditLogs' => AuditLog::query()
                ->where('business_id', $business->id)
                ->latest('created_at')
                ->limit(12)
                ->get(),
            'retentionDays' => (int) config('marketlens.billing.retention_days', 180),
            'moduleAccess' => app(ModuleAccessService::class)->resolveAll($business),
            'businessModules' => BusinessModule::cases(),
            'calculationBases' => ServiceCalculationBasis::cases(),
            'settlementFrequencies' => SettlementFrequency::cases(),
            'ownershipTypes' => ShareholderOwnershipType::cases(),
            'distributionBases' => ShareholderDistributionBasis::cases(),
        ])->title('Business settings');
    }

    public function saveGeneral(BusinessContext $businessContext, BusinessSettingsService $settings): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manageSettings', $business);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'legalName' => ['nullable', 'string', 'max:160'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:500'],
            'state' => ['nullable', 'string', 'max:120'],
            'localGovernmentArea' => ['nullable', 'string', 'max:120'],
            'businessType' => ['nullable', 'string', 'max:120'],
            'defaultCurrency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'timezone', 'max:80'],
            'preferredLanguage' => ['required', 'string', 'max:20'],
        ]);

        $logoPath = $business->logo_path;
        if ($validated['logo'] instanceof TemporaryUploadedFile) {
            $logoPath = $validated['logo']->store('business-logos', 'public');
        }

        $settings->updateGeneral($business, auth()->user(), [
            'name' => $validated['name'],
            'legal_name' => $validated['legalName'] ?: null,
            'logo_path' => $logoPath,
            'phone' => $validated['phone'] ?: null,
            'email' => $validated['email'] ?: null,
            'address' => $validated['address'] ?: null,
            'state' => $validated['state'] ?: null,
            'local_government_area' => $validated['localGovernmentArea'] ?: null,
            'business_type' => $validated['businessType'] ?: null,
            'default_currency' => $validated['defaultCurrency'],
            'timezone' => $validated['timezone'],
            'preferred_language' => $validated['preferredLanguage'],
        ]);

        $this->logo = null;
        $this->dispatch('notify', tone: 'success', title: 'General settings saved', message: 'Business profile details were updated.');
    }

    public function saveMonitoring(BusinessContext $businessContext, BusinessSettingsService $settings): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manageSettings', $business);

        $validated = $this->validate([
            'defaultBranchId' => ['nullable', 'integer'],
            'defaultDateRange' => ['required', Rule::in(['today', '7_days', '30_days', 'this_month'])],
            'dashboardRefreshPreference' => ['required', Rule::in(['manual', '30s', '60s', '5m'])],
            'staleDataWarningThreshold' => ['required', 'integer', 'min:5', 'max:1440'],
            'summaryDisplayPreferences.sales' => ['boolean'],
            'summaryDisplayPreferences.inventory' => ['boolean'],
            'summaryDisplayPreferences.cashiers' => ['boolean'],
            'summaryDisplayPreferences.synchronization' => ['boolean'],
        ]);

        $settings->updateMonitoring($business, auth()->user(), [
            'default_branch_id' => $validated['defaultBranchId'] ?: null,
            'default_date_range' => $validated['defaultDateRange'],
            'dashboard_refresh_preference' => $validated['dashboardRefreshPreference'],
            'stale_data_warning_threshold' => $validated['staleDataWarningThreshold'],
            'summary_display_preferences' => $validated['summaryDisplayPreferences'],
        ]);

        $this->dispatch('notify', tone: 'success', title: 'Monitoring settings saved', message: 'Dashboard defaults and warning thresholds were updated.');
    }


    public function saveModules(BusinessContext $businessContext, BusinessSettingsService $settings): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manageSettings', $business);

        $validated = $this->validate([
            'moduleSettings.production.enabled' => ['boolean'],
            'moduleSettings.production.business_level_locks.prevent_branch_overrides' => ['boolean'],
            'moduleSettings.services.enabled' => ['boolean'],
            'moduleSettings.services.staff_revenue_sharing_enabled' => ['boolean'],
            'moduleSettings.services.default_calculation_basis' => ['required', Rule::enum(ServiceCalculationBasis::class)],
            'moduleSettings.services.deduct_direct_expenses_before_sharing' => ['boolean'],
            'moduleSettings.services.staff_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'moduleSettings.services.business_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'moduleSettings.services.expense_reserve_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'moduleSettings.services.require_service_completion_before_allocation' => ['boolean'],
            'moduleSettings.services.require_confirmed_payment_before_allocation' => ['boolean'],
            'moduleSettings.services.require_manager_approval' => ['boolean'],
            'moduleSettings.services.allow_multiple_staff_per_service' => ['boolean'],
            'moduleSettings.services.allow_service_specific_rules' => ['boolean'],
            'moduleSettings.services.allow_branch_specific_rules' => ['boolean'],
            'moduleSettings.services.settlement_frequency' => ['required', Rule::enum(SettlementFrequency::class)],
            'moduleSettings.services.business_level_locks.prevent_branch_overrides' => ['boolean'],
            'moduleSettings.shareholders.enabled' => ['boolean'],
            'moduleSettings.shareholders.ownership_type' => ['required', Rule::enum(ShareholderOwnershipType::class)],
            'moduleSettings.shareholders.distribution_basis' => ['required', Rule::enum(ShareholderDistributionBasis::class)],
            'moduleSettings.shareholders.require_approved_resolution' => ['boolean'],
            'moduleSettings.shareholders.require_closed_accounting_period' => ['boolean'],
            'moduleSettings.shareholders.require_retained_profit_allocation' => ['boolean'],
            'moduleSettings.shareholders.require_tax_provision' => ['boolean'],
            'moduleSettings.shareholders.allow_partial_payments' => ['boolean'],
            'moduleSettings.shareholders.allow_shareholder_loans' => ['boolean'],
            'moduleSettings.shareholders.allow_capital_contributions' => ['boolean'],
            'moduleSettings.shareholders.business_level_locks.prevent_branch_overrides' => ['boolean'],
        ]);

        $settings->updateModules($business, auth()->user(), $validated['moduleSettings']);
        $this->moduleSettings = $this->defaultModuleSettings($business->refresh());

        $this->dispatch('notify', tone: 'success', title: 'Module settings saved', message: 'Business module capabilities and defaults were updated.');
    }
    public function saveNotifications(BusinessContext $businessContext, BusinessSettingsService $settings): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manageSettings', $business);

        $allowedKeys = collect($this->notificationCategories())->keys()
            ->crossJoin(array_keys($this->notificationChannels()))
            ->map(fn (array $parts): string => $parts[0].'|'.$parts[1])
            ->all();

        $validated = $this->validate([
            'notificationPreferences' => ['array'],
            'notificationPreferences.*' => ['boolean'],
        ]);

        $preferences = collect($validated['notificationPreferences'] ?? [])
            ->only($allowedKeys)
            ->map(fn (mixed $enabled): bool => (bool) $enabled)
            ->all();

        $settings->updateNotificationPreferences($business, auth()->user(), $preferences);

        $this->dispatch('notify', tone: 'success', title: 'Notification settings saved', message: 'Alert delivery preferences were updated.');
    }

    public function changePassword(BusinessContext $businessContext, BusinessSettingsService $settings): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manageSettings', $business);

        $validated = $this->validate([
            'currentPassword' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'same:passwordConfirmation'],
            'passwordConfirmation' => ['required', 'string'],
        ]);

        $settings->changePassword($business, auth()->user(), $validated['currentPassword'], $validated['password']);

        $this->reset(['currentPassword', 'password', 'passwordConfirmation']);
        $this->dispatch('notify', tone: 'success', title: 'Password changed', message: 'Your Platform password was updated.');
    }

    public function trustCurrentDevice(BusinessContext $businessContext, BusinessSettingsService $settings): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manageSettings', $business);

        $settings->trustCurrentDevice($business, auth()->user(), request());

        $this->dispatch('notify', tone: 'success', title: 'Device trusted', message: 'This browser was added to trusted devices.');
    }

    public function revokeTrustedDevice(BusinessContext $businessContext, BusinessSettingsService $settings, int $deviceId): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manageSettings', $business);

        $settings->revokeTrustedDevice($business, auth()->user(), $deviceId);

        $this->dispatch('notify', tone: 'warning', title: 'Trusted device revoked', message: 'The device can no longer be treated as trusted.');
    }

    public function revokePlatformSession(BusinessContext $businessContext, BusinessSettingsService $settings, string $sessionId): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manageSettings', $business);

        $settings->revokePlatformSession($business, auth()->user(), $sessionId);

        $this->dispatch('notify', tone: 'warning', title: 'Platform session revoked', message: 'The selected browser session was removed.');
    }

    public function revokeMonitoringSession(BusinessContext $businessContext, BusinessSettingsService $settings, int $tokenId): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manageSettings', $business);

        $settings->revokeMonitoringSession($business, auth()->user(), $tokenId);

        $this->dispatch('notify', tone: 'warning', title: 'Monitoring session revoked', message: 'The selected Monitoring App token was removed.');
    }

    public function requestDataExport(BusinessContext $businessContext, BusinessSettingsService $settings): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('exportData', $business);

        $settings->requestDataExport($business, auth()->user());

        $this->dispatch('notify', tone: 'success', title: 'Data export created', message: 'Business records were exported with a seven-day retention window.');
    }

    public function requestAccountClosure(BusinessContext $businessContext, BusinessSettingsService $settings): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('requestClosure', $business);

        $validated = $this->validate(['closureReason' => ['nullable', 'string', 'max:500']]);
        $settings->requestAccountClosure($business, auth()->user(), $validated['closureReason'] ?: null);
        $this->closureReason = '';

        $this->dispatch('notify', tone: 'warning', title: 'Account closure requested', message: 'No synchronized records were deleted; retention policy still applies.');
    }

    private function currentBusiness(BusinessContext $businessContext): Business
    {
        return auth()->user()->ownedBusinesses()
            ->with(['branches', 'defaultBranch'])
            ->whereKey($businessContext->id() ?: auth()->user()->ownedBusinesses()->value('businesses.id'))
            ->firstOrFail();
    }

    /**
     * @return array<string, string>
     */
    private function notificationCategories(): array
    {
        return [
            'low_stock' => 'Low-stock alerts',
            'cashier_shortage' => 'Cashier-shortage alerts',
            'debt' => 'Debt alerts',
            'synchronization' => 'Synchronization alerts',
            'security' => 'Security alerts',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function notificationChannels(): array
    {
        return [
            'email' => 'Email',
            'push' => 'Push',
            'sms' => 'SMS placeholder',
            'whatsapp' => 'WhatsApp placeholder',
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function defaultNotificationPreferences(Business $business): array
    {
        $defaults = [];
        foreach (array_keys($this->notificationCategories()) as $category) {
            foreach (array_keys($this->notificationChannels()) as $channel) {
                $defaults[$category.'|'.$channel] = in_array($channel, ['email', 'push'], true);
            }
        }

        $stored = AlertNotificationPreference::query()
            ->where('business_id', $business->id)
            ->where('user_id', auth()->id())
            ->get()
            ->keyBy(fn (AlertNotificationPreference $preference): string => $preference->category.'|'.$preference->channel);

        foreach ($stored as $key => $preference) {
            $defaults[$key] = $preference->enabled;
        }

        return $defaults;
    }


    /**
     * @return array<string, mixed>
     */
    private function defaultModuleSettings(Business $business): array
    {
        return array_replace_recursive([
            'production' => ['enabled' => false, 'business_level_locks' => ['prevent_branch_overrides' => false]],
            'services' => [
                'enabled' => false,
                'staff_revenue_sharing_enabled' => false,
                'default_calculation_basis' => ServiceCalculationBasis::GrossAmount->value,
                'deduct_direct_expenses_before_sharing' => false,
                'staff_percentage' => 50,
                'business_percentage' => 50,
                'expense_reserve_percentage' => 0,
                'require_service_completion_before_allocation' => true,
                'require_confirmed_payment_before_allocation' => true,
                'require_manager_approval' => false,
                'allow_multiple_staff_per_service' => false,
                'allow_service_specific_rules' => false,
                'allow_branch_specific_rules' => false,
                'settlement_frequency' => SettlementFrequency::Monthly->value,
                'business_level_locks' => ['prevent_branch_overrides' => false],
            ],
            'shareholders' => [
                'enabled' => false,
                'ownership_type' => ShareholderOwnershipType::LimitedCompany->value,
                'distribution_basis' => ShareholderDistributionBasis::OwnershipPercentage->value,
                'require_approved_resolution' => true,
                'require_closed_accounting_period' => true,
                'require_retained_profit_allocation' => true,
                'require_tax_provision' => true,
                'allow_partial_payments' => false,
                'allow_shareholder_loans' => false,
                'allow_capital_contributions' => false,
                'business_level_locks' => ['prevent_branch_overrides' => false],
            ],
        ], $business->module_settings ?? []);
    }
    private function platformSessions(): \Illuminate\Support\Collection
    {
        return DB::table('sessions')
            ->where('user_id', auth()->id())
            ->orderByDesc('last_activity')
            ->limit(8)
            ->get()
            ->map(function (object $session): object {
                $session->last_seen_at = now()->createFromTimestamp($session->last_activity);

                return $session;
            });
    }

    private function monitoringSessions(): \Illuminate\Support\Collection
    {
        return PersonalAccessToken::query()
            ->where('tokenable_type', auth()->user()->getMorphClass())
            ->where('tokenable_id', auth()->id())
            ->latest('last_used_at')
            ->latest()
            ->limit(8)
            ->get();
    }
}
