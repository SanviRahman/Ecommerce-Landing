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
        | Courier List For Order Dropdown + Invoice Merchant Info
        |--------------------------------------------------------------------------
        | merchant_id এবং phone_number invoice এর left side business/courier info
        | section এ show হবে।
        |--------------------------------------------------------------------------
        */
        $couriers = [
            [
                'name' => 'SteadFast Courier',
                'code' => 'steadfast',
                'merchant_id' => null,
                'phone_number' => null,
                'status' => true,
            ],
            [
                'name' => 'Pathao Courier',
                'code' => 'pathao',
                'merchant_id' => null,
                'phone_number' => null,
                'status' => true,
            ],
            [
                'name' => 'Paperfly',
                'code' => 'paperfly',
                'merchant_id' => null,
                'phone_number' => null,
                'status' => true,
            ],
            [
                'name' => 'RedX',
                'code' => 'redx',
                'merchant_id' => null,
                'phone_number' => null,
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
                    'merchant_id' => $courier['merchant_id'],
                    'phone_number' => $courier['phone_number'],
                    'status' => $courier['status'],
                ]
            );
        }

        $this->command?->info('Courier list seeded successfully.');
    }
}