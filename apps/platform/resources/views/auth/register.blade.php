@extends('layouts.auth')

@section('content')
  <div>
    <h1 class="text-2xl font-bold">Create your MarketLens account</h1>
    <p class="mt-2 text-sm text-muted">For business owners and Monitoring users. Shop staff are registered in the Shop App.</p>

    <form method="POST" action="{{ route('register') }}" class="mt-6 grid gap-4">
      @csrf
      <label class="grid gap-2 text-sm font-medium">
        Full name
        <input name="name" value="{{ old('name') }}" required autocomplete="name" class="ml-field">
        @error('name') <span class="text-xs text-danger">{{ $message }}</span> @enderror
      </label>

      <label class="grid gap-2 text-sm font-medium">
        Email
        <input name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="ml-field">
        @error('email') <span class="text-xs text-danger">{{ $message }}</span> @enderror
      </label>

      <label class="grid gap-2 text-sm font-medium">
        Phone
        <input name="phone" value="{{ old('phone') }}" required autocomplete="tel" class="ml-field">
        @error('phone') <span class="text-xs text-danger">{{ $message }}</span> @enderror
      </label>

      <label class="grid gap-2 text-sm font-medium">
        Password
        <input name="password" type="password" required autocomplete="new-password" class="ml-field">
        @error('password') <span class="text-xs text-danger">{{ $message }}</span> @enderror
      </label>

      <label class="grid gap-2 text-sm font-medium">
        Password confirmation
        <input name="password_confirmation" type="password" required autocomplete="new-password" class="ml-field">
      </label>

      <label class="flex items-start gap-2 text-sm text-muted">
        <input name="terms" type="checkbox" value="1" class="mt-1 h-4 w-4 rounded border-border">
        <span>I accept the <a href="/terms" class="text-accent">terms</a>.</span>
      </label>
      @error('terms') <span class="text-xs text-danger">{{ $message }}</span> @enderror

      <label class="flex items-start gap-2 text-sm text-muted">
        <input name="privacy" type="checkbox" value="1" class="mt-1 h-4 w-4 rounded border-border">
        <span>I accept the <a href="/privacy" class="text-accent">privacy policy</a>.</span>
      </label>
      @error('privacy') <span class="text-xs text-danger">{{ $message }}</span> @enderror

      <button type="submit" class="inline-flex h-11 items-center justify-center rounded-md bg-accent px-5 text-sm font-semibold text-white">Register</button>
    </form>

    <p class="mt-5 text-sm text-muted">Already have an account? <a href="{{ route('login') }}" class="text-accent">Login</a></p>
  </div>
@endsection
