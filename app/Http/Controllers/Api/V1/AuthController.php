<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\Models\AuditLog;
use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SuccessResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): SuccessResource|JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $key = strtolower($request->string('email')->toString()).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => __('Too many login attempts. Please try again later.'),
            ]);
        }

        $user = User::query()->where('email', $request->string('email')->lower()->toString())->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        if (! $user->account_status->canAuthenticate()) {
            return $this->errorResponse([
                'message' => 'This account cannot access MarketLens.',
                'code' => 'ACCOUNT_DISABLED',
            ], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            return $this->errorResponse([
                'message' => 'Email verification is required before accessing MarketLens.',
                'code' => 'EMAIL_NOT_VERIFIED',
            ], 403);
        }

        if ($user->account_status === AccountStatus::PendingVerification) {
            $user->forceFill(['account_status' => AccountStatus::Active])->save();
        }

        RateLimiter::clear($key);

        $token = $user->createToken(
            $request->string('device_name')->toString() ?: 'monitoring-app',
            ['monitoring:read'],
        )->plainTextToken;

        AuditLog::query()->create([
            'actor_id' => $user->id,
            'action' => 'monitoring_user.token_created',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'metadata' => ['device_name' => $request->input('device_name', 'monitoring-app')],
        ]);

        return new SuccessResource([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function logout(Request $request): SuccessResource
    {
        $request->user()?->currentAccessToken()?->delete();

        return new SuccessResource(['message' => 'Logout successful.']);
    }

    public function user(Request $request): SuccessResource
    {
        return new SuccessResource(['user' => $request->user()]);
    }

    private function errorResponse(array $payload, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $payload['message'],
            'code' => $payload['code'],
            'errors' => [],
            'trace_id' => (string) str()->uuid(),
        ], $status);
    }
}
