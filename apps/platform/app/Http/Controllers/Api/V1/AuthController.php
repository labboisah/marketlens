<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SuccessResource;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(): SuccessResource
    {
        return new SuccessResource(['message' => 'Monitoring authentication endpoint placeholder.']);
    }

    public function logout(): SuccessResource
    {
        return new SuccessResource(['message' => 'Logout endpoint placeholder.']);
    }

    public function user(Request $request): SuccessResource
    {
        return new SuccessResource(['user' => $request->user()]);
    }
}
