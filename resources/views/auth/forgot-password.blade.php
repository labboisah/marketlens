@extends('layouts.auth')

@section('content')
  <div>
    <h1 class="text-2xl font-bold">Reset your password</h1>
    <p class="mt-2 text-sm leading-6 text-muted">Enter your email address and we will send a password reset link if the account exists.</p>

    @if (session('status'))
      <div class="mt-5 rounded-md border border-success/20 bg-success/10 p-3 text-sm text-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 grid gap-4">
      @csrf
      <label class="grid gap-2 text-sm font-medium">
        Email
        <input name="email" type="email" value="{{ old('email') }}" required autofocus class="ml-field">
        @error('email') <span class="text-xs text-danger">{{ $message }}</span> @enderror
      </label>
      <button type="submit" class="inline-flex h-11 items-center justify-center rounded-md bg-accent px-5 text-sm font-semibold text-white">Send reset link</button>
    </form>
  </div>
@endsection
