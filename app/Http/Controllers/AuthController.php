<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // Handle the registration form submission
    public function register(Request $request)
    {
        // 1. Validate the incoming data
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed', // 'confirmed' checks if password matches password_confirmation
        ]);

        // 2. Create the user in the database
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password), // Always hash passwords!
        ]);

        // 3. Log the user in automatically
        Auth::login($user);

        // 4. Redirect them to the homepage (or dashboard)
        return redirect('/')->with('success', 'Account created successfully!');
    }

    /**
     * Handle the login form submission. Only failed logins count towards the limits
     * (5 a minute per email and IP, 50 a minute per IP), so a class logging in together
     * from one campus network is never blocked, but password guessing is slowed down.
     */
    public function login(Request $request): RedirectResponse
    {
        // 1. Validate the inputs
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $emailKey = 'login:'.Str::lower($credentials['email']).'|'.$request->ip();
        $ipKey = 'login-ip:'.$request->ip();

        if (RateLimiter::tooManyAttempts($emailKey, 5) || RateLimiter::tooManyAttempts($ipKey, 50)) {
            return back()->withErrors([
                'email' => 'Too many attempts. Please wait a minute and try again.',
            ])->onlyInput('email');
        }

        // 2. Attempt to log in
        if (Auth::attempt($credentials)) {
            RateLimiter::clear($emailKey);
            $request->session()->regenerate(); // Security measure

            return redirect()->intended('/')->with('success', 'Logged in successfully!');
        }

        // 3. If login fails, count it and go back with an error
        RateLimiter::hit($emailKey);
        RateLimiter::hit($ipKey);

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    // Handle logout
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}