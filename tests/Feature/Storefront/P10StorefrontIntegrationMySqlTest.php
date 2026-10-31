<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Support\P02MigrationSafety;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** P10 cross-page smoke: only synthetic rows on restricted MySQL, rolled back. */
final class P10StorefrontIntegrationMySqlTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        P02MigrationSafety::assertIsolatedTestDatabase();
        self::assertGreaterThan(0, DB::transactionLevel());
        config()->set('storefront.p07_catalog_test_enabled', true);
        config()->set('storefront.p08_cart_test_enabled', false);
        config()->set('storefront.p08_checkout_test_enabled', false);
        config()->set('storefront.p03_core_test_enabled', false);
    }

    /** @param array<string, mixed> $overrides */
    private function siteSetting(array $overrides = []): void
    {
        DB::table('site_settings')->insert(array_merge([
            'website_name' => 'P10 Synthetic Shop', 'status' => true,
            'whatsapp_number' => null, 'messenger_link' => null,
            'email' => null, 'phone' => null, 'hotline' => null,
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides));
    }

    public function test_missing_contact_does_not_create_fake_buttons(): void
    {
        $this->get(route('storefront.shop'))->assertOk()->assertDontSee('p10-contact-menu', false);
        self::assertSame(0, DB::table('site_settings')->count());
    }

    public function test_configured_contact_urls_are_visible_on_shop(): void
    {
        $this->siteSetting([
            'whatsapp_number' => '01700000001',
            'messenger_link' => 'https://m.me/p10shop',
            'email' => 'support@example.org', 'phone' => '+8801700000002',
        ]);
        $this->get(route('storefront.shop'))->assertOk()
            ->assertSee('https://wa.me/8801700000001', false)
            ->assertSee('https://m.me/p10shop', false)
            ->assertSee('mailto:support@example.org', false)
            ->assertSee('tel:+8801700000002', false);
    }

    public function test_inactive_contact_and_unsafe_message_links_never_render(): void
    {
        $this->siteSetting(['status' => false, 'whatsapp_number' => '01700000001']);
        $this->get(route('storefront.shop'))->assertOk()->assertDontSee('https://wa.me/', false);
        $this->siteSetting(['messenger_link' => 'https://m.me.evil.example/bad']);
        $this->get(route('storefront.shop'))->assertOk()->assertDontSee('m.me.evil.example', false);
    }

    public function test_cart_only_footer_does_not_promise_checkout(): void
    {
        config()->set('storefront.p08_cart_test_enabled', true);
        $this->get(route('storefront.shop'))->assertOk()
            ->assertSee('Cart review is available. Checkout is not enabled yet.')
            ->assertSee('storefront/cart', false);
    }

    public function test_checkout_enabled_footer_reflects_real_flags(): void
    {
        config()->set('storefront.p08_cart_test_enabled', true);
        config()->set('storefront.p08_checkout_test_enabled', true);
        config()->set('storefront.p03_core_test_enabled', true);
        $this->get(route('storefront.shop'))->assertOk()
            ->assertSee('Cart and cash-on-delivery checkout are available');
    }

    public function test_product_error_is_visible_without_mutating_inventory(): void
    {
        $key = strtolower(Str::random(12));
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'P10 synthetic category', 'slug' => 'p10-cat-' . $key,
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'category_id' => $categoryId, 'name' => 'P10 synthetic product',
            'slug' => 'p10-prod-' . $key, 'product_code' => 'P10-' . $key,
            'stock' => 3, 'new_price' => 520, 'status' => 1,
            'has_variants' => 0, 'inventory_version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('product_variants')->insert([
            'product_id' => $productId, 'sku' => 'P10-SKU-' . $key,
            'option_signature' => 'default', 'stock' => 3,
            'is_default' => 1, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // Reproduce the actual browser workflow. Injecting an error bag directly
        // into the test session did not represent a failed Add-to-Cart POST.
        // Keep cart enabled so the form and real Laravel validation path exist.
        config()->set('storefront.p08_cart_test_enabled', true);
        $productUrl = route('storefront.product', 'p10-prod-' . $key);
        $this->get($productUrl)->assertOk()
            ->assertSee('p08-product-order-form', false)
            ->assertSee('name="storefront_product_origin"', false)
            ->assertDontSee('We could not update your cart.');
        $this->from($productUrl)->post(route('storefront.cart.add'), [
            'product_variant_id' => 0, // Deliberately invalid; no cart/order writes.
            'quantity' => 2,
            'cart_version' => 0,
            'intent' => 'cart',
            'storefront_product_origin' => 'p10-prod-' . $key,
        ])->assertRedirect($productUrl)->assertSessionHasErrors(['product_variant_id']);
        // The HTML must show useful feedback even if a view loses the shared
        // error bag; the scoped failed-form marker comes from Laravel's flash.
        $this->get($productUrl)->assertOk()
            ->assertSee('We could not update your cart.')
            ->assertSee('role="alert"', false)
            ->assertSee('p08-product-order-form', false)
            ->assertSee('value="2" required', false);
        self::assertSame(0, DB::table('orders')->count());
        self::assertSame(0, DB::table('cart_items')->count());
        self::assertSame(3, (int) DB::table('products')->where('id', $productId)->value('stock'));
        self::assertSame(3, (int) DB::table('product_variants')->where('product_id', $productId)->value('stock'));
    }

    public function test_storefront_stays_hidden_when_feature_disabled(): void
    {
        config()->set('storefront.p07_catalog_test_enabled', false);
        $this->get(route('storefront.shop'))->assertNotFound();
        $this->get(route('storefront.home'))->assertNotFound();
    }
}
