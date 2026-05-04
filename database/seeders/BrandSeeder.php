<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Brand;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $brands = ['Apple', 'Samsung', 'Nike', 'Sony'];

        foreach ($brands as $brand) {
            Brand::create([
                'name' => $brand,
                'slug' => Str::slug($brand),
                'status' => 1,
            ]);
        }
    }
}