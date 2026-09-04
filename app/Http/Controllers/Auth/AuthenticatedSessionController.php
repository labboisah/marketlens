<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Owners\Models\BusinessOwner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => __('Too many login attempts. Please try again later.'),
            ]);
        }

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        $request->session()->regenerate();
        RateLimiter::clear($key);

        $user = $request->user();

        AuditLog::query()->create([
            'actor_id' => $user->id,
            'action' => 'platform_user.logged_in',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'metadata' => ['remember' => $request->boolean('remember')],
        ]);

        if ($user->hasDisabledAccount()) {
            Auth::logout();

            return redirect()->route('account.disabled');
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if (! $user->hasActiveAccount()) {
            return redirect()->route('account.disabled');
        }

        if ($user->isSuperAdmin()) {
            return redirect()->route('super-admin.dashboard');
        }

        if (! BusinessOwner::query()->where('user_id', $user->id)->exists()) {
            return redirect()->intended(route('onboarding.wizard'));
        }

        return redirect()->intended(route('platform.dashboard'));
    }

    public function destroy(): RedirectResponse
    {
        $user = request()->user();

        if ($user) {
            AuditLog::query()->create([
                'actor_id' => $user->id,
                'action' => 'platform_user.logged_out',
                'subject_type' => User::class,
                'subject_id' => $user->id,
            ]);
        }

        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function throttleKey(LoginRequest $request): string
    {
        return strtolower($request->string('email')->toString()).'|'.$request->ip();
    }
}
