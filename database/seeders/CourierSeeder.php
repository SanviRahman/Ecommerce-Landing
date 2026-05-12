<?php

namespace Database\Seeders;

use App\Models\Courier;
use Illuminate\Database\Seeder;

class CourierSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Courier List For Order Dropdown
        |--------------------------------------------------------------------------
        | এই courier list admin order courier dropdown/filter এ show করবে।
        | API credential CourierAccountSeeder থেকে manage হবে।
        |--------------------------------------------------------------------------
        */
        $couriers = [
            [
                'name' => 'SteadFast Courier',
                'code' => 'steadfast',
                'status' => true,
            ],
            [
                'name' => 'Pathao Courier',
                'code' => 'pathao',
                'status' => true,
            ],
        ];

        foreach ($couriers as $courier) {
            Courier::updateOrCreate(
                [
                    'code' => $courier['code'],
                ],
                [
                    'name' => $courier['name'],
                    'status' => $courier['status'],
                ]
            );
        }

        $this->command?->info('Courier list seeded successfully.');
    }
}