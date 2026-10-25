<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\User;
use App\Services\Catalog\VariantCatalogManager;
use App\Services\Storefront\HomepageBuilder;
use App\Services\Storefront\PublicCatalog;
use App\Support\P02MigrationSafety;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Isolated rollback-only real MariaDB catalog HTTP tests, no checkout flows. */
final class P07PublicCatalogMySqlTest extends TestCase
{
    use DatabaseTransactions;

    private PublicCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        P02MigrationSafety::assertIsolatedTestDatabase();
        self::assertGreaterThan(0, DB::transactionLevel());
        config()->set('storefront.p07_catalog_test_enabled', true);
        $this->catalog = app(PublicCatalog::class);
    }

    private function category(bool $enabled = true, ?Category $parent = null): Category
    {
        $slug = 'p07-c-' . Str::lower(Str::random(16));
        return Category::create([
            'name' => 'P07 ' . $slug, 'slug' => $slug,
            'status' => $enabled, 'parent_id' => $parent?->id,
        ]);
    }

    private function product(Category $category, string $name = 'Public test product', bool $enabled = true): Product
    {
        $id = Str::lower(Str::random(14));
        return Product::create([
            'category_id' => $category->id, 'name' => $name, 'slug' => 'p07-p-' . $id,
            'product_code' => 'P07-' . strtoupper($id), 'new_price' => 799,
            'stock' => 4, 'status' => $enabled,
        ]);
    }

    private function storefrontAdmin(): User
    {
        return User::create([
            'name' => 'P07 Admin', 'email' => 'p07-' . Str::random(18) . '@example.test',
            'password' => bcrypt(Str::random(25)), 'role' => 'admin', 'is_active' => true,
        ]);
    }

    private function publishCategoryHomepage(Category $category, string $title = 'Published catalog heading'): HomepageBuilder
    {
        $admin = $this->storefrontAdmin();
        $builder = app(HomepageBuilder::class);
        $builder->startDraft(0, $admin->id);
        $builder->addSection(1, $admin->id, 'category_grid');
        $state = $builder->inspect();
        $key = $state['draft']['sections'][0]['section_key'];
        $builder->updateSection((int) $state['page']['lock_version'], $admin->id, $key, [
            'is_enabled' => true, 'show_mobile' => true, 'show_desktop' => true,
            'title' => $title, 'category_ids' => [(int) $category->id],
        ]);
        $builder->publish((int) $builder->inspect()['page']['lock_version'], $admin->id);
        return $builder;
    }

    public function test_catalog_flag_off_denies_public_read(): void
    {
        config()->set('storefront.p07_catalog_test_enabled', false);
        $this->get(route('storefront.shop'))->assertNotFound();
        $this->get(route('storefront.home'))->assertNotFound();
    }

    public function test_existing_campaign_root_route_is_unchanged(): void
    {
        self::assertSame('home', app('router')->getRoutes()->match(
            \Illuminate\Http\Request::create('/', 'GET'))->getName());
        self::assertSame('campaign.custom.show', app('router')->getRoutes()->match(
            \Illuminate\Http\Request::create('/some-campaign', 'GET'))->getName());
        self::assertSame('storefront.shop', app('router')->getRoutes()->match(
            \Illuminate\Http\Request::create('/storefront/shop', 'GET'))->getName());
    }

    public function test_shop_returns_200_with_empty_catalog(): void
    {
        $this->get(route('storefront.shop'))->assertOk()
            ->assertSee('Explore our products')
            ->assertSee('No matching public products')
            ->assertDontSee('Order Now');
    }

    public function test_active_product_in_active_category_is_visible(): void
    {
        $product = $this->product($this->category(), 'Shiny green mug');
        $this->get(route('storefront.shop'))->assertOk()->assertSee('Shiny green mug')
            ->assertSee(route('storefront.product', $product->slug));
        $this->get(route('storefront.product', $product->slug))->assertOk()->assertSee('799');
    }

    public function test_inactive_and_soft_deleted_products_do_not_leak(): void
    {
        $category = $this->category();
        $inactive = $this->product($category, 'P07 Hidden Product', false);
        $deleted = $this->product($category, 'P07 Deleted Product');
        $deleted->delete();
        $this->get(route('storefront.shop'))->assertDontSee('P07 Hidden Product')->assertDontSee('P07 Deleted Product');
        $this->get(route('storefront.product', $inactive->slug))->assertNotFound();
        $this->get(route('storefront.product', $deleted->slug))->assertNotFound();
    }

    public function test_inactive_parent_hides_descendant_and_product(): void
    {
        $parent = $this->category(false);
        $child = $this->category(true, $parent);
        $product = $this->product($child, 'P07 Unsafe Child Item');
        $this->get(route('storefront.shop'))->assertDontSee('P07 Unsafe Child Item');
        $this->get(route('storefront.category', $child->slug))->assertNotFound();
        $this->get(route('storefront.product', $product->slug))->assertNotFound();
    }

    public function test_third_level_category_is_hidden(): void
    {
        $root = $this->category();
        $child = $this->category(true, $root);
        $grandchild = $this->category(true, $child);
        $product = $this->product($grandchild, 'P07 Third Level Item');
        $this->get(route('storefront.shop'))->assertDontSee('P07 Third Level Item');
        $this->get(route('storefront.category', $grandchild->slug))->assertNotFound();
        $this->get(route('storefront.product', $product->slug))->assertNotFound();
    }

    public function test_root_category_lists_one_level_descendant_products(): void
    {
        $parent = $this->category();
        $child = $this->category(true, $parent);
        $this->product($child, 'P07 In Child Category');
        $this->get(route('storefront.category', $parent->slug))->assertOk()->assertSee('P07 In Child Category');
        $this->get(route('storefront.category', $child->slug))->assertOk()->assertSee('P07 In Child Category');
    }

    public function test_search_filters_products_without_showing_unrelated_items(): void
    {
        $cat = $this->category();
        $this->product($cat, 'Orange P07 Flask');
        $this->product($cat, 'Blue P07 Jacket');
        $this->get(route('storefront.shop', ['q' => 'Orange']))->assertOk()
            ->assertSee('Orange P07 Flask')->assertDontSee('Blue P07 Jacket');
    }

    public function test_invalid_sort_is_rejected_and_does_not_execute_sql(): void
    {
        $this->get(route('storefront.shop', ['sort' => 'rand()']))->assertRedirect();
    }

    public function test_price_sort_uses_server_owned_values(): void
    {
        $cat = $this->category();
        $high = $this->product($cat, 'P07 Expensive One');
        $low = $this->product($cat, 'P07 Affordable One');
        $high->update(['new_price' => 2000]);
        $low->update(['new_price' => 100]);
        $response = $this->get(route('storefront.shop', ['sort' => 'price_asc']))->assertOk();
        self::assertLessThan(strpos($response->getContent(), 'P07 Expensive One'),
            strpos($response->getContent(), 'P07 Affordable One'));
    }

    public function test_variable_product_shows_explicit_options_price_and_stock(): void
    {
        $product = $this->product($this->category());
        $manager = app(VariantCatalogManager::class);
        // Start at zero so the audited default variant also opens at zero.
        $product->update(['stock' => 0]);
        $manager->prepareSimple($product);
        $manager->convertToVariable($product->fresh());
        $manager->bootstrapOptions();
        $option = ProductOption::where('code', 'weight')->firstOrFail();
        $manager->addOptionValue($option, ['code' => '1kg-p07', 'label' => '1 kg']);
        $value = ProductOptionValue::where('product_option_id', $option->id)->where('code', '1kg-p07')->firstOrFail();
        $variant = $manager->createVariant($product->fresh(), 'P07-' . Str::random(10), ['weight' => $value->id], 1499, null, true);
        $manager->adjustStock($product->fresh(), $variant, 3, 0, 'Synthetic counted stock', $this->storefrontAdmin()->id);
        $product->refresh()->update(['status' => true]); // Test-only approved activation, rolled back.
        $this->get(route('storefront.product', $product->slug))->assertOk()
            ->assertSee('1 kg')->assertSee('1499')->assertSee('3')
            ->assertSee('Select all options')->assertDontSee('Add to Cart');
    }

    public function test_draft_never_renders_as_published_homepage(): void
    {
        $builder = app(HomepageBuilder::class);
        $builder->startDraft(0, $this->storefrontAdmin()->id);
        $this->get(route('storefront.home'))->assertOk()->assertSee('not published yet')
            ->assertDontSee('PRIVATE HOMEPAGE PREVIEW');
    }

    public function test_published_homepage_uses_pinned_revision_not_new_draft(): void
    {
        $category = $this->category();
        $builder = $this->publishCategoryHomepage($category, 'P07 Published Heading');
        $this->get(route('storefront.home'))->assertOk()->assertSee('P07 Published Heading')
            ->assertSee(route('storefront.category', $category->slug))
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $builder->startDraft((int) $builder->inspect()['page']['lock_version'], $this->storefrontAdmin()->id);
        $key = $builder->inspect()['draft']['sections'][0]['section_key'];
        $builder->updateSection((int) $builder->inspect()['page']['lock_version'], $this->storefrontAdmin()->id, $key, [
            'title' => 'P07 Secret Draft Only',
        ]);
        $this->get(route('storefront.home'))->assertOk()->assertSee('P07 Published Heading')
            ->assertDontSee('P07 Secret Draft Only');
    }

    public function test_product_description_is_html_escaped(): void
    {
        $product = $this->product($this->category());
        $product->update(['full_description' => '<script>alert(1)</script>']);
        $this->get(route('storefront.product', $product->slug))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_product_detail_does_not_create_cart_or_orders(): void
    {
        $product = $this->product($this->category());
        $this->get(route('storefront.product', $product->slug))->assertOk();
        self::assertSame(0, DB::table('carts')->count());
        self::assertSame(0, DB::table('cart_items')->count());
        self::assertSame(0, DB::table('orders')->count());
    }
}
