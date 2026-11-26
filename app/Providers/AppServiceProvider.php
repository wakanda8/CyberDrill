<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
{
    if (config('app.env') === 'production') {
        URL::forceScheme('https');
    }

    $this->configureRateLimiting();
}

    /**
     * Slow down password guessing and mass sign-ups. Login allows 5 tries per email and IP,
     * so one person behind a shared network can't lock everyone else out, plus 20 per IP,
     * so one address can't try a common password against many accounts.
     */
    private function configureRateLimiting(): void
    {
        $tooMany = 'Too many attempts. Please wait a minute and try again.';
        $loginResponse = fn () => back()->withErrors(['email' => $tooMany])->onlyInput('email');

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)
                ->by(Str::lower((string) $request->input('email')).'|'.$request->ip())
                ->response($loginResponse),
            Limit::perMinute(20)->by($request->ip())->response($loginResponse),
        ]);

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)
            ->by($request->ip())
            ->response(fn () => back()->withErrors(['email' => $tooMany])->onlyInput('name', 'email')));
    }
}
