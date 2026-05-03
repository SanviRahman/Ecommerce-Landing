<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$product = App\Models\Product::first();
if ($product) {
    try {
        $product->delete();
        echo "Deleted. Trashed: " . ($product->trashed() ? "Yes" : "No") . "\n";
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "No product found.\n";
}

$category = App\Models\Category::first();
if ($category) {
    try {
        $category->delete();
        echo "Deleted. Trashed: " . ($category->trashed() ? "Yes" : "No") . "\n";
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "No category found.\n";
}
