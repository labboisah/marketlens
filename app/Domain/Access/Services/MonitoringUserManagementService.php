<?php

namespace App\Domain\Access\Services;

use App\Domain\Access\Models\BusinessMonitoringUser;
use App\Domain\Access\Models\MonitoringPermission;
use App\Domain\Access\Models\MonitoringRole;
use App\Domain\Access\Models\MonitoringUserInvitation;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Tenancy\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MonitoringUserManagementService
{
    public const PERMISSIONS = [
        'view-production' => 'View production operations',
        'view-production-finance' => 'View production costs and shares',
        'export-production' => 'Export production reports',
        'view-dashboard' => 'View dashboard',
        'view-sales' => 'View sales',
        'view-inventory' => 'View inventory',
        'view-expenses' => 'View expenses',
        'view-customer-debts' => 'View customer debts',
        'view-suppliers' => 'View suppliers',
        'view-cashiers' => 'View cashiers',
        'view-branches' => 'View branches',
        'view-devices' => 'View devices',
        'view-synchronization' => 'View synchronization',
        'view-alerts' => 'View alerts',
        'export-reports' => 'Export reports',
        'manage-subscription' => 'Manage subscription',
        'manage-monitoring-users' => 'Manage monitoring users',
        'manage-business-settings' => 'Manage business settings',
    ];

    public const ROLES = [
        'business-owner' => ['Business Owner', ['*'], true],
        'business-administrator' => ['Business Administrator', ['*'], false],
        'remote-manager' => ['Remote Manager', ['view-dashboard', 'view-sales', 'view-inventory', 'view-expenses', 'view-customer-debts', 'view-suppliers', 'view-cashiers', 'view-branches', 'view-devices', 'view-synchronization', 'view-alerts', 'export-reports'], false],
        'accountant' => ['Accountant', ['view-dashboard', 'view-sales', 'view-expenses', 'view-customer-debts', 'view-suppliers', 'view-cashiers', 'export-reports'], false],
        'auditor' => ['Auditor', ['view-dashboard', 'view-sales', 'view-inventory', 'view-expenses', 'view-customer-debts', 'view-suppliers', 'view-cashiers', 'view-branches', 'view-devices', 'view-synchronization', 'view-alerts', 'export-reports'], false],
        'read-only-viewer' => ['Read-Only Viewer', ['view-dashboard', 'view-sales', 'view-inventory', 'view-expenses', 'view-customer-debts', 'view-suppliers', 'view-cashiers', 'view-branches', 'view-devices', 'view-synchronization', 'view-alerts'], false],
    ];

    public function ensureDefaults(): void
    {
        $permissions = collect(self::PERMISSIONS)->mapWithKeys(fn (string $name, string $slug): array => [
            $slug => MonitoringPermission::query()->firstOrCreate(['slug' => $slug], ['name' => $name]),
        ]);

        foreach (self::ROLES as $slug => [$name, $rolePermissions, $isOwner]) {
            $role = MonitoringRole::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_owner_role' => $isOwner]
            );

            $permissionIds = $rolePermissions === ['*']
                ? $permissions->pluck('id')->all()
                : $permissions->only($rolePermissions)->pluck('id')->all();

            $role->permissions()->sync($permissionIds);
        }
    }

    public function roles()
    {
        $this->ensureDefaults();

        return MonitoringRole::query()->with('permissions')->orderBy('id')->get();
    }

    /**
     * @return array<string, string>
     */
    public function permissions(): array
    {
        return self::PERMISSIONS;
    }

    /**
     * @param array<int, int> $branchIds
     * @param array<int, string> $modulePermissions
     */
    public function invite(Business $business, User $actor, array $data, array $branchIds, array $modulePermissions): MonitoringUserInvitation
    {
        $this->ensureDefaults();
        $role = MonitoringRole::query()->whereKey($data['role_id'])->firstOrFail();
        $this->assertBranchesBelongToBusiness($business, $branchIds);
        $modulePermissions = $this->allowedPermissions($modulePermissions);
        $token = Str::random(48);

        $invitation = MonitoringUserInvitation::query()->create([
            'business_id' => $business->id,
            'monitoring_role_id' => $role->id,
            'invited_by' => $actor->id,
            'email' => strtolower($data['email']),
            'token_hash' => hash('sha256', $token),
            'branch_ids' => array_values($branchIds),
            'module_permissions' => array_values($modulePermissions),
            'status' => 'pending',
            'expires_at' => now()->addDays((int) ($data['expires_in_days'] ?? 7)),
            'last_sent_at' => now(),
            'send_count' => 1,
        ]);

        if ($user = User::query()->where('email', strtolower($data['email']))->first()) {
            $this->grantAccess($business, $user, $role, $actor, $branchIds, $modulePermissions);
        }

        $this->audit($business, $actor, 'monitoring_user.invited', $invitation, ['email' => $invitation->email, 'role' => $role->slug]);

        return $invitation;
    }

    public function resend(Business $business, User $actor, int $invitationId): MonitoringUserInvitation
    {
        $invitation = MonitoringUserInvitation::query()->where('business_id', $business->id)->whereKey($invitationId)->firstOrFail();

        if ($invitation->status !== 'pending' || $invitation->cancelled_at !== null || $invitation->accepted_at !== null) {
            throw ValidationException::withMessages(['invitation' => 'Only pending invitations can be resent.']);
        }

        $invitation->forceFill([
            'expires_at' => now()->addDays(7),
            'last_sent_at' => now(),
            'send_count' => $invitation->send_count + 1,
        ])->save();

        $this->audit($business, $actor, 'monitoring_user.invitation_resent', $invitation);

        return $invitation;
    }

    public function cancel(Business $business, User $actor, int $invitationId): void
    {
        $invitation = MonitoringUserInvitation::query()->where('business_id', $business->id)->whereKey($invitationId)->firstOrFail();
        $invitation->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();
        $this->audit($business, $actor, 'monitoring_user.invitation_cancelled', $invitation);
    }

    public function suspend(Business $business, User $actor, int $assignmentId): void
    {
        $assignment = BusinessMonitoringUser::query()->where('business_id', $business->id)->whereKey($assignmentId)->firstOrFail();
        $assignment->forceFill(['status' => 'suspended', 'suspended_at' => now()])->save();
        $this->audit($business, $actor, 'monitoring_user.suspended', $assignment);
    }

    public function revoke(Business $business, User $actor, int $assignmentId): void
    {
        $assignment = BusinessMonitoringUser::query()->where('business_id', $business->id)->whereKey($assignmentId)->firstOrFail();
        $assignment->forceFill(['status' => 'revoked', 'revoked_at' => now()])->save();
        $this->audit($business, $actor, 'monitoring_user.revoked', $assignment);
    }

    /**
     * @param array<int, int> $branchIds
     * @param array<int, string> $modulePermissions
     */
    public function updateAccess(Business $business, User $actor, int $assignmentId, int $roleId, array $branchIds, array $modulePermissions): BusinessMonitoringUser
    {
        $this->ensureDefaults();
        $assignment = BusinessMonitoringUser::query()->where('business_id', $business->id)->whereKey($assignmentId)->firstOrFail();
        $this->assertBranchesBelongToBusiness($business, $branchIds);
        $role = MonitoringRole::query()->whereKey($roleId)->firstOrFail();
        $modulePermissions = $this->allowedPermissions($modulePermissions);

        $assignment->forceFill([
            'monitoring_role_id' => $role->id,
            'module_permissions' => array_values($modulePermissions),
        ])->save();
        $assignment->branches()->sync($branchIds);

        $this->audit($business, $actor, 'monitoring_user.access_updated', $assignment);

        return $assignment->refresh();
    }

    /**
     * @param array<int, int> $branchIds
     * @param array<int, string> $modulePermissions
     */
    public function grantAccess(Business $business, User $user, MonitoringRole $role, User $actor, array $branchIds, array $modulePermissions): BusinessMonitoringUser
    {
        return DB::transaction(function () use ($business, $user, $role, $actor, $branchIds, $modulePermissions): BusinessMonitoringUser {
            $assignment = BusinessMonitoringUser::query()->updateOrCreate(
                ['business_id' => $business->id, 'user_id' => $user->id],
                [
                    'monitoring_role_id' => $role->id,
                    'status' => 'active',
                    'suspended_at' => null,
                    'revoked_at' => null,
                    'invited_by' => $actor->id,
                    'module_permissions' => array_values($modulePermissions),
                ]
            );
            $assignment->branches()->sync($branchIds);

            return $assignment;
        });
    }

    /** @param array<int, int> $branchIds */
    private function assertBranchesBelongToBusiness(Business $business, array $branchIds): void
    {
        if ($branchIds === []) {
            return;
        }

        $ownedCount = $business->branches()->whereIn('id', $branchIds)->count();
        if ($ownedCount !== count(array_unique($branchIds))) {
            throw ValidationException::withMessages(['branchIds' => 'Branch access must stay within this business.']);
        }
    }

    /** @param array<int, string> $permissions */
    private function allowedPermissions(array $permissions): array
    {
        return collect($permissions)->filter(fn (string $permission): bool => array_key_exists($permission, self::PERMISSIONS))->values()->all();
    }

    private function audit(Business $business, User $actor, string $action, object $subject, array $metadata = []): void
    {
        AuditLog::query()->create([
            'business_id' => $business->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->id ?? null,
            'metadata' => $metadata,
        ]);
    }
}
