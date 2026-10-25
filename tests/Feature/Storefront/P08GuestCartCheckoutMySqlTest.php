<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Order;
use App\Services\Cart\GuestCartService;
use App\Services\Checkout\StorefrontCheckout;
use App\Support\P02MigrationSafety;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Rollback-only, restricted real MariaDB P08 cart/COD lifecycle tests. */
final class P08GuestCartCheckoutMySqlTest extends TestCase
{
    use DatabaseTransactions;

    private string $owner;

    protected function setUp(): void
    {
        parent::setUp();
        P02MigrationSafety::assertIsolatedTestDatabase();
        self::assertGreaterThan(0, DB::transactionLevel());
        config()->set('storefront.p07_catalog_test_enabled', true);
        config()->set('storefront.p08_cart_test_enabled', true);
        config()->set('storefront.p08_checkout_test_enabled', true);
        config()->set('storefront.p03_core_test_enabled', true);
        $this->owner = hash('sha256', 'p08-synthetic-guest-' . Str::random(32));
    }

    protected function tearDown(): void
    {
        config()->set('storefront.p08_checkout_test_enabled', false);
        config()->set('storefront.p08_cart_test_enabled', false);
        config()->set('storefront.p03_core_test_enabled', false);
        parent::tearDown();
    }

    /** @return array{product:int,variant:int,shipping:int} */
    private function fixture(int $stock = 8, bool $variable = false, bool $free = false): array
    {
        $key = strtolower(Str::random(16));
        $now = now();
        $category = (int) DB::table('categories')->insertGetId([
            'name' => 'P08 synthetic', 'slug' => 'p08-cat-' . $key,
            'status' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $product = (int) DB::table('products')->insertGetId([
            'category_id' => $category, 'name' => 'P08 synthetic product',
            'slug' => 'p08-prod-' . $key, 'product_code' => 'P08-' . $key,
            'stock' => $stock, 'new_price' => 750, 'status' => 1,
            'has_variants' => $variable ? 1 : 0, 'inventory_version' => 1,
            'is_free_delivery' => $free ? 1 : 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $signature = 'default';
        $option = $value = null;
        if ($variable) {
            $option = (int) DB::table('product_options')->insertGetId([
                'code' => 'p08-color-' . $key, 'name' => 'Color',
                'input_type' => 'select', 'is_active' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $value = (int) DB::table('product_option_values')->insertGetId([
                'product_option_id' => $option, 'code' => 'p08-green-' . $key,
                'label' => 'Green', 'is_active' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('product_option_assignments')->insert([
                'product_id' => $product, 'product_option_id' => $option,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $signature = $option . ':' . $value;
        }
        $variant = (int) DB::table('product_variants')->insertGetId([
            'product_id' => $product, 'sku' => 'P08-SKU-' . $key,
            'option_signature' => $signature, 'price_override' => $variable ? 830 : null,
            'stock' => $stock, 'is_default' => $variable ? 0 : 1,
            'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        if ($variable) {
            DB::table('product_variant_values')->insert([
                'product_variant_id' => $variant, 'product_option_id' => $option,
                'product_option_value_id' => $value,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        DB::table('inventory_movements')->insert([
            'product_variant_id' => $variant, 'event_uuid' => (string) Str::uuid(),
            'operation_key' => 'opening:v1:product:' . $product,
            'kind' => 'opening', 'quantity_delta' => $stock,
            'stock_before' => 0, 'stock_after' => $stock,
            'reason' => 'P08 synthetic opening', 'created_at' => $now,
        ]);
        $shipping = (int) DB::table('shipping_charges')->insertGetId([
            'campaign_id' => null, 'area_name' => 'P08 area ' . $key,
            'delivery_charge' => 65, 'status' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        return ['product' => $product, 'variant' => $variant, 'shipping' => $shipping];
    }

    private function cart(): GuestCartService
    {
        return app(GuestCartService::class);
    }

    private function add(int $variant, int $qty = 1, int $version = 0): array
    {
        $this->cart()->add($this->owner, $variant, $qty, $version);
        return $this->cart()->show($this->owner);
    }

    private function payload(array $cart, int $area): array
    {
        return [
            'customer_name' => 'P08 Test Buyer', 'phone' => '01700000022',
            'address' => 'Synthetic test address, P08 test district',
            'customer_note' => '', 'shipping_area_id' => $area,
            'cart_version' => $cart['version'], 'checkout_key' => (string) Str::uuid(),
        ];
    }

    private function place(array $payload): string
    {
        return app(StorefrontCheckout::class)->place(
            Request::create('http://127.0.0.1/storefront/checkout', 'POST'), $this->owner, $payload
        );
    }

    public function test_features_are_denied_when_off(): void
    {
        config()->set('storefront.p08_cart_test_enabled', false);
        $this->get(route('storefront.cart'))->assertNotFound();
        $this->post(route('storefront.cart.add'), [
            'product_variant_id' => 1, 'quantity' => 1, 'cart_version' => 0,
        ])->assertNotFound();
    }

    public function test_empty_cart_renders_without_database_write(): void
    {
        $this->get(route('storefront.cart'))->assertOk()->assertSee('Your cart is empty');
        self::assertSame(0, DB::table('carts')->count());
    }

    public function test_cart_persists_and_merges_identical_variant(): void
    {
        $f = $this->fixture();
        $snapshot = $this->add($f['variant'], 2);
        self::assertSame(2, $snapshot['version']);
        $next = $this->add($f['variant'], 2, $snapshot['version']);
        self::assertCount(1, $next['lines']);
        self::assertSame(4, $next['lines'][0]['quantity']);
        self::assertSame('3000.00', $next['subtotal']);
    }

    public function test_cart_owner_isolated_for_read_and_update(): void
    {
        $f = $this->fixture();
        $cart = $this->add($f['variant']);
        $stranger = hash('sha256', 'different-guest');
        self::assertSame([], $this->cart()->show($stranger)['lines']);
        try {
            $this->cart()->change($stranger, $cart['lines'][0]['id'], 0, $cart['version']);
            self::fail('Expected ownership rejection.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('cart', $e->errors());
        }
        self::assertCount(1, $this->cart()->show($this->owner)['lines']);
    }

    public function test_stale_cart_edit_cannot_overwrite_new_quantity(): void
    {
        $f = $this->fixture();
        $cart = $this->add($f['variant'], 1);
        $this->cart()->change($this->owner, $cart['lines'][0]['id'], 3, $cart['version']);
        try {
            $this->cart()->change($this->owner, $cart['lines'][0]['id'], 4, $cart['version']);
            self::fail('Expected stale version rejection.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('cart', $e->errors());
        }
        self::assertSame(3, $this->cart()->show($this->owner)['lines'][0]['quantity']);
    }

    public function test_cart_does_not_reserve_or_deduct_stock(): void
    {
        $f = $this->fixture(stock: 4);
        $this->add($f['variant'], 3);
        self::assertSame(4, (int) DB::table('product_variants')->where('id', $f['variant'])->value('stock'));
        self::assertSame(1, DB::table('inventory_movements')->where('product_variant_id', $f['variant'])->count());
    }

    public function test_overselling_cart_quantity_is_rejected(): void
    {
        $f = $this->fixture(stock: 2);
        try {
            $this->add($f['variant'], 3);
            self::fail('Expected insufficient stock rejection.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('quantity', $e->errors());
        }
        self::assertSame(0, DB::table('cart_items')->count());
    }

    public function test_price_change_requires_fresh_quote_before_checkout(): void
    {
        $f = $this->fixture();
        $cart = $this->add($f['variant']);
        DB::table('products')->where('id', $f['product'])->update(['new_price' => 800]);
        $changed = $this->cart()->show($this->owner);
        self::assertTrue($changed['lines'][0]['price_changed']);
        self::assertFalse($changed['can_checkout']);
        try {
            $this->place($this->payload($cart, $f['shipping']));
            self::fail('Expected price change rejection.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('cart', $e->errors());
        }
        self::assertSame(0, DB::table('orders')->count());
        $this->cart()->change($this->owner, $cart['lines'][0]['id'], 1, $cart['version']);
        self::assertTrue($this->cart()->show($this->owner)['can_checkout']);
    }

    public function test_variable_option_price_and_snapshot_are_server_sourced(): void
    {
        $f = $this->fixture(variable: true);
        $cart = $this->add($f['variant'], 2);
        self::assertSame('830.00', $cart['lines'][0]['unit_price']);
        self::assertSame('1660.00', $cart['subtotal']);
        $this->place($this->payload($cart, $f['shipping']));
        self::assertSame(6, (int) DB::table('product_variants')->where('id', $f['variant'])->value('stock'));
        $item = DB::table('order_items')->first();
        self::assertSame(2, (int) $item->inventory_allocated_quantity);
        self::assertNotNull($item->variant_options);
        self::assertSame(1, DB::table('inventory_movements')->where('kind', 'allocation')->count());
    }

    public function test_successful_cod_order_consumes_cart_once(): void
    {
        $f = $this->fixture();
        $cart = $this->add($f['variant'], 2);
        $token = $this->place($this->payload($cart, $f['shipping']));
        $order = Order::where('success_token', $token)->firstOrFail();
        self::assertSame('cash_on_delivery', $order->payment_method);
        self::assertSame('cod_pending', $order->payment_status);
        self::assertNull($order->campaign_id);
        self::assertSame('1500.00', $order->sub_total);
        self::assertSame('65.00', $order->shipping_charge);
        self::assertSame('1565.00', $order->total_amount);
        self::assertSame(1, (int) $order->inventory_version);
        self::assertSame(6, (int) DB::table('product_variants')->where('id', $f['variant'])->value('stock'));
        self::assertSame(0, DB::table('cart_items')->count());
        self::assertSame(1, DB::table('checkout_requests')->count());
    }

    public function test_checkout_key_replay_returns_original_order_and_no_double_decrement(): void
    {
        $f = $this->fixture();
        $cart = $this->add($f['variant']);
        $payload = $this->payload($cart, $f['shipping']);
        $first = $this->place($payload);
        $replayed = $this->place($payload);
        self::assertSame($first, $replayed);
        self::assertSame(1, DB::table('orders')->count());
        self::assertSame(1, DB::table('inventory_movements')->where('kind', 'allocation')->count());
        self::assertSame(7, (int) DB::table('product_variants')->where('id', $f['variant'])->value('stock'));
    }

    public function test_checkout_key_reused_for_changed_payload_is_rejected(): void
    {
        $f = $this->fixture();
        $cart = $this->add($f['variant']);
        $payload = $this->payload($cart, $f['shipping']);
        $this->place($payload);
        $payload['address'] = 'Changed street address, synthetic test';
        $this->expectException(\DomainException::class);
        $this->place($payload);
    }

    public function test_deleted_or_inactive_variant_blocks_checkout(): void
    {
        $f = $this->fixture();
        $cart = $this->add($f['variant']);
        DB::table('product_variants')->where('id', $f['variant'])->update(['is_active' => 0]);
        self::assertFalse($this->cart()->show($this->owner)['can_checkout']);
        try {
            $this->place($this->payload($cart, $f['shipping']));
            self::fail('Expected stale variant rejection.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('cart', $e->errors());
        }
    }

    public function test_missing_shipping_selection_rejects_entire_checkout(): void
    {
        $f = $this->fixture();
        $cart = $this->add($f['variant']);
        $data = $this->payload($cart, $f['shipping']);
        $data['shipping_area_id'] = $f['shipping'] + 9000;
        try {
            $this->place($data);
            self::fail('Expected invalid shipping area.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('shipping_area_id', $e->errors());
        }
        self::assertSame(0, DB::table('orders')->count());
        self::assertSame(8, (int) DB::table('product_variants')->where('id', $f['variant'])->value('stock'));
    }

    public function test_free_delivery_still_requires_selected_area(): void
    {
        $f = $this->fixture(free: true);
        $cart = $this->add($f['variant']);
        $this->place($this->payload($cart, $f['shipping']));
        $order = Order::firstOrFail();
        self::assertSame('0.00', $order->shipping_charge);
        self::assertTrue($order->is_free_delivery);
    }

    public function test_cart_removal_only_affects_owned_line(): void
    {
        $f = $this->fixture();
        $cart = $this->add($f['variant'], 2);
        $this->cart()->change($this->owner, $cart['lines'][0]['id'], 0, $cart['version']);
        self::assertSame([], $this->cart()->show($this->owner)['lines']);
        self::assertSame(8, (int) DB::table('product_variants')->where('id', $f['variant'])->value('stock'));
    }

    public function test_stale_checkout_version_fails_before_creating_order(): void
    {
        $f = $this->fixture();
        $cart = $this->add($f['variant']);
        $this->cart()->change($this->owner, $cart['lines'][0]['id'], 2, $cart['version']);
        try {
            $this->place($this->payload($cart, $f['shipping']));
            self::fail('Expected stale cart conflict.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('cart', $e->errors());
        }
        self::assertSame(0, DB::table('orders')->count());
    }
}
