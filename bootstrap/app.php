<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // A proxy sits in front of the app: the Cloudflare Tunnel in compose.yaml (or Render's
        // proxy upstream). Trusting its X-Forwarded-For makes $request->ip() the visitor's address
        // (needed for per-visitor rate limits), and X-Forwarded-Proto marks requests as HTTPS.
        // Only those two headers are trusted: visitors can send their own X-Forwarded-Host, which
        // the proxy passes on, and trusting it would let them change the app's generated URLs.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );

        // First in the global stack, so early responses (maintenance 503, upload too large 413) get the headers too.
        $middleware->prepend(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
