<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Browser security headers sent with every response. No other site may show CyberDrill
     * in a frame (stops clickjacking of admin actions): CSP frame-ancestors for current
     * browsers, X-Frame-Options for older ones.
     *
     * @var array<string, string>
     */
    private const HEADERS = [
        'X-Frame-Options' => 'DENY',
        'Content-Security-Policy' => "frame-ancestors 'none'",
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ];

    /**
     * Add the security headers, plus HSTS when the request came in over HTTPS.
     * HSTS covers this host only (no includeSubDomains), so other hpcs.my sites are unaffected.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->add(self::HEADERS);

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
