<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_pages_send_the_browser_security_headers(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'")
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_error_pages_also_send_the_headers(): void
    {
        $response = $this->get('/no-such-page');

        $response->assertNotFound()->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_responses_stopped_by_earlier_middleware_also_send_the_headers(): void
    {
        $response = $this->call('POST', '/login', server: ['CONTENT_LENGTH' => PHP_INT_MAX]);

        $response->assertStatus(413)->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_hsts_is_sent_only_over_https(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

        $this->get('/login', ['X-Forwarded-Proto' => 'https'])
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
