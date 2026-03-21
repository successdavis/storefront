<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleAuthService
{
    public function redirectUrl(): string
    {
        $state = Str::random(40);
        Session::put('oauth.google.state', $state);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => (string) config('services.google.client_id'),
            'redirect_uri' => route('auth.google.callback'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
            'access_type' => 'online',
        ]);
    }

    public function authenticate(array $query): User
    {
        if (!config('services.google.client_id') || !config('services.google.client_secret')) {
            throw ValidationException::withMessages([
                'google' => 'Google sign-in is not configured for this environment.',
            ]);
        }

        if (!empty($query['error'])) {
            throw ValidationException::withMessages([
                'google' => 'Google sign-in was cancelled or denied.',
            ]);
        }

        $expectedState = Session::pull('oauth.google.state');
        if (!$expectedState || !hash_equals((string) $expectedState, (string) ($query['state'] ?? ''))) {
            throw ValidationException::withMessages([
                'google' => 'Invalid Google sign-in state. Please try again.',
            ]);
        }

        $code = trim((string) ($query['code'] ?? ''));
        if ($code === '') {
            throw ValidationException::withMessages([
                'google' => 'Missing Google authorization code.',
            ]);
        }

        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => (string) config('services.google.client_id'),
            'client_secret' => (string) config('services.google.client_secret'),
            'redirect_uri' => route('auth.google.callback'),
            'grant_type' => 'authorization_code',
        ])->throw()->json();

        $accessToken = (string) ($tokenResponse['access_token'] ?? '');
        if ($accessToken === '') {
            throw ValidationException::withMessages([
                'google' => 'Unable to complete Google sign-in.',
            ]);
        }

        $profile = Http::withToken($accessToken)
            ->get('https://openidconnect.googleapis.com/v1/userinfo')
            ->throw()
            ->json();

        $providerUserId = (string) ($profile['sub'] ?? '');
        $email = Str::lower((string) ($profile['email'] ?? ''));
        $emailVerified = (bool) ($profile['email_verified'] ?? false);

        if ($providerUserId === '' || $email === '') {
            throw ValidationException::withMessages([
                'google' => 'Google account information is incomplete.',
            ]);
        }

        if (!$emailVerified) {
            throw ValidationException::withMessages([
                'google' => 'Your Google account email must be verified before you can sign in.',
            ]);
        }

        $socialAccount = SocialAccount::query()
            ->where('provider', 'google')
            ->where('provider_user_id', $providerUserId)
            ->with('user.roles')
            ->first();

        if ($socialAccount) {
            return $socialAccount->user;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (!$user) {
            $user = User::query()->create([
                'name' => (string) ($profile['name'] ?? 'Google User'),
                'email' => $email,
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(40)),
            ]);
        }

        if (!$user->hasAnyRole(RoleNames::all())) {
            $user->assignRole(RoleNames::CUSTOMER);
        }

        $user->socialAccounts()->updateOrCreate(
            ['provider' => 'google'],
            [
                'provider_user_id' => $providerUserId,
                'provider_email' => $email,
                'provider_name' => (string) ($profile['name'] ?? $user->name),
                'avatar_url' => $profile['picture'] ?? null,
                'meta' => $profile,
            ],
        );

        if (!$user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user->fresh('roles');
    }
}
