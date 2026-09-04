@extends('layouts.auth')

@section('content')
  <div>
    <h1 class="text-2xl font-bold">Login</h1>
    <p class="mt-2 text-sm text-muted">Access the MarketLens Platform or Monitoring App account.</p>

    @if (session('status'))
      <div class="mt-5 rounded-md border border-success/20 bg-success/10 p-3 text-sm text-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-6 grid gap-4">
      @csrf
      <label class="grid gap-2 text-sm font-medium">
        Email
        <input name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="ml-field">
        @error('email') <span class="text-xs text-danger">{{ $message }}</span> @enderror
      </label>

      <label class="grid gap-2 text-sm font-medium">
        Password
        <input name="password" type="password" required autocomplete="current-password" class="ml-field">
        @error('password') <span class="text-xs text-danger">{{ $message }}</span> @enderror
      </label>

      <label class="flex items-center gap-2 text-sm text-muted">
        <input name="remember" type="checkbox" value="1" class="h-4 w-4 rounded border-border">
        Remember me
      </label>

      <button type="submit" class="inline-flex h-11 items-center justify-center rounded-md bg-accent px-5 text-sm font-semibold text-white">Login</button>
    </form>

    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 text-sm">
      <a href="{{ route('password.request') }}" class="text-accent hover:underline">Forgot password?</a>
      <a href="{{ route('register') }}" class="text-muted hover:text-foreground">Create account</a>
    </div>
  </div>
@endsection
