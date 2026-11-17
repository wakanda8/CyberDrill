<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:make-admin {email}', function (string $email) {
    // Case-insensitive, because PostgreSQL (production) compares emails case-sensitively
    $user = User::whereRaw('LOWER(email) = ?', [Str::lower($email)])->first();

    if (! $user) {
        $this->error("No user found with email {$email}. Register the account on the site first.");

        return 1;
    }

    // is_admin is deliberately not in $fillable, so set it with forceFill
    $user->forceFill(['is_admin' => true])->save();

    $this->info("{$user->name} ({$email}) is now an admin.");
})->purpose('Give an existing user admin access');
