<?php

use App\Livewire\Platform\Administration\Dashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('platform.dashboard');
});

Route::get('/admin/dashboard', Dashboard::class)->name('platform.dashboard');
