<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        // A guest interrupted mid-action (e.g. add to cart) arrives with
        // ?redirect=<path>; store it as the intended URL so both the normal
        // and two-factor login flows return the user where they left off.
        $redirect = $this->safeInternalPath((string) $request->query('redirect', ''));
        if ($redirect !== null) {
            redirect()->setIntendedUrl($redirect);
        }

        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Only same-app relative paths are allowed as post-login destinations.
     */
    protected function safeInternalPath(string $path): ?string
    {
        if ($path === ''
            || !str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || str_contains($path, '\\')
        ) {
            return null;
        }

        return $path;
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->validateCredentials();

        if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                // Sessions are always remembered; users stay signed in until
                // they explicitly log out.
                'login.remember' => true,
            ]);

            return to_route('two-factor.login');
        }

        Auth::login($user, true);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        \App\Models\PosTerminal::where('id', session('pos_terminal_id'))
        ->update([
            'locked_by_employee_id' => null,
            'locked_at' => null,
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
