<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Users
        |--------------------------------------------------------------------------
        */
        $this->call([
            AdminUserSeeder::class,
            EmployeeSeeder::class,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Product Base Data
        |--------------------------------------------------------------------------
        */
        $this->call([
            CategorySeeder::class,
            BrandSeeder::class,
            ProductSeeder::class,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Courier Data
        |--------------------------------------------------------------------------
        | CourierSeeder = normal courier dropdown/list
        | CourierAccountSeeder = courier API credentials/accounts
        |--------------------------------------------------------------------------
        */
        $this->call([
            CourierSeeder::class,
            CourierAccountSeeder::class,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Landing / Frontend Data
        |--------------------------------------------------------------------------
        */
        $this->call([
            CampaignSeeder::class,
            BannerSeeder::class,
            FaqSeeder::class,
            ReviewSeeder::class,
            SocialMediaSeeder::class,
            PageSeeder::class,
            TrackingPixelSeeder::class,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Orders / Reports
        |--------------------------------------------------------------------------
        | OrderSeeder depends on products, campaigns and couriers.
        |--------------------------------------------------------------------------
        */
        $this->call([
            OrderSeeder::class,
            BulkOrderSeeder::class,
            ReportExportSeeder::class,
        ]);
    }
}