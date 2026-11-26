<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const TOO_MANY = 'Too many attempts. Please wait a minute and try again.';

    public function test_login_is_blocked_after_five_failures_even_with_the_right_password(): void
    {
        $user = User::factory()->create(['email' => 'student@example.com']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertSessionHasErrors(['email' => 'The provided credentials do not match our records.']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => self::TOO_MANY]);

        $this->assertGuest();
    }

    public function test_login_is_blocked_after_fifty_failures_from_one_ip_across_accounts(): void
    {
        $this->failLoginsAcrossAccounts(50);

        $this->post('/login', ['email' => 'student99@example.com', 'password' => 'Password123'])
            ->assertSessionHasErrors(['email' => self::TOO_MANY]);
    }

    public function test_successful_logins_from_one_network_are_never_counted(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 55; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');
        }

        $this->assertAuthenticatedAs($user);
    }

    public function test_visitors_behind_the_same_proxy_are_limited_separately(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1']);

        $this->withHeader('X-Forwarded-For', '203.0.113.10');
        $this->failLoginsAcrossAccounts(50);
        $this->post('/login', ['email' => 'student99@example.com', 'password' => 'Password123'])
            ->assertSessionHasErrors(['email' => self::TOO_MANY]);

        $this->withHeader('X-Forwarded-For', '198.51.100.20');
        $this->post('/login', ['email' => 'student99@example.com', 'password' => 'Password123'])
            ->assertSessionDoesntHaveErrors(['email' => self::TOO_MANY]);
    }

    public function test_register_is_blocked_after_thirty_attempts_from_one_ip(): void
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->post('/register', ['name' => 'Spammer', 'email' => 'not-an-email'])
                ->assertSessionDoesntHaveErrors(['email' => self::TOO_MANY]);
        }

        $this->post('/register', ['name' => 'Spammer', 'email' => 'not-an-email'])
            ->assertSessionHasErrors(['email' => self::TOO_MANY]);
    }

    private function failLoginsAcrossAccounts(int $count): void
    {
        for ($attempt = 0; $attempt < $count; $attempt++) {
            $this->post('/login', ['email' => "student{$attempt}@example.com", 'password' => 'Password123'])
                ->assertSessionDoesntHaveErrors(['email' => self::TOO_MANY]);
        }
    }
}
