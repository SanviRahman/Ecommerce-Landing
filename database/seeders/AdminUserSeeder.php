<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed an initial administrator only when explicitly configured.
     * Never publish a predictable default credential or reset an existing user.
     */
    public function run(): void
    {
        $email = trim((string) env('INITIAL_ADMIN_EMAIL', ''));
        $password = (string) env('INITIAL_ADMIN_PASSWORD', '');

        if ($email === '' || $password === '') {
            $this->command?->warn('Initial admin not seeded: set INITIAL_ADMIN_EMAIL and INITIAL_ADMIN_PASSWORD explicitly.');

            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 12) {
            throw new InvalidArgumentException('Initial admin requires a valid email and a password of at least 12 characters.');
        }

        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'role' => User::ROLE_ADMIN,
                'password' => Hash::make($password),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info('Initial admin seeding completed. Existing accounts and passwords were not changed.');
    }
}
