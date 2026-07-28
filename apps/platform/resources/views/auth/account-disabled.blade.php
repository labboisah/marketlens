@extends('layouts.auth')

@section('content')
  <div class="rounded-lg border border-danger/20 bg-danger/10 p-6">
    <h1 class="text-2xl font-bold text-foreground">Account access is unavailable</h1>
    <p class="mt-3 text-sm leading-6 text-muted">This account is suspended, disabled, or not yet active. It cannot access Platform business records or Monitoring App data.</p>
    <a href="{{ route('login') }}" class="mt-6 inline-flex h-11 items-center justify-center rounded-md bg-accent px-5 text-sm font-semibold text-white">Back to login</a>
  </div>
@endsection
