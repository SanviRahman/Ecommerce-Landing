<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Catalog\VariantCatalogManager;
use App\Support\P02MigrationSafety;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Test-only fixtures; every test writes inside DatabaseTransactions and rolls back. */
final class P04VariantCatalogMySqlTest extends TestCase
{
    use DatabaseTransactions;

    private VariantCatalogManager $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        P02MigrationSafety::assertIsolatedTestDatabase();
        self::assertGreaterThan(0, DB::transactionLevel());
        $this->catalog = app(VariantCatalogManager::class);
    }

    private function syntheticActorId(): int
    {
        $user = User::create([
            'name' => 'P04 Stock Admin',
            'email' => 'p04-inventory-' . Str::random(16) . '@example.test',
            'password' => bcrypt(Str::random(26)),
            'role' => 'admin', 'is_active' => true,
        ]);
        return (int) $user->id;
    }

    private function product(int $stock = 0): Product
    {
        $nonce = Str::lower(Str::random(15));
        $category = Category::create(['name' => 'P04 Test Category', 'slug' => 'p04-cat-' . $nonce, 'status' => true]);
        return Product::create([
            'category_id' => $category->id,
            'name' => 'P04 Test Product', 'slug' => 'p04-prod-' . $nonce,
            'product_code' => 'P04-' . strtoupper($nonce), 'new_price' => 999,
            'stock' => $stock, 'status' => true,
        ]);
    }

    private function option(string $code, string $valueCode, string $label): int
    {
        $this->catalog->bootstrapOptions();
        $option = ProductOption::where('code', $code)->firstOrFail();
        $this->catalog->addOptionValue($option, ['code' => $valueCode, 'label' => $label]);
        return (int) ProductOptionValue::where('product_option_id', $option->id)
            ->where('code', $valueCode)->value('id');
    }

    private function variableProduct(): Product
    {
        $product = $this->product();
        $this->catalog->prepareSimple($product);
        $this->catalog->convertToVariable($product);
        return $product->fresh();
    }

    public function test_bootstrap_creates_exact_supported_options_without_duplicates(): void
    {
        $this->catalog->bootstrapOptions();
        $this->catalog->bootstrapOptions();
        self::assertSame(4, ProductOption::whereIn('code', array_keys(VariantCatalogManager::OPTIONS))->count());
    }

    public function test_prepare_simple_preserves_stock_and_creates_one_opening_ledger(): void
    {
        $product = $this->product(10);
        $this->catalog->prepareSimple($product);
        $product->refresh();
        self::assertSame(1, (int) $product->inventory_version);
        self::assertSame(10, (int) $product->stock);
        $variant = ProductVariant::where('product_id', $product->id)->sole();
        self::assertSame(10, (int) $variant->stock);
        self::assertSame(1, DB::table('inventory_movements')->where('product_variant_id', $variant->id)->count());
    }

    public function test_conversion_refuses_to_clone_nonzero_stock(): void
    {
        $product = $this->product(10);
        $this->catalog->prepareSimple($product);
        $this->expectException(ValidationException::class);
        $this->catalog->convertToVariable($product);
    }

    public function test_zero_stock_conversion_is_inactive_and_preserves_default_history(): void
    {
        $product = $this->variableProduct();
        self::assertTrue((bool) $product->has_variants);
        self::assertFalse((bool) $product->status);
        self::assertSame(1, ProductVariant::where('product_id', $product->id)->count());
        self::assertFalse((bool) ProductVariant::where('product_id', $product->id)->firstOrFail()->is_active);
    }

    public function test_one_kg_and_two_kg_keep_independent_explicit_prices(): void
    {
        $product = $this->variableProduct();
        $one = $this->option('weight', '1kg', '1 kg');
        $two = $this->option('weight', '2kg', '2 kg');
        $sku1 = 'P04-ONE-' . strtoupper(Str::random(8));
        $sku2 = 'P04-TWO-' . strtoupper(Str::random(8));
        $v1 = $this->catalog->createVariant($product, $sku1, ['weight' => $one], 1499, null, true);
        $v2 = $this->catalog->createVariant($product, $sku2, ['weight' => $two], 1800, null, true);
        self::assertSame(1499, (int) $v1->price_override);
        self::assertSame(1800, (int) $v2->price_override);
        self::assertNotSame($v1->option_signature, $v2->option_signature);
        self::assertSame(0, (int) $v1->stock);
        self::assertSame(0, (int) $v2->stock);
    }

    public function test_duplicate_option_combination_is_rejected(): void
    {
        $product = $this->variableProduct();
        $size = $this->option('size', 'xl', 'XL');
        $this->catalog->createVariant($product, 'P04-XL-' . Str::random(8), ['size' => $size], null, null, true);
        $this->expectException(ValidationException::class);
        $this->catalog->createVariant($product, 'P04-OTHER-' . Str::random(8), ['size' => $size], null, null, true);
    }

    public function test_foreign_option_value_is_rejected(): void
    {
        $product = $this->variableProduct();
        $color = $this->option('color', 'black', 'Black');
        $this->expectException(ValidationException::class);
        $this->catalog->createVariant($product, 'P04-BAD-' . Str::random(8), ['weight' => $color], null, null, true);
    }

    public function test_audited_adjustment_updates_variant_mirror_and_ledger(): void
    {
        $product = $this->variableProduct();
        $size = $this->option('size', 'm', 'Medium');
        $variant = $this->catalog->createVariant($product, 'P04-M-' . Str::random(8), ['size' => $size], 0, null, true);
        $this->catalog->adjustStock($product, $variant, 6, 0, 'Physical inventory counted', $this->syntheticActorId());
        self::assertSame(6, (int) $variant->fresh()->stock);
        self::assertSame(6, (int) $product->fresh()->stock);
        self::assertSame(6, (int) DB::table('inventory_movements')
            ->where('product_variant_id', $variant->id)->where('kind', 'adjustment')->value('quantity_delta'));
        self::assertSame(0, (int) $variant->price_override);
    }

    public function test_retired_default_sku_cannot_receive_stock_after_conversion(): void
    {
        $product = $this->variableProduct();
        $retired = ProductVariant::where('product_id', $product->id)
            ->where('option_signature', 'default')->firstOrFail();
        $this->expectException(ValidationException::class);
        $this->catalog->adjustStock($product, $retired, 3, 0,
            'Must never restock retired default', $this->syntheticActorId());
    }

    public function test_stale_stock_adjustment_refuses_a_second_change(): void
    {
        $product = $this->variableProduct();
        $size = $this->option('size', 'l', 'Large');
        $variant = $this->catalog->createVariant($product, 'P04-L-' . Str::random(8), ['size' => $size], null, null, true);
        $actorId = $this->syntheticActorId();
        $this->catalog->adjustStock($product, $variant, 2, 0, 'Physical count plus two', $actorId);
        try {
            $this->catalog->adjustStock($product, $variant, 2, 0, 'Stale second submission', $actorId);
            self::fail('Stale adjustment was accepted.');
        } catch (ValidationException) {
            self::assertSame(2, (int) $variant->fresh()->stock);
            self::assertSame(2, (int) $product->fresh()->stock);
        }
    }

    public function test_oversell_like_negative_adjustment_is_rejected(): void
    {
        $product = $this->variableProduct();
        $size = $this->option('size', 's', 'Small');
        $variant = $this->catalog->createVariant($product, 'P04-S-' . Str::random(8), ['size' => $size], null, null, true);
        $this->expectException(ValidationException::class);
        $this->catalog->adjustStock($product, $variant, -1, 0, 'Invalid negative inventory', $this->syntheticActorId());
    }

    public function test_category_parent_cycle_is_rejected_without_mutation(): void
    {
        $root = $this->product()->category;
        $child = Category::create([
            'name' => 'P04 Child', 'slug' => 'p04-child-' . Str::random(12),
            'parent_id' => $root->id, 'sort_order' => 10, 'status' => true,
        ]);
        $user = User::create([
            'name' => 'P04 Admin', 'email' => 'p04-' . Str::random(16) . '@example.test',
            'password' => bcrypt(Str::random(26)), 'role' => 'admin', 'is_active' => true,
        ]);
        $this->actingAs($user)->put(route('admin.categories.update', $root->id), [
            'name' => 'P04 Test Category', 'parent_id' => $child->id, 'sort_order' => 0,
        ])->assertSessionHasErrors(['parent_id']);
        self::assertNull($root->fresh()->parent_id);
    }

    public function test_variable_product_cannot_be_bulk_activated_via_legacy_admin(): void
    {
        $product = $this->variableProduct();
        $user = User::create([
            'name' => 'P04 Admin', 'email' => 'p04-' . Str::random(16) . '@example.test',
            'password' => bcrypt(Str::random(26)), 'role' => 'admin', 'is_active' => true,
        ]);
        $this->actingAs($user)->postJson(route('admin.products.multiple_action'), [
            'action' => 'active', 'ids' => [$product->id],
        ])->assertUnprocessable();
        self::assertFalse((bool) $product->fresh()->status);
    }

    public function test_admin_can_create_a_variant_through_a_validated_post(): void
    {
        $product = $this->variableProduct();
        $weightId = $this->option('weight', '500g', '500 g');
        $actor = User::create([
            'name' => 'P04 Admin', 'email' => 'p04-' . Str::random(16) . '@example.test',
            'password' => bcrypt(Str::random(26)), 'role' => 'admin', 'is_active' => true,
        ]);
        $sku = 'P04-500G-' . strtoupper(Str::random(8));
        $this->actingAs($actor)->post(route('admin.products.variants.store', $product->id), [
            'sku' => $sku, 'price_override' => 499, 'options' => ['weight' => $weightId],
            'is_active' => 1,
        ])->assertRedirect()->assertSessionHas('success');
        self::assertSame(0, (int) ProductVariant::where('sku', $sku)->value('stock'));
        self::assertSame(499, (int) ProductVariant::where('sku', $sku)->value('price_override'));
    }

    public function test_admin_stock_post_rejects_stale_expected_balance(): void
    {
        $product = $this->variableProduct();
        $sizeId = $this->option('size', 'xxl', 'XXL');
        $variant = $this->catalog->createVariant($product, 'P04-XXL-' . Str::random(8), ['size' => $sizeId], 100, null, true);
        $actor = User::create([
            'name' => 'P04 Admin', 'email' => 'p04-' . Str::random(16) . '@example.test',
            'password' => bcrypt(Str::random(26)), 'role' => 'admin', 'is_active' => true,
        ]);
        $this->actingAs($actor)->post(route('admin.products.variants.stock', [$product->id, $variant->id]), [
            'delta' => 7, 'expected_stock' => 3, 'reason' => 'Wrong stale stock count',
        ])->assertSessionHasErrors(['expected_stock']);
        self::assertSame(0, (int) $variant->fresh()->stock);
    }

    public function test_http_page_requires_admin_and_rejects_employee(): void
    {
        $product = $this->product();
        $user = User::create([
            'name' => 'P04 Employee', 'email' => 'p04-' . Str::random(16) . '@example.test',
            'password' => bcrypt(Str::random(26)), 'role' => 'employee', 'is_active' => true,
        ]);
        $this->actingAs($user)->get(route('admin.products.variants.index', $product->id))->assertForbidden();
    }

    public function test_admin_can_render_variant_management_page(): void
    {
        $product = $this->product();
        $user = User::create([
            'name' => 'P04 Admin', 'email' => 'p04-' . Str::random(16) . '@example.test',
            'password' => bcrypt(Str::random(26)), 'role' => 'admin', 'is_active' => true,
        ]);
        $this->actingAs($user)->get(route('admin.products.variants.index', $product->id))
            ->assertOk()->assertSee('Variants:');
    }

    public function test_unused_color_value_can_be_edited_and_deleted(): void
    {
        $this->catalog->bootstrapOptions();
        $option = ProductOption::where('code', 'color')->firstOrFail();
        $code = 'p40-' . Str::lower(Str::random(10));
        $this->catalog->addOptionValue($option, ['code' => $code, 'label' => 'Before']);
        $value = ProductOptionValue::where('product_option_id', $option->id)->where('code', $code)->firstOrFail();
        $this->catalog->updateOptionValue($option, $value, [
            'code' => $code, 'label' => 'Forest Green', 'swatch_hex' => '#187449',
            'sort_order' => 2, 'is_active' => true,
        ]);
        self::assertSame('Forest Green', $value->fresh()->label);
        self::assertSame('#187449', $value->fresh()->swatch_hex);
        $this->catalog->deleteOptionValue($option, $value);
        self::assertFalse(ProductOptionValue::whereKey($value->id)->exists());
    }

    public function test_referenced_color_value_cannot_change_code_or_be_deleted(): void
    {
        $product = $this->variableProduct();
        $code = 'p40-' . Str::lower(Str::random(10));
        $id = $this->option('color', $code, 'Blue');
        $this->catalog->createVariant($product, 'P40-C-' . Str::random(8), ['color' => $id], null, null, true);
        $option = ProductOption::where('code', 'color')->firstOrFail();
        $value = ProductOptionValue::findOrFail($id);
        try {
            $this->catalog->updateOptionValue($option, $value, [
                'code' => 'different', 'label' => 'Blue', 'swatch_hex' => '#2222FF',
                'sort_order' => 0, 'is_active' => true,
            ]);
            self::fail('A used option code must remain immutable.');
        } catch (ValidationException $expected) {
            self::assertSame($code, $value->fresh()->code);
        }
        $this->expectException(ValidationException::class);
        $this->catalog->deleteOptionValue($option, $value);
    }

    public function test_value_used_by_active_variant_cannot_be_disabled(): void
    {
        $product = $this->variableProduct();
        $code = 'p40-' . Str::lower(Str::random(10));
        $id = $this->option('size', $code, 'XL');
        $this->catalog->createVariant($product, 'P40-S-' . Str::random(8), ['size' => $id], null, null, true);
        $option = ProductOption::where('code', 'size')->firstOrFail();
        $value = ProductOptionValue::findOrFail($id);
        $this->expectException(ValidationException::class);
        $this->catalog->updateOptionValue($option, $value, [
            'code' => $code, 'label' => 'XL', 'swatch_hex' => null,
            'sort_order' => 0, 'is_active' => false,
        ]);
    }
}
