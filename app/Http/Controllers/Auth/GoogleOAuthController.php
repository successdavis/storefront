<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\GoogleAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GoogleOAuthController extends Controller
{
    public function __construct(
        protected GoogleAuthService $googleAuthService,
    ) {}

    public function redirect(): RedirectResponse
    {
        return redirect()->away($this->googleAuthService->redirectUrl());
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $user = $this->googleAuthService->authenticate($request->query());

            Auth::login($user, true);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard', absolute: false));
        } catch (\Throwable $exception) {
            if (method_exists($exception, 'errors')) {
                return redirect()->route('login')->withErrors($exception->errors())->with('error', collect($exception->errors())->flatten()->first());
            }

            report($exception);

            return redirect()->route('login')->with('error', 'Unable to complete Google sign-in right now.');
        }
    }
}
