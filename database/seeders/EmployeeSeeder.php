<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class EmployeeSeeder extends Seeder
{
    /** Demo accounts are opt-in and may never be seeded outside local/testing. */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! filter_var(env('SEED_DEMO_EMPLOYEES', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->command?->warn('Demo employee seeding skipped (disabled by default).');

            return;
        }

        $password = (string) env('DEMO_EMPLOYEE_PASSWORD', '');

        if (mb_strlen($password) < 12) {
            throw new InvalidArgumentException('DEMO_EMPLOYEE_PASSWORD must be explicitly supplied with at least 12 characters.');
        }

        foreach ([
            ['name' => 'Test Employee', 'email' => 'employee@test.com'],
            ['name' => 'Demo Employee', 'email' => 'demo@employee.com'],
        ] as $employee) {
            User::firstOrCreate(['email' => $employee['email']], [
                'name' => $employee['name'],
                'password' => Hash::make($password),
                'role' => User::ROLE_EMPLOYEE,
                'is_active' => true,
            ]);
        }

        $this->command?->info('Opt-in demo accounts prepared. Existing passwords were preserved.');
    }
}
