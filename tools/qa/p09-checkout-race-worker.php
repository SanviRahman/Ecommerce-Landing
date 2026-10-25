<?php

declare(strict_types=1);

/** Spawned ONLY by the guarded P09 test runner; never exposed through routes. */
function p09WorkerFail(string $code): never
{
    fwrite(STDOUT, 'P09_RESULT:ERROR:' . $code . PHP_EOL);
    exit(1);
}

if (PHP_SAPI !== 'cli' || getenv('P09_RACE_WORKER') !== 'SYNTHETIC_ONLY'
    || getenv('P09_RACE_WRITE_APPROVED') !== 'YES_SYNTHETIC_COMMIT_AND_CLEANUP'
    || getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'p01_campaign_test'
    || getenv('DB_USERNAME') !== 'p01_tester' || getenv('DB_HOST') !== '127.0.0.1') {
    p09WorkerFail('GUARD');
}
$ownerHash = (string) getenv('P09_RACE_OWNER_HASH');
$key = (string) getenv('P09_RACE_KEY');
$phone = (string) getenv('P09_RACE_PHONE');
$shippingId = (int) getenv('P09_RACE_SHIPPING_ID');
$version = (int) getenv('P09_RACE_CART_VERSION');
$gate = (string) getenv('P09_RACE_GATE');
$ready = (string) getenv('P09_RACE_READY');
if (! preg_match('/\A[a-f0-9]{64}\z/D', $ownerHash)
    || ! preg_match('/\A[0-9a-f-]{36}\z/D', $key)
    || ! preg_match('/\A01[0-9]{9}\z/D', $phone)
    || $shippingId < 1 || $version !== 2 || $gate === '' || $ready === '') {
    p09WorkerFail('PARAMETERS');
}
try {
    $root = dirname(__DIR__, 2);
    require_once $root . '/vendor/autoload.php';
    $app = require $root . '/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    \App\Support\P02MigrationSafety::assertIsolatedTestDatabase();
    config()->set('storefront.p07_catalog_test_enabled', true);
    config()->set('storefront.p08_cart_test_enabled', true);
    config()->set('storefront.p08_checkout_test_enabled', true);
    config()->set('storefront.p03_core_test_enabled', true);

    // The fixture is committed on purpose: unlike DatabaseTransactions tests,
    // two independent clients can now see exactly the same last available SKU.
    // Synthetic orders MUST NOT trigger external API sync after commit.
    \App\Models\Order::unsetEventDispatcher();
    // Do not reassign employee cursors during a committed synthetic test.
    app()->bind(\App\Services\OrderAssignmentService::class,
        static fn () => new class extends \App\Services\OrderAssignmentService {
            public function assign(\App\Models\Order $order): ?\App\Models\User
            {
                return null;
            }
        });
    \Illuminate\Support\Facades\DB::statement('SET SESSION innodb_lock_wait_timeout = 5');

    if (file_put_contents($ready, 'ready', LOCK_EX) === false) {
        p09WorkerFail('READY_FILE');
    }
    $deadline = microtime(true) + 18;
    while (! is_file($gate) && microtime(true) < $deadline) {
        clearstatcache(true, $gate);
        usleep(10000);
    }
    if (! is_file($gate)) {
        p09WorkerFail('BARRIER_TIMEOUT');
    }

    $request = \Illuminate\Http\Request::create('http://127.0.0.1/storefront/checkout', 'POST');
    $data = [
        'customer_name' => 'P09 Synthetic Buyer', 'phone' => $phone,
        'address' => 'P09 local race test synthetic address', 'customer_note' => '',
        'shipping_area_id' => $shippingId, 'cart_version' => $version,
        'checkout_key' => $key,
    ];
    try {
        app(\App\Services\Checkout\StorefrontCheckout::class)->place($request, $ownerHash, $data);
        echo 'P09_RESULT:SUCCESS' . PHP_EOL;
        exit(0);
    } catch (\Illuminate\Validation\ValidationException $exception) {
        $errors = $exception->errors();
        // One client must lose only because the final stock is no longer there.
        $cart = implode(' ', $errors['cart'] ?? []);
        if (str_contains($cart, 'out of stock') || str_contains($cart, 'insufficient stock')) {
            echo 'P09_RESULT:STOCK_REJECTED' . PHP_EOL;
            exit(0);
        }
        p09WorkerFail('UNEXPECTED_VALIDATION');
    } catch (\DomainException $exception) {
        if (str_contains($exception->getMessage(), 'Insufficient stock for this variant.')
            || str_contains($exception->getMessage(), 'Concurrent checkout stock change:')) {
            echo 'P09_RESULT:STOCK_REJECTED' . PHP_EOL;
            exit(0);
        }
        p09WorkerFail('UNEXPECTED_DOMAIN_ERROR');
    }
} catch (Throwable $exception) {
    // Synthetic-only workers expose no secret, schema connection string or token.
    p09WorkerFail('UNEXPECTED_' . strtoupper((new \ReflectionClass($exception))->getShortName()));
}
