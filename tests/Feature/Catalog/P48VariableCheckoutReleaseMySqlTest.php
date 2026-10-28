<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Cart\GuestCartService;
use App\Services\Catalog\VariantCatalogManager;
use App\Support\P02MigrationSafety;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Restricted p01_campaign_test only, transactional fixtures automatically rollback. */
final class P48VariableCheckoutReleaseMySqlTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private VariantCatalogManager $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        P02MigrationSafety::assertIsolatedTestDatabase();
        self::assertGreaterThan(0, DB::transactionLevel());
        $this->admin = User::create([
            'name' => 'P48 QA Admin', 'email' => 'p48-' . Str::random(15) . '@example.test',
            'password' => bcrypt(Str::random(28)), 'role' => 'admin', 'is_active' => true,
        ]);
        $this->actingAs($this->admin);
        $this->catalog = app(VariantCatalogManager::class);
    }

    /** @return array{product:Product, variant:ProductVariant} */
    private function variableFixture(int $stock = 0, int $price = 650): array
    {
        $key = strtolower(Str::random(14));
        $cat = Category::create([
            'name' => 'P48 Test Category', 'slug' => 'p48-cat-' . $key, 'status' => true,
        ]);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => 'P48 Synthetic Variant',
            'slug' => 'p48-product-' . $key, 'product_code' => 'P48-' . strtoupper($key),
            'stock' => 0, 'new_price' => 630, 'status' => true,
        ]);
        $this->catalog->prepareSimple($product);
        $this->catalog->convertToVariable($product);
        $this->catalog->bootstrapOptions();
        $color = ProductOption::query()->where('code', 'color')->firstOrFail();
        $this->catalog->addOptionValue($color, [
            'code' => 'p48-' . $key, 'label' => 'P48 Test Black', 'swatch_hex' => '#000000',
        ]);
        $valueId = (int) ProductOptionValue::query()
            ->where('product_option_id', $color->id)->where('code', 'p48-' . $key)->value('id');
        $variant = $this->catalog->createVariant($product, 'P48-SKU-' . strtoupper($key),
            ['color' => $valueId], $price, null, true);
        if ($stock > 0) {
            $this->catalog->adjustStock($product, $variant, $stock, 0,
                'P48 synthetic warehouse physical count', (int) $this->admin->id);
        }
        return ['product' => $product->fresh(), 'variant' => $variant->fresh()];
    }

    public function test_a_zero_stock_variant_cannot_be_released(): void
    {
        $fixture = $this->variableFixture();
        $this->post(route('admin.products.variants.activate', $fixture['product']))
            ->assertSessionHasErrors('product');
        self::assertFalse((bool) $fixture['product']->fresh()->status);
    }

    public function test_a_valid_audited_variant_can_be_activated_and_added_to_cart(): void
    {
        $fixture = $this->variableFixture(stock: 7, price: 840);
        $this->post(route('admin.products.variants.activate', $fixture['product']))
            ->assertRedirect()->assertSessionHas('success');
        self::assertTrue((bool) $fixture['product']->fresh()->status);

        config()->set('storefront.p08_cart_test_enabled', true);
        $ownerHash = hash('sha256', 'p48-test-' . Str::random(25));
        $carts = app(GuestCartService::class);
        $carts->add($ownerHash, (int) $fixture['variant']->id, 2, 0);
        $state = $carts->show($ownerHash);
        self::assertTrue($state['can_checkout']);
        self::assertSame('840.00', $state['lines'][0]['unit_price']);
        self::assertSame('1680.00', $state['subtotal']);
        self::assertSame(7, (int) $fixture['variant']->fresh()->stock); // Cart does not deduct.
    }

    public function test_tampered_option_signature_blocks_release(): void
    {
        $fixture = $this->variableFixture(stock: 3);
        ProductVariant::whereKey($fixture['variant']->id)->update(['option_signature' => '1:999999']);
        $this->post(route('admin.products.variants.activate', $fixture['product']))
            ->assertSessionHasErrors('product');
        self::assertFalse((bool) $fixture['product']->fresh()->status);
    }

    public function test_zero_price_and_unapproved_inventory_are_rejected(): void
    {
        $fixture = $this->variableFixture(stock: 4, price: 0);
        $this->post(route('admin.products.variants.activate', $fixture['product']))
            ->assertSessionHasErrors('product');
        self::assertFalse((bool) $fixture['product']->fresh()->status);
        ProductVariant::whereKey($fixture['variant']->id)->update(['price_override' => 680]);
        $lastMovementId = (int) DB::table('inventory_movements')
            ->where('product_variant_id', $fixture['variant']->id)
            ->orderByDesc('id')->value('id');
        DB::table('inventory_movements')->where('id', $lastMovementId)->update(['stock_after' => 999]);
        $this->post(route('admin.products.variants.activate', $fixture['product']))
            ->assertSessionHasErrors('product');
        self::assertFalse((bool) $fixture['product']->fresh()->status);
    }

    public function test_pause_does_not_destroy_inventory_or_cart(): void
    {
        $fixture = $this->variableFixture(stock: 5);
        $this->post(route('admin.products.variants.activate', $fixture['product']))
            ->assertSessionHas('success');
        $this->post(route('admin.products.variants.pause', $fixture['product']))
            ->assertSessionHas('success');
        self::assertFalse((bool) $fixture['product']->fresh()->status);
        self::assertSame(5, (int) $fixture['variant']->fresh()->stock);
    }
}
