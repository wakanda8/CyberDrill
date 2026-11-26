<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_a_visitor_supplied_forwarded_host_does_not_change_redirect_urls(): void
    {
        $response = $this->get('/profile', ['X-Forwarded-Host' => 'evil.example']);

        $response->assertHeader('Location', 'http://localhost/login');
    }

    public function test_the_forwarded_protocol_from_the_proxy_is_trusted(): void
    {
        $response = $this->get('/profile', ['X-Forwarded-Proto' => 'https']);

        $response->assertHeader('Location', 'https://localhost/login');
    }
}
