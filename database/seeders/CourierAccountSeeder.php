<?php

namespace Database\Seeders;

use App\Models\CourierAccount;
use Illuminate\Database\Seeder;

class CourierAccountSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | SteadFast Courier
        |--------------------------------------------------------------------------
        */
        CourierAccount::updateOrCreate(
            [
                'code' => 'steadfast',
            ],
            [
                'name' => 'SteadFast Courier',
                'base_url' => 'https://portal.packzy.com/api/v1',

                /*
                |--------------------------------------------------------------------------
                | Security Note
                |--------------------------------------------------------------------------
                | Real API key/secret seeder-e rakha safe na.
                | Admin Panel -> Courier API Accounts theke update korbe.
                */
                'api_key' => null,
                'secret_key' => null,
                'token' => null,

                'settings' => [
                    'store_id' => null,
                    'delivery_type' => null,
                    'item_type' => null,
                    'item_weight' => null,
                    'special_instruction' => null,
                ],

                'is_default' => true,
                'status' => true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Pathao Courier
        |--------------------------------------------------------------------------
        */
        CourierAccount::updateOrCreate(
            [
                'code' => 'pathao',
            ],
            [
                'name' => 'Pathao Courier',
                'base_url' => 'https://api-hermes.pathao.com',

                /*
                |--------------------------------------------------------------------------
                | Pathao uses Bearer access token.
                | Token, store_id Admin Panel theke update korbe.
                */
                'api_key' => null,
                'secret_key' => null,
                'token' => null,

                'settings' => [
                    'store_id' => null,
                    'delivery_type' => 48,
                    'item_type' => 2,
                    'item_weight' => 0.5,
                    'special_instruction' => 'Please call before delivery.',
                ],

                'is_default' => false,
                'status' => true,
            ]
        );

        $this->command?->info('Courier API accounts seeded successfully.');
    }
}