<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Only let users with is_admin = true through.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_admin) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
