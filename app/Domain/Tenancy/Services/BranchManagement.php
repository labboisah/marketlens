<?php

namespace App\Domain\Tenancy\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Licensing\Services\BusinessLicenseEntitlementService;
use App\Domain\Modules\Services\ModuleAccessService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\BranchOperatingMode;
use App\Enums\BranchStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchManagement
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Business $business, User $actor, array $data): Branch
    {
        $this->enforceBranchLimit($business);
        $this->enforceUniqueCode($business, (string) ($data['code'] ?? ''));
        $operatingModes = $this->normalizeOperatingModes($data['operating_modes'] ?? ['sales']);

        return DB::transaction(function () use ($business, $actor, $data, $operatingModes): Branch {
            $isHeadOffice = (bool) ($data['is_head_office'] ?? false);

            if (! $business->branches()->exists()) {
                $isHeadOffice = true;
            }

            if ($isHeadOffice) {
                $this->clearHeadOffice($business);
            }

            $branch = $business->branches()->create([
                ...$data,
                'operating_modes' => $operatingModes,
                'is_head_office' => $isHeadOffice,
                'status' => BranchStatus::Active,
            ]);

            $this->ensureHeadOffice($business);
            $this->audit($actor, $branch, 'branch.created');

            return $branch;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Branch $branch, User $actor, array $data): Branch
    {
        $this->enforceUniqueCode($branch->business, (string) ($data['code'] ?? ''), $branch);
        $operatingModes = $this->normalizeOperatingModes($data['operating_modes'] ?? $branch->operating_modes ?? ['sales']);

        return DB::transaction(function () use ($branch, $actor, $data, $operatingModes): Branch {
            $isHeadOffice = (bool) ($data['is_head_office'] ?? false);

            if ($isHeadOffice) {
                $this->clearHeadOffice($branch->business);
            } elseif ($branch->is_head_office && $branch->business->branches()->whereKeyNot($branch->id)->exists()) {
                throw ValidationException::withMessages([
                    'is_head_office' => 'A business must have one head office.',
                ]);
            }

            $branch->forceFill([
                ...$data,
                'operating_modes' => $operatingModes,
                'is_head_office' => $isHeadOffice,
            ])->save();

            $this->ensureHeadOffice($branch->business);
            $this->audit($actor, $branch, 'branch.updated');

            return $branch->refresh();
        });
    }

    public function activate(Branch $branch, User $actor): Branch
    {
        return DB::transaction(function () use ($branch, $actor): Branch {
            $branch->forceFill([
                'status' => BranchStatus::Active,
                'archived_at' => null,
            ])->save();

            $this->ensureHeadOffice($branch->business);
            $this->audit($actor, $branch, 'branch.activated');

            return $branch->refresh();
        });
    }

    public function suspend(Branch $branch, User $actor): Branch
    {
        return DB::transaction(function () use ($branch, $actor): Branch {
            if ($branch->is_head_office) {
                throw ValidationException::withMessages([
                    'branch' => 'Designate another head office before suspending this branch.',
                ]);
            }

            $branch->forceFill(['status' => BranchStatus::Suspended])->save();
            $this->ensureHeadOffice($branch->business);
            $this->audit($actor, $branch, 'branch.suspended');

            return $branch->refresh();
        });
    }

    public function designateHeadOffice(Branch $branch, User $actor): Branch
    {
        if ($branch->status !== BranchStatus::Active) {
            throw ValidationException::withMessages([
                'branch' => 'Only an active branch can be designated as head office.',
            ]);
        }

        return DB::transaction(function () use ($branch, $actor): Branch {
            $this->clearHeadOffice($branch->business);
            $branch->forceFill(['is_head_office' => true])->save();
            $this->audit($actor, $branch, 'branch.head_office_designated');

            return $branch->refresh();
        });
    }

    public function archive(Branch $branch, User $actor): Branch
    {
        return DB::transaction(function () use ($branch, $actor): Branch {
            if ($branch->is_head_office) {
                throw ValidationException::withMessages([
                    'branch' => 'Designate another head office before archiving this branch.',
                ]);
            }

            $branch->forceFill([
                'status' => BranchStatus::Archived,
                'archived_at' => now(),
            ])->save();

            $this->ensureHeadOffice($branch->business);
            $this->audit($actor, $branch, 'branch.archived');

            return $branch->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function updateModuleSettings(Branch $branch, User $actor, array $settings): Branch
    {
        app(ModuleAccessService::class)->validateBranchSettings($branch->business, $branch, $settings);

        $branch->forceFill(['module_settings' => array_merge($branch->module_settings ?? [], $settings)])->save();
        app(ModuleAccessService::class)->forget($branch->business_id, $branch);
        $this->audit($actor, $branch, 'branch.modules_updated');

        return $branch->refresh();
    }

    public function maxBranches(Business $business): int
    {
        $licensedLimit = app(BusinessLicenseEntitlementService::class)->limit($business, 'max_branches');

        if ($licensedLimit !== null) {
            return $licensedLimit === 'unlimited' ? PHP_INT_MAX : (int) $licensedLimit;
        }

        $featureNames = $business->currentSubscription?->plan?->features()
            ->pluck('name')
            ->map(fn (string $name): string => strtolower($name))
            ->all() ?? [];

        if (collect($featureNames)->contains(fn (string $name): bool => str_contains($name, 'multiple branches'))) {
            return 999;
        }

        return 1;
    }

    private function enforceBranchLimit(Business $business): void
    {
        $activeBranchCount = $business->branches()
            ->whereNotIn('status', [BranchStatus::Archived->value])
            ->count();

        if ($activeBranchCount >= $this->maxBranches($business)) {
            throw ValidationException::withMessages([
                'branch' => 'The current subscription does not allow another branch.',
            ]);
        }
    }

    private function enforceUniqueCode(Business $business, string $code, ?Branch $ignore = null): void
    {
        if ($code === '') {
            return;
        }

        $exists = $business->branches()
            ->where('code', $code)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => 'Branch code must be unique within this business.',
            ]);
        }
    }

    /** @param mixed $modes @return array<int, string> */
    private function normalizeOperatingModes(mixed $modes): array
    {
        if (! is_array($modes) || $modes === []) {
            throw ValidationException::withMessages([
                'operatingModes' => 'Select at least one operating mode for this branch.',
            ]);
        }

        $normalized = [];
        foreach ($modes as $mode) {
            $operatingMode = is_string($mode) ? BranchOperatingMode::tryFrom($mode) : null;
            if (! $operatingMode) {
                throw ValidationException::withMessages([
                    'operatingModes' => 'The selected branch operating mode is invalid.',
                ]);
            }
            $normalized[] = $operatingMode->value;
        }

        return array_values(array_unique($normalized));
    }

    private function clearHeadOffice(Business $business): void
    {
        $business->branches()->update(['is_head_office' => false]);
    }

    private function ensureHeadOffice(Business $business): void
    {
        if ($business->branches()->where('is_head_office', true)->exists()) {
            return;
        }

        $business->branches()
            ->where('status', BranchStatus::Active)
            ->oldest('id')
            ->first()
            ?->forceFill(['is_head_office' => true])
            ->save();
    }

    private function audit(User $actor, Branch $branch, string $action): void
    {
        AuditLog::query()->create([
            'business_id' => $branch->business_id,
            'branch_id' => $branch->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => Branch::class,
            'subject_id' => $branch->id,
            'metadata' => [
                'branch_uuid' => $branch->uuid,
                'branch_code' => $branch->code,
                'status' => $branch->status?->value,
                'operating_modes' => $branch->operating_modes ?? [],
            ],
        ]);
    }
}
