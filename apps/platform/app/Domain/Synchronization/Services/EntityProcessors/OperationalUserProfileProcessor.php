<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

use App\Domain\Synchronization\Data\SyncRecordResult;
use App\Domain\Synchronization\Models\OperationalUserProfile;
use App\Domain\Synchronization\Models\SyncBatch;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class OperationalUserProfileProcessor extends SyncEntityProcessor
{
    private const SENSITIVE_FIELDS = [
        'password',
        'password_hash',
        'pin',
        'pin_hash',
        'access_token',
        'refresh_token',
        'remember_token',
        'password_reset_token',
        'password_reset_expires_at',
        'session_id',
        'session_token',
        'local_session',
        'sessions',
    ];

    public function entity(): string
    {
        return 'operational_user_profile';
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function process(SyncBatch $batch, array $record): SyncRecordResult
    {
        $payload = $record['payload'] ?? [];

        if (is_array($payload) === false) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Operational-user profile payload must be an object.', false);
        }

        $sensitiveFields = array_values(array_intersect(array_keys($payload), self::SENSITIVE_FIELDS));

        if ($sensitiveFields !== []) {
            return new SyncRecordResult('rejected', $record, 'SENSITIVE_FIELD_REJECTED', 'Operational-user profile payload contains fields that must never be synchronized.', false);
        }

        $validator = Validator::make($payload, [
            'employee_number' => ['nullable', 'string', 'max:80'],
            'display_name' => ['required', 'string', 'max:160'],
            'username' => ['nullable', 'string', 'max:120'],
            'staff_code' => ['nullable', 'string', 'max:120'],
            'role_name' => ['required', 'string', 'max:120'],
            'role_code' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'string', 'in:active,inactive,suspended,disabled'],
            'created_at' => ['required', 'date'],
            'updated_at' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', $validator->errors()->first(), false);
        }

        $safePayload = Arr::only($payload, [
            'employee_number',
            'display_name',
            'username',
            'staff_code',
            'role_name',
            'role_code',
            'status',
            'created_at',
            'updated_at',
        ]);

        $record['payload'] = $safePayload;
        $result = parent::process($batch, $record);

        if ($result->status !== 'accepted') {
            return $result;
        }

        OperationalUserProfile::query()->updateOrCreate(
            [
                'business_id' => $batch->business_id,
                'branch_id' => $batch->branch_id,
                'uuid' => $record['record_uuid'],
            ],
            [
                'employee_number' => $safePayload['employee_number'] ?? null,
                'display_name' => $safePayload['display_name'],
                'username' => $safePayload['username'] ?? null,
                'staff_code' => $safePayload['staff_code'] ?? null,
                'role_name' => $safePayload['role_name'],
                'role_code' => $safePayload['role_code'] ?? null,
                'status' => $safePayload['status'],
                'is_active' => $safePayload['status'] === 'active',
                'replicated_created_at' => Carbon::parse($safePayload['created_at']),
                'replicated_updated_at' => Carbon::parse($safePayload['updated_at']),
            ],
        );

        return $result;
    }
}
