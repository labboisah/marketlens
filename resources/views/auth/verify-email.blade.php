@extends('layouts.auth')

@section('content')
  <div>
    <h1 class="text-2xl font-bold">Verify your email</h1>
    <p class="mt-3 text-sm leading-6 text-muted">Email verification is required before business onboarding and business-record access.</p>

    @if (session('status') === 'verification-link-sent')
      <div class="mt-5 rounded-md border border-success/20 bg-success/10 p-3 text-sm text-success">A new verification link has been sent.</div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
      @csrf
      <button type="submit" class="inline-flex h-11 items-center justify-center rounded-md bg-accent px-5 text-sm font-semibold text-white">Resend verification email</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4">
      @csrf
      <button type="submit" class="text-sm text-muted hover:text-foreground">Logout</button>
    </form>
  </div>
@endsection
