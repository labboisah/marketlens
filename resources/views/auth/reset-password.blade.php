@extends('layouts.auth')

@section('content')
  <div>
    <h1 class="text-2xl font-bold">Choose a new password</h1>
    <form method="POST" action="{{ route('password.store') }}" class="mt-6 grid gap-4">
      @csrf
      <input type="hidden" name="token" value="{{ $request->route('token') }}">

      <label class="grid gap-2 text-sm font-medium">
        Email
        <input name="email" type="email" value="{{ old('email', $request->email) }}" required autocomplete="email" class="ml-field">
        @error('email') <span class="text-xs text-danger">{{ $message }}</span> @enderror
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

      <button type="submit" class="inline-flex h-11 items-center justify-center rounded-md bg-accent px-5 text-sm font-semibold text-white">Reset password</button>
    </form>
  </div>
@endsection
