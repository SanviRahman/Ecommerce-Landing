<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        
        User::create([
            'name' => 'Test Employee',
            'email' => 'employee@test.com',
            'password' => Hash::make('12345678'),
            'role' => 'employee',
        ]);

        User::create([
            'name' => 'Demo Employee',
            'email' => 'demo@employee.com',
            'password' => Hash::make('12345678'),
            'role' => 'employee',
        ]);
    }
}