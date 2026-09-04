<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\Models\AuditLog;
use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        if ($request->user()->account_status === AccountStatus::PendingVerification) {
            $request->user()->forceFill(['account_status' => AccountStatus::Active])->save();
        }

        AuditLog::query()->create([
            'actor_id' => $request->user()->id,
            'action' => 'platform_user.email_verified',
            'subject_type' => User::class,
            'subject_id' => $request->user()->id,
        ]);

        return redirect()->route($request->user()->isSuperAdmin() ? 'super-admin.dashboard' : 'onboarding.wizard');
    }
}
