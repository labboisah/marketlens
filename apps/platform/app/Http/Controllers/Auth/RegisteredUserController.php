<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\Models\AuditLog;
use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->lower()->toString(),
            'phone' => $request->string('phone')->toString(),
            'password' => $request->string('password')->toString(),
            'account_status' => AccountStatus::PendingVerification,
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
        ]);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        AuditLog::query()->create([
            'actor_id' => $user->id,
            'action' => 'platform_user.registered',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'metadata' => ['email' => $user->email],
        ]);

        return redirect()->route('verification.notice');
    }
}
