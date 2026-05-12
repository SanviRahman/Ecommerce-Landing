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
        | Reset Default Courier API Account
        |--------------------------------------------------------------------------
        | পুরোনো database এ Pathao default হয়ে থাকলে সেটা false করবে।
        | তারপর SteadFast কে default করবে।
        |--------------------------------------------------------------------------
        */
        CourierAccount::query()->update([
            'is_default' => false,
        ]);

        /*
        |--------------------------------------------------------------------------
        | SteadFast Courier API Account
        |--------------------------------------------------------------------------
        | Default courier API হিসেবে SteadFast থাকবে।
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
                | Real API key/secret seeder-e rakha safe na.
                | Admin Panel -> Courier API Accounts থেকে update করবে।
                |--------------------------------------------------------------------------
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
        | Pathao Courier API Account
        |--------------------------------------------------------------------------
        | Pathao active থাকবে, কিন্তু default হবে না।
        | Pathao send button click করলে Pathao-তে যাবে।
        |--------------------------------------------------------------------------
        */
        CourierAccount::updateOrCreate(
            [
                'code' => 'pathao',
            ],
            [
                'name' => 'Pathao Courier',
                'base_url' => 'https://api-hermes.pathao.com',

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
        $this->command?->info('Default Courier API: SteadFast Courier');
    }
}