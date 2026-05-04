<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categoryIds = Category::query()->pluck('id')->toArray();
        $brandIds = Brand::query()->pluck('id')->toArray();

        if (empty($categoryIds)) {
            $this->command->error('No category found. Please seed categories first.');
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

            Product::create([
                'category_id' => $categoryIds[array_rand($categoryIds)],

                // brand_id nullable, brand না থাকলে null যাবে
                'brand_id' => ! empty($brandIds)
                    ? $brandIds[array_rand($brandIds)]
                    : null,

                'name' => $productName,
                'slug' => $this->generateUniqueSlug($productName),
                'product_code' => $this->generateProductCode($index + 1),

                'purchase_price' => random_int(200, $newPrice - 50),
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

                'status' => true,

                'meta_title' => $productName,
                'meta_description' => 'Buy ' . $productName . ' at the best price.',
            ]);
        }
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $count = 1;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $count;
            $count++;
        }

        return $slug;
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