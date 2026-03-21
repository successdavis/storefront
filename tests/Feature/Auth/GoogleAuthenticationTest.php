<?php

namespace Tests\Feature\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'google-client-id',
            'services.google.client_secret' => 'google-client-secret',
        ]);
    }

    public function test_google_callback_creates_a_customer_account_when_email_is_new(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token',
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-user-1',
                'email' => 'new-google-user@example.com',
                'email_verified' => true,
                'name' => 'New Google User',
                'picture' => 'https://example.com/avatar.png',
            ]),
        ]);

        $response = $this
            ->withSession(['oauth.google.state' => 'expected-state'])
            ->get(route('auth.google.callback', [
                'state' => 'expected-state',
                'code' => 'oauth-code',
            ]));

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'new-google-user@example.com',
        ]);
        $this->assertDatabaseHas('social_accounts', [
            'provider' => 'google',
            'provider_user_id' => 'google-user-1',
            'provider_email' => 'new-google-user@example.com',
        ]);
    }

    public function test_google_callback_auto_links_a_verified_existing_email(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.com',
        ]);
        $user->syncRoles([RoleNames::CUSTOMER]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token',
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-user-2',
                'email' => 'existing@example.com',
                'email_verified' => true,
                'name' => 'Existing User',
            ]),
        ]);

        $response = $this
            ->withSession(['oauth.google.state' => 'expected-state'])
            ->get(route('auth.google.callback', [
                'state' => 'expected-state',
                'code' => 'oauth-code',
            ]));

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);

        $this->assertTrue(SocialAccount::query()
            ->where('user_id', $user->id)
            ->where('provider', 'google')
            ->where('provider_user_id', 'google-user-2')
            ->exists());
    }
}
