<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            EmployeeSeeder::class,
            CategorySeeder::class,
            BrandSeeder::class,
            OrderSeeder::class,
            ProductSeeder::class,
            CampaignSeeder::class,
            TrackingPixelSeeder::class,
            PageSeeder::class,
            BannerSeeder::class,
            ReviewSeeder::class,
            FaqSeeder::class,
            SocialMediaSeeder::class,
            BulkOrderSeeder::class,
            ReportExportSeeder::class,
        ]);
    }
}
