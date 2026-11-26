<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

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
     * Slow down mass sign-ups: 30 a minute per IP, which leaves room for a class
     * registering together from one campus network. Login is limited in AuthController,
     * where only failed attempts are counted.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(30)
            ->by($request->ip())
            ->response(fn () => back()
                ->withErrors(['email' => 'Too many attempts. Please wait a minute and try again.'])
                ->onlyInput('name', 'email')));
    }
}
