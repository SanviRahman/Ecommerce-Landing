<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Electronics',
            'Clothing',
            'Home & Kitchen',
            'Beauty',
            'Organic Food',
            'Natural Oil',
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                [
                    'slug' => Str::slug($category),
                ],
                [
                    'name' => $category,
                    'status' => true,
                ]
            );
        }

        $this->command?->info('Categories seeded successfully.');
    }
}