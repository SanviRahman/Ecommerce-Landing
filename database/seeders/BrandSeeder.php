<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = ['Apple', 'Samsung', 'Nike', 'Sony'];

        foreach ($brands as $brand) {
            Brand::updateOrCreate(
                [
                    'slug' => Str::slug($brand),
                ],
                [
                    'name' => $brand,
                    'status' => true,
                ]
            );
        }

        $this->command?->info('Brands seeded successfully.');
    }
}