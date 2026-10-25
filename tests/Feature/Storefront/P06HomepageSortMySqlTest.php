<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\User;
use App\Services\Storefront\HomepageBuilder;
use App\Support\P02MigrationSafety;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** P06 draft-only reorder HTTP tests. Each test is rolled back on isolated MySQL. */
final class P06HomepageSortMySqlTest extends TestCase
{
    use DatabaseTransactions;

    private HomepageBuilder $builder;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        P02MigrationSafety::assertIsolatedTestDatabase();
        self::assertGreaterThan(0, DB::transactionLevel());
        $this->builder = app(HomepageBuilder::class);
        $this->admin = $this->user('admin');
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => 'P06 ' . ucfirst($role), 'email' => 'p06-' . Str::random(15) . '@example.test',
            'password' => bcrypt(Str::random(18)), 'role' => $role, 'is_active' => true,
        ]);
    }

    private function draft(int $sections = 3): array
    {
        $this->builder->startDraft(0, $this->admin->id);
        foreach (array_slice(['category_grid', 'product_list', 'hero_slider', 'reviews'], 0, $sections) as $type) {
            $version = (int) $this->builder->inspect()['page']['lock_version'];
            $this->builder->addSection($version, $this->admin->id, $type);
        }
        return $this->builder->inspect();
    }

    private function keys(): array
    {
        return array_column($this->builder->inspect()['draft']['sections'], 'section_key');
    }

    private function endpoint(): string
    {
        return route('admin.storefront.home.sections.reorder');
    }

    public function test_json_drag_reorders_three_sections_and_updates_version(): void
    {
        $start = $this->draft();
        $before = $this->keys();
        $version = (int) $start['page']['lock_version'];
        $target = [$before[2], $before[0], $before[1]];
        $response = $this->actingAs($this->admin)->patchJson($this->endpoint(), [
            'expected_version' => $version, 'section_keys' => $target,
        ]);
        $response->assertOk()->assertJson([
            'saved' => true, 'lock_version' => $version + 1, 'section_keys' => $target,
        ]);

        // Laravel may append `private` to Cache-Control on authenticated responses.
        // Verify the security-critical no-store directive without requiring an exact string.
        $cacheDirectives = array_map(
            static fn (string $directive): string => strtolower(trim($directive)),
            explode(',', (string) $response->baseResponse->headers->get('Cache-Control'))
        );
        self::assertContains('no-store', $cacheDirectives, 'Homepage reorder must not be cacheable.');
        self::assertSame($target, $this->keys());
        self::assertSame($version + 1, (int) $this->builder->inspect()['page']['lock_version']);
        self::assertSame(1, DB::table('homepage_audit_events')->where('event', 'section_reorder')->count());
    }

    public function test_html_form_fallback_returns_redirect_and_persists_order(): void
    {
        $start = $this->draft(2);
        $target = array_reverse($this->keys());
        $this->actingAs($this->admin)->patch($this->endpoint(), [
            'expected_version' => $start['page']['lock_version'], 'section_keys' => $target,
        ])->assertRedirect(route('admin.storefront.home.index'));
        self::assertSame($target, $this->keys());
    }

    public function test_stale_tab_returns_409_and_does_not_change_saved_order(): void
    {
        $start = $this->draft();
        $version = (int) $start['page']['lock_version'];
        $target = array_reverse($this->keys());
        $this->actingAs($this->admin)->patchJson($this->endpoint(), [
            'expected_version' => $version, 'section_keys' => $target,
        ])->assertOk();
        $this->actingAs($this->admin)->patchJson($this->endpoint(), [
            'expected_version' => $version, 'section_keys' => array_reverse($target),
        ])->assertStatus(409);
        self::assertSame($target, $this->keys());
        self::assertSame($version + 1, (int) $this->builder->inspect()['page']['lock_version']);
    }

    public function test_missing_section_refuses_partial_list_atomically(): void
    {
        $start = $this->draft();
        $before = $this->keys();
        $this->actingAs($this->admin)->patchJson($this->endpoint(), [
            'expected_version' => $start['page']['lock_version'], 'section_keys' => array_slice($before, 1),
        ])->assertUnprocessable();
        self::assertSame($before, $this->keys());
    }

    public function test_repeated_section_key_is_rejected_without_writes(): void
    {
        $start = $this->draft();
        $before = $this->keys();
        $this->actingAs($this->admin)->patchJson($this->endpoint(), [
            'expected_version' => $start['page']['lock_version'],
            'section_keys' => [$before[0], $before[0], $before[2]],
        ])->assertUnprocessable();
        self::assertSame($before, $this->keys());
    }

    public function test_unknown_uuid_is_rejected_without_writes(): void
    {
        $start = $this->draft();
        $before = $this->keys();
        $this->actingAs($this->admin)->patchJson($this->endpoint(), [
            'expected_version' => $start['page']['lock_version'],
            'section_keys' => [$before[0], $before[1], (string) Str::uuid()],
        ])->assertUnprocessable();
        self::assertSame($before, $this->keys());
    }

    public function test_invalid_uuid_format_is_rejected(): void
    {
        $start = $this->draft();
        $before = $this->keys();
        $this->actingAs($this->admin)->patchJson($this->endpoint(), [
            'expected_version' => $start['page']['lock_version'],
            'section_keys' => [$before[0], $before[1], 'unsafe-string'],
        ])->assertUnprocessable();
        self::assertSame($before, $this->keys());
    }

    public function test_string_instead_of_order_array_is_rejected(): void
    {
        $start = $this->draft();
        $this->actingAs($this->admin)->patchJson($this->endpoint(), [
            'expected_version' => $start['page']['lock_version'], 'section_keys' => 'not-an-array',
        ])->assertUnprocessable();
    }

    public function test_employee_cannot_change_homepage_order(): void
    {
        $start = $this->draft();
        $before = $this->keys();
        $this->actingAs($this->user('employee'))->patchJson($this->endpoint(), [
            'expected_version' => $start['page']['lock_version'], 'section_keys' => array_reverse($before),
        ])->assertForbidden();
        self::assertSame($before, $this->keys());
    }

    public function test_anonymous_user_cannot_change_homepage_order(): void
    {
        $start = $this->draft();
        $this->app['auth']->guard()->logout();
        $this->patchJson($this->endpoint(), [
            'expected_version' => $start['page']['lock_version'], 'section_keys' => array_reverse($this->keys()),
        ])->assertUnauthorized();
    }

    public function test_disabled_admin_cannot_change_homepage_order(): void
    {
        $start = $this->draft();
        $this->admin->forceFill(['is_active' => false])->save();
        $before = $this->keys();
        $this->actingAs($this->admin)->patchJson($this->endpoint(), [
            'expected_version' => $start['page']['lock_version'], 'section_keys' => array_reverse($before),
        ])->assertRedirect(route('admin.login'));
        self::assertSame($before, $this->keys());
    }

    public function test_published_revision_content_stays_unchanged_after_new_draft_reorder(): void
    {
        $start = $this->draft(2);
        $cat = Category::create([
            'name' => 'P06 Category', 'slug' => 'p06-' . Str::lower(Str::random(15)), 'status' => true,
        ]);
        $first = $start['draft']['sections'][0]['section_key'];
        $this->builder->updateSection((int) $start['page']['lock_version'], $this->admin->id, $first, [
            'is_enabled' => true, 'show_desktop' => true, 'show_mobile' => true,
            'category_ids' => [$cat->id], 'title' => 'Original published heading',
        ]);
        $this->builder->publish((int) $this->builder->inspect()['page']['lock_version'], $this->admin->id);
        $published = $this->builder->inspect()['published'];
        $this->builder->startDraft((int) $this->builder->inspect()['page']['lock_version'], $this->admin->id);
        $target = array_reverse($this->keys());
        $this->actingAs($this->admin)->patchJson($this->endpoint(), [
            'expected_version' => $this->builder->inspect()['page']['lock_version'], 'section_keys' => $target,
        ])->assertOk();
        $after = $this->builder->inspect();
        self::assertSame($published['id'], $after['published']['id']);
        self::assertSame(array_column($published['sections'], 'section_key'),
            array_column($after['published']['sections'], 'section_key'));
        self::assertSame('Original published heading', $after['published']['sections'][0]['title']);
        self::assertSame($target, array_column($after['draft']['sections'], 'section_key'));
    }
}
