<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$user = App\Models\User::where('role', 'admin')->first() ?: App\Models\User::first();

// simulate authentication
auth()->login($user);

$product = App\Models\Product::first();
if (!$product) {
    die("No product\n");
}

$request = Illuminate\Http\Request::create('/admin/products/' . $product->id, 'DELETE');
$request->headers->set('X-Requested-With', 'XMLHttpRequest');

$response = $kernel->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Content: " . $response->getContent() . "\n";
