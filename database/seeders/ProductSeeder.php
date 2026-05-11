<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categoryIds = Category::query()->pluck('id')->toArray();
        $brandIds = Brand::query()->pluck('id')->toArray();

        if (empty($categoryIds)) {
            $this->command?->error('No category found. Please seed categories first.');
            return;
        }

        $products = [
            'Premium Honey',
            'Organic Black Seed Oil',
            'Herbal Hair Oil',
            'Natural Face Wash',
            'Aloe Vera Gel',
            'Vitamin C Serum',
            'Organic Tea',
            'Leather Wallet',
            'Smart Watch',
            'Bluetooth Headphone',
            'Cotton T-Shirt',
            'Running Shoes',
            'Travel Backpack',
            'Kitchen Knife Set',
            'Water Bottle',
        ];

        foreach ($products as $index => $productName) {
            $oldPrice = random_int(800, 2500);
            $newPrice = random_int(400, $oldPrice - 100);

            Product::updateOrCreate(
                [
                    'slug' => Str::slug($productName),
                ],
                [
                    'category_id' => $categoryIds[array_rand($categoryIds)],

                    'brand_id' => ! empty($brandIds)
                        ? $brandIds[array_rand($brandIds)]
                        : null,

                    'name' => $productName,
                    'product_code' => $this->generateProductCode($index + 1),

                    'purchase_price' => random_int(200, max(250, $newPrice - 50)),
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,

                    'stock' => random_int(10, 100),
                    'sold_quantity' => random_int(0, 30),

                    'weight_size' => $this->randomWeightSize(),

                    'short_description' => $productName . ' is a high quality product for daily use.',
                    'full_description' => $productName . ' is carefully selected and quality checked. This product is suitable for customers who want reliable and premium quality products.',

                    'is_top_sale' => $index % 3 === 0,
                    'is_feature' => $index % 2 === 0,
                    'is_flash_sale' => $index % 4 === 0,

                    /*
                    |--------------------------------------------------------------------------
                    | New Free Delivery Field
                    |--------------------------------------------------------------------------
                    | কিছু product free delivery হবে।
                    */
                    'is_free_delivery' => in_array($index, [0, 2, 4, 6, 10], true),

                    'status' => true,

                    'meta_title' => $productName,
                    'meta_description' => 'Buy ' . $productName . ' at the best price.',
                ]
            );
        }

        $this->command?->info('Products seeded successfully with free delivery products.');
    }

    private function generateProductCode(int $number): string
    {
        do {
            $code = 'PROD-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(Str::random(4));
        } while (Product::where('product_code', $code)->exists());

        return $code;
    }

    private function randomWeightSize(): string
    {
        $sizes = [
            '100ml',
            '250ml',
            '500ml',
            '1kg',
            'Small',
            'Medium',
            'Large',
            'XL',
            'Free Size',
        ];

        return $sizes[array_rand($sizes)];
    }
}