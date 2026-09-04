@extends('layouts.auth')

@section('content')
  <div class="text-center">
    <h1 class="text-2xl font-bold">Onboarding complete</h1>
    <p class="mt-3 text-sm leading-6 text-muted">Your business, primary branch, owner access, and trial subscription are ready.</p>
    <a href="{{ route('platform.dashboard') }}" class="mt-6 inline-flex h-11 items-center justify-center rounded-md bg-accent px-5 text-sm font-semibold text-white">Go to dashboard</a>
  </div>
@endsection
