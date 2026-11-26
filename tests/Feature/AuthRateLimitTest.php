<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const TOO_MANY = 'Too many attempts. Please wait a minute and try again.';

    public function test_login_is_blocked_after_five_attempts_even_with_the_right_password(): void
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

    public function test_login_is_blocked_after_twenty_attempts_from_one_ip_across_accounts(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->post('/login', ['email' => "student{$attempt}@example.com", 'password' => 'Password123'])
                ->assertSessionDoesntHaveErrors(['email' => self::TOO_MANY]);
        }

        $this->post('/login', ['email' => 'student99@example.com', 'password' => 'Password123'])
            ->assertSessionHasErrors(['email' => self::TOO_MANY]);
    }

    public function test_register_is_blocked_after_five_attempts(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/register', ['name' => 'Spammer', 'email' => 'not-an-email'])
                ->assertSessionDoesntHaveErrors(['email' => self::TOO_MANY]);
        }

        $this->post('/register', ['name' => 'Spammer', 'email' => 'not-an-email'])
            ->assertSessionHasErrors(['email' => self::TOO_MANY]);
    }
}
