<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRedirectAndRememberTest extends TestCase
{
    use RefreshDatabase;

    protected function makeCustomer(): User
    {
        $customer = User::factory()->create(['password' => bcrypt('secret-password')]);
        $customer->syncRoles([RoleNames::CUSTOMER]);

        return $customer;
    }

    protected function login(User $user)
    {
        return $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);
    }

    public function test_login_returns_user_to_the_page_they_came_from(): void
    {
        $customer = $this->makeCustomer();

        $this->get(route('login', ['redirect' => '/store/product/some-item']));

        $this->login($customer)
            ->assertRedirect('/store/product/some-item');
    }

    public function test_login_without_redirect_goes_to_dashboard(): void
    {
        $customer = $this->makeCustomer();

        $this->get(route('login'));

        $this->login($customer)
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_external_redirect_targets_are_ignored(): void
    {
        $customer = $this->makeCustomer();

        $this->get(route('login', ['redirect' => 'https://evil.example.com/phish']));

        $this->login($customer)
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_protocol_relative_redirect_targets_are_ignored(): void
    {
        $customer = $this->makeCustomer();

        $this->get(route('login', ['redirect' => '//evil.example.com/phish']));

        $this->login($customer)
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_login_always_issues_a_remember_cookie(): void
    {
        $customer = $this->makeCustomer();

        $response = $this->login($customer);

        $rememberCookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));

        $this->assertNotNull($rememberCookie, 'Login should always set a remember cookie');
        $this->assertNotSame('', (string) $rememberCookie->getValue());
    }
}
