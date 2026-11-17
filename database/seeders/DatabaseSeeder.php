<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PhishingScenarioSeeder::class,
            QuestionSeeder::class,      // malware scenarios
            SpamQuestionSeeder::class,
        ]);

        // Ready-made admin for local development only (never in production)
        if (app()->isLocal()) {
            $admin = User::firstOrNew(['email' => 'test@example.com']);

            // Only set the password on first creation, so re-seeding doesn't re-hash and rewrite it
            if (! $admin->exists) {
                $admin->password = 'password'; // hashed by the User model's cast
            }

            $admin->forceFill(['name' => 'Test User', 'is_admin' => true])->save();
        }
    }
}
