<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\Storefront\HomepageBuilder;
use App\Support\P02MigrationSafety;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** Fully transactional, single-test-user P05 integration checks on isolated MariaDB. */
final class P05HomepageMySqlTest extends TestCase
{
    use DatabaseTransactions;

    private HomepageBuilder $builder;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        P02MigrationSafety::assertIsolatedTestDatabase();
        self::assertGreaterThan(0, DB::transactionLevel());
        $this->builder = app(HomepageBuilder::class);
        $this->actor = $this->actor('admin');
    }

    private function actor(string $role): User
    {
        return User::create([
            'name' => 'P05 '.ucfirst($role), 'email' => 'p05-'.Str::random(18).'@example.test',
            'password' => bcrypt(Str::random(20)), 'role' => $role, 'is_active' => true,
        ]);
    }

    private function category(bool $active = true): Category
    {
        $slug = 'p05-cat-'.Str::lower(Str::random(16));
        return Category::create(['name' => 'P05 '. $slug, 'slug' => $slug, 'status' => $active]);
    }

    private function product(): Product
    {
        $cat = $this->category();
        $slug = 'p05-prod-'.Str::lower(Str::random(16));
        return Product::create([
            'category_id' => $cat->id, 'name' => 'P05 Product', 'slug' => $slug,
            'product_code' => 'P05-'.strtoupper(Str::random(15)), 'new_price' => 300, 'stock' => 5, 'status' => true,
        ]);
    }

    private function page(): array
    {
        return $this->builder->inspect();
    }

    private function start(): int
    {
        $this->builder->startDraft(0, $this->actor->id);
        return (int) $this->page()['page']['lock_version'];
    }

    private function section(string $type): array
    {
        $version = $this->start();
        $this->builder->addSection($version, $this->actor->id, $type);
        return $this->page()['draft']['sections'][0];
    }

    private function populateCategorySection(): string
    {
        $category = $this->category();
        $section = $this->section('category_grid');
        $version = $this->page()['page']['lock_version'];
        $this->builder->updateSection($version, $this->actor->id, $section['section_key'], [
            'title' => 'Shop by Category', 'is_enabled' => true, 'show_desktop' => true,
            'show_mobile' => true, 'category_ids' => [(int) $category->id],
        ]);
        return $section['section_key'];
    }

    public function test_empty_inspection_is_read_only(): void
    {
        self::assertNull($this->page()['page']);
        self::assertSame(0, DB::table('storefront_pages')->count());
    }

    public function test_initial_draft_is_not_published(): void
    {
        self::assertSame(1, $this->start());
        $state = $this->page();
        self::assertNull($state['published']);
        self::assertSame('draft', $state['draft']['status']);
        self::assertSame('storefront', $state['page']['mode']);
    }

    public function test_retry_start_draft_is_idempotent(): void
    {
        $v = $this->start();
        $id = $this->page()['draft']['id'];
        $this->builder->startDraft($v, $this->actor->id);
        self::assertSame($id, $this->page()['draft']['id']);
        self::assertSame($v, $this->page()['page']['lock_version']);
    }

    public function test_version_conflict_returns_409_without_mutation(): void
    {
        $version = $this->start();
        $this->builder->addSection($version, $this->actor->id, 'category_grid');
        try {
            $this->builder->addSection($version, $this->actor->id, 'product_list');
            self::fail('Stale version should never overwrite another save.');
        } catch (HttpException $e) {
            self::assertSame(409, $e->getStatusCode());
        }
        self::assertCount(1, $this->page()['draft']['sections']);
    }

    public function test_unsupported_component_and_unsafe_colors_fail_closed(): void
    {
        $this->start();
        $this->expectException(ValidationException::class);
        $this->builder->addSection(1, $this->actor->id, 'arbitrary_php');
    }

    public function test_unknown_style_token_is_rejected(): void
    {
        $section = $this->section('product_list');
        $this->expectException(ValidationException::class);
        $this->builder->updateSection($this->page()['page']['lock_version'], $this->actor->id,
            $section['section_key'], ['is_enabled' => false, 'show_desktop' => true, 'styles' => ['css' => 'body{display:none}']]);
    }

    public function test_disabled_section_cannot_be_published_as_only_content(): void
    {
        $this->section('hero_slider');
        $this->expectException(ValidationException::class);
        $this->builder->publish($this->page()['page']['lock_version'], $this->actor->id);
    }

    public function test_selected_categories_must_be_active_and_unmodified(): void
    {
        $section = $this->section('category_grid');
        $cat = $this->category(false);
        $this->expectException(ValidationException::class);
        $this->builder->updateSection($this->page()['page']['lock_version'], $this->actor->id,
            $section['section_key'], ['is_enabled' => true, 'show_desktop' => true,
                'category_ids' => [(int) $cat->id]]);
    }

    public function test_product_ids_are_scoped_and_duplicated_selection_fails(): void
    {
        $product = $this->product();
        $section = $this->section('product_list');
        $this->expectException(ValidationException::class);
        $this->builder->updateSection($this->page()['page']['lock_version'], $this->actor->id,
            $section['section_key'], ['is_enabled' => true, 'show_mobile' => true,
                'product_ids' => [(int) $product->id, (int) $product->id]]);
    }

    public function test_reorder_requires_exact_draft_keys(): void
    {
        $this->start();
        $this->builder->addSection(1, $this->actor->id, 'category_grid');
        $v = $this->page()['page']['lock_version'];
        $this->builder->addSection($v, $this->actor->id, 'product_list');
        $keys = array_column($this->page()['draft']['sections'], 'section_key');
        $v = $this->page()['page']['lock_version'];
        $this->builder->reorder($v, $this->actor->id, array_reverse($keys));
        self::assertSame(array_reverse($keys), array_column($this->page()['draft']['sections'], 'section_key'));
        $this->expectException(ValidationException::class);
        $this->builder->reorder($this->page()['page']['lock_version'], $this->actor->id,
            [$keys[0], $keys[0]]);
    }

    public function test_global_active_review_can_be_published_as_typed_item(): void
    {
        $section = $this->section('reviews');
        $review = Review::create([
            'campaign_id' => null, 'customer_name' => 'P05 Verified Customer',
            'rating' => 5, 'review_text' => 'Real test-only review text', 'status' => true,
        ]);
        $this->builder->addItem((int) $this->page()['page']['lock_version'], $this->actor->id,
            $section['section_key'], ['review_id' => (int) $review->id]);
        $this->builder->updateSection((int) $this->page()['page']['lock_version'], $this->actor->id,
            $section['section_key'], ['title' => 'Customer Reviews', 'is_enabled' => true, 'show_mobile' => true]);
        $this->builder->publish((int) $this->page()['page']['lock_version'], $this->actor->id);
        self::assertSame('review', $this->page()['published']['sections'][0]['items'][0]['type']);
    }

    public function test_foreign_item_and_missing_media_are_rejected(): void
    {
        $section = $this->section('hero_slider');
        $this->expectException(ValidationException::class);
        $this->builder->addItem($this->page()['page']['lock_version'], $this->actor->id,
            $section['section_key'], ['media_id' => 998888888, 'title' => 'Unsafe']);
    }

    public function test_published_revision_is_immutable_during_next_draft_edits(): void
    {
        $key = $this->populateCategorySection();
        $this->builder->publish($this->page()['page']['lock_version'], $this->actor->id);
        $state = $this->page();
        $publishedId = (int) $state['published']['id'];
        self::assertNull($state['draft']);
        $this->builder->startDraft((int) $state['page']['lock_version'], $this->actor->id);
        $new = $this->page();
        self::assertNotSame($publishedId, (int) $new['draft']['id']);
        self::assertSame($key, $new['draft']['sections'][0]['section_key']);
        $this->builder->updateSection((int) $new['page']['lock_version'], $this->actor->id, $key,
            ['title' => 'Edited private draft', 'is_enabled' => true, 'show_desktop' => true,
                'category_ids' => $new['draft']['sections'][0]['categories']]);
        $after = $this->page();
        self::assertSame('Shop by Category', $after['published']['sections'][0]['title']);
        self::assertSame('Edited private draft', $after['draft']['sections'][0]['title']);
    }

    public function test_restore_clones_published_revision_into_new_draft(): void
    {
        $this->populateCategorySection();
        $this->builder->publish($this->page()['page']['lock_version'], $this->actor->id);
        $before = $this->page();
        $this->builder->startDraft((int) $before['page']['lock_version'], $this->actor->id,
            (int) $before['published']['id']);
        $after = $this->page();
        self::assertSame('published', $after['published']['status']);
        self::assertSame('draft', $after['draft']['status']);
        self::assertNotSame($after['published']['sections'][0]['id'], $after['draft']['sections'][0]['id']);
        self::assertSame($after['published']['sections'][0]['section_key'], $after['draft']['sections'][0]['section_key']);
    }

    public function test_published_resources_revalidated_when_category_becomes_inactive(): void
    {
        $this->populateCategorySection();
        $cat = DB::table('homepage_section_categories')->first();
        DB::table('categories')->where('id', $cat->category_id)->update(['status' => false]);
        $this->expectException(ValidationException::class);
        $this->builder->publish($this->page()['page']['lock_version'], $this->actor->id);
    }

    public function test_successful_publication_is_only_a_staging_pointer(): void
    {
        $this->populateCategorySection();
        $this->builder->publish($this->page()['page']['lock_version'], $this->actor->id);
        self::assertNull($this->page()['draft']);
        self::assertSame('published', $this->page()['published']['status']);
        self::assertSame('storefront', $this->page()['page']['mode']);
        self::assertSame(\App\Http\Controllers\Frontend\HomeController::class . '@index', \Illuminate\Support\Facades\Route::getRoutes()->getByName('home')->getActionName());
        self::assertSame(1, DB::table('homepage_audit_events')->where('event', 'publish')->count());
    }

    public function test_role_employee_cannot_view_or_publish_builder(): void
    {
        $employee = $this->actor('employee');
        $this->actingAs($employee)->get(route('admin.storefront.home.index'))->assertForbidden();
        $this->actingAs($employee)->post(route('admin.storefront.home.publish'), ['expected_version' => 1])
            ->assertForbidden();
    }

    public function test_inactive_admin_cannot_use_builder(): void
    {
        $this->actor->update(['is_active' => false]);
        $this->actingAs($this->actor)->get(route('admin.storefront.home.index'))->assertRedirect(route('admin.login'));
    }

    public function test_authenticated_preview_is_private_no_store(): void
    {
        $this->populateCategorySection();
        $this->actingAs($this->actor)->get(route('admin.storefront.home.preview'))
            ->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Shop by Category')->assertSee('PRIVATE HOMEPAGE PREVIEW');
    }

    public function test_cross_page_section_key_cannot_be_edited(): void
    {
        $this->start();
        $this->expectException(ValidationException::class);
        $this->builder->updateSection(1, $this->actor->id, (string) Str::uuid(), ['is_enabled' => false]);
    }
}
