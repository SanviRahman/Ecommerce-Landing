<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Storefront\CategoryShelf;
use App\Services\Storefront\PublicCatalog;
use App\Support\P02MigrationSafety;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Run only against a dedicated empty test database with the new migration applied. */
final class CatalogMerchandisingMySqlTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        P02MigrationSafety::assertIsolatedTestDatabase();
        self::assertGreaterThan(0, DB::transactionLevel());
    }

    private function admin(string $role = 'admin'): User
    {
        return User::create([
            'name' => 'Merchandising QA',
            'email' => Str::random(18).'@example.test',
            'password' => bcrypt(Str::random(24)),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function category(?Category $parent = null): Category
    {
        $slug = 'merch-'.Str::lower(Str::random(12));
        return Category::create([
            'name' => $slug,
            'slug' => $slug,
            'status' => true,
            'parent_id' => $parent?->id,
            'sort_order' => 0,
        ]);
    }

    private function product(Category $category, string $name): Product
    {
        $slug = Str::lower(Str::random(16));
        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'product_code' => 'MERCH-'.Str::upper($slug),
            'new_price' => 420,
            'stock' => 3,
            'status' => true,
        ]);
    }

    public function test_product_order_is_shared_by_public_category_and_homepage_shelf(): void
    {
        $category = $this->category();
        $first = $this->product($category, 'Merch Item First');
        $second = $this->product($category, 'Merch Item Second');

        $this->actingAs($this->admin())->patch(route('admin.catalog-order.products'), [
            'category_id' => $category->id,
            'product_ids' => [$first->id, $second->id],
        ])->assertRedirect();

        self::assertSame(1, (int) $first->fresh()->sort_order);
        self::assertSame(2, (int) $second->fresh()->sort_order);

        $catalog = app(PublicCatalog::class);
        $catalogOrder = $catalog->sortedProducts($catalog->eligibleProducts()
            ->where('products.category_id', $category->id), 'manual')->get()->pluck('id')->all();
        $homepageShelf = app(CategoryShelf::class)->productsFor([
            'type' => 'category_products', 'is_enabled' => true,
            'category_id' => $category->id, 'product_limit' => 8,
            'product_sort' => 'newest', // Old section preference must not override admin order.
        ])->pluck('id')->all();

        self::assertSame([$first->id, $second->id], $catalogOrder);
        self::assertSame($catalogOrder, $homepageShelf);
    }

    public function test_category_order_is_persistent_and_subcategory_stays_with_its_parent(): void
    {
        $rootA = $this->category();
        $rootB = $this->category();
        $child = $this->category($rootA);

        // Reorder the full set to avoid overwriting another category's ranking.
        $all = Category::query()->pluck('id')->map('intval')->all();
        $ordered = array_values(array_diff($all, [$rootA->id, $rootB->id, $child->id]));
        array_unshift($ordered, $rootB->id, $rootA->id, $child->id);
        $this->actingAs($this->admin())->patch(route('admin.catalog-order.categories'), [
            'category_ids' => $ordered,
        ])->assertRedirect();

        self::assertSame(1, (int) $rootB->fresh()->sort_order);
        self::assertSame(2, (int) $rootA->fresh()->sort_order);
        self::assertSame($rootA->id, (int) $child->fresh()->parent_id);
        self::assertSame([$rootB->id, $rootA->id],
            app(PublicCatalog::class)->navigationCategories()->pluck('id')
                ->filter(fn ($id) => in_array($id, [$rootB->id, $rootA->id], true))->values()->all());
    }

    public function test_reorder_rejects_malformed_partial_or_cross_category_payload(): void
    {
        $catA = $this->category();
        $catB = $this->category();
        $inside = $this->product($catA, 'Belongs to A');
        $outside = $this->product($catB, 'Belongs to B');
        $this->actingAs($this->admin())->patchJson(route('admin.catalog-order.products'), [
            'category_id' => $catA->id,
            'product_ids' => [$inside->id, $outside->id],
        ])->assertUnprocessable();
        self::assertSame(0, (int) $inside->fresh()->sort_order);
    }

    public function test_non_admin_cannot_change_merchandising_order(): void
    {
        $category = $this->category();
        $product = $this->product($category, 'Locked item');
        $this->actingAs($this->admin('employee'))->patch(route('admin.catalog-order.products'), [
            'category_id' => $category->id,
            'product_ids' => [$product->id],
        ])->assertForbidden();
        self::assertSame(0, (int) $product->fresh()->sort_order);
    }

    public function test_website_name_may_be_empty_without_requiring_logo_or_changing_contacts(): void
    {
        $admin = $this->admin();
        $settings = SiteSetting::create([
            'website_name' => 'Before QA',
            'phone' => '01712345678',
            'status' => true,
        ]);
        $this->actingAs($admin)->put(route('admin.site-settings.update', $settings->id), [
            'website_name' => '', 'phone' => '01712345678', 'status' => '1',
        ])->assertRedirect();

        self::assertNull($settings->fresh()->website_name);
        self::assertSame('01712345678', $settings->fresh()->phone);
        self::assertNotSame('', app(\App\Support\StorefrontBranding::class)->name());
    }
}
