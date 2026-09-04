<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class OnboardingCompletedController extends Controller
{
    public function __invoke(): View
    {
        return view('onboarding.completed');
    }
}
