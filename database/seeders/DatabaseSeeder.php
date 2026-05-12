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
        | Courier API Accounts
        |--------------------------------------------------------------------------
        */
        $this->call([
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
        */
        $this->call([
            OrderSeeder::class,
            BulkOrderSeeder::class,
            ReportExportSeeder::class,
        ]);

        $this->call([
            CourierSeeder::class,
        ]);
    }
}
