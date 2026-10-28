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

/**
 * P41: run ONLY on the isolated migrated MariaDB test database.
 * Published revisions are immutable; a reordered draft is explicitly published
 * by the editor's publish_after_sort=1 flag and then cloned for further edits.
 */
final class P41HomepageOrderLiveMySqlTest extends TestCase
{
    use DatabaseTransactions;

    private HomepageBuilder $builder;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        P02MigrationSafety::assertIsolatedTestDatabase();
        $this->builder = app(HomepageBuilder::class);
        $this->admin = User::create([
            'name' => 'P41 Admin', 'email' => 'p41-' . Str::random(14) . '@example.test',
            'password' => bcrypt(Str::random(30)), 'role' => 'admin', 'is_active' => true,
        ]);
    }

    /** @return array{0:array, 1:array} The public revision and its editable clone. */
    private function publishedWithTwoSections(): array
    {
        $this->builder->startDraft(0, $this->admin->id);
        foreach (['Fashion', 'Electronics'] as $name) {
            $category = Category::create([
                'name' => 'P41 ' . $name, 'slug' => 'p41-' . Str::lower(Str::random(13)),
                'status' => true,
            ]);
            $v = (int) $this->builder->inspect()['page']['lock_version'];
            $this->builder->addSection($v, $this->admin->id, 'category_grid');
            $draft = $this->builder->inspect();
            $section = $draft['draft']['sections'][count($draft['draft']['sections']) - 1];
            $this->builder->updateSection((int) $draft['page']['lock_version'], $this->admin->id,
                $section['section_key'], [
                    'title' => 'P41 ' . $name, 'is_enabled' => true,
                    'show_desktop' => true, 'show_mobile' => true,
                    'category_ids' => [(int) $category->id],
                ]);
        }
        $this->builder->publish((int) $this->builder->inspect()['page']['lock_version'], $this->admin->id);
        $published = $this->builder->inspect()['published'];
        $this->builder->startDraft((int) $this->builder->inspect()['page']['lock_version'], $this->admin->id);
        return [$published, $this->builder->inspect()];
    }

    public function test_drag_auto_publishes_exact_order_without_mutating_previous_publication(): void
    {
        [$previous, $state] = $this->publishedWithTwoSections();
        $before = array_column($state['draft']['sections'], 'section_key');
        $reordered = array_reverse($before);
        $version = (int) $state['page']['lock_version'];

        $this->actingAs($this->admin)
            ->patchJson(route('admin.storefront.home.sections.reorder'), [
                'expected_version' => $version, 'section_keys' => $reordered,
                'publish_after_sort' => true,
            ])->assertOk()->assertJson([
                'saved' => true, 'published' => true,
                'lock_version' => $version + 3, 'section_keys' => $reordered,
            ]);

        $after = $this->builder->inspect();
        self::assertSame($reordered, array_column($after['published']['sections'], 'section_key'));
        self::assertSame($reordered, array_column($after['draft']['sections'], 'section_key'));
        self::assertSame('published', $after['published']['status']);
        self::assertSame('draft', $after['draft']['status']);
        self::assertNotSame($previous['id'], $after['published']['id']);
        self::assertNotSame($after['draft']['id'], $after['published']['id']);
        self::assertSame($before, array_column($this->builder->revision((int) $previous['id'])['sections'], 'section_key'));
        self::assertSame('archived', $this->builder->revision((int) $previous['id'])['status']);

        $live = $this->get('/');
        $live->assertOk();
        $html = $live->getContent();
        $first = strpos($html, 'id="sf-section-' . $reordered[0] . '"');
        $second = strpos($html, 'id="sf-section-' . $reordered[1] . '"');
        self::assertNotFalse($first);
        self::assertNotFalse($second);
        self::assertLessThan($second, $first, 'Public HTML order must match the Admin organizer.');
        self::assertStringNotContainsString('p33-auto-section', $html);
        self::assertStringNotContainsString('p33-track-section', $html);

        $preview = $this->actingAs($this->admin)->get(route('admin.storefront.home.preview'));
        $preview->assertOk();
        $previewHtml = $preview->getContent();
        self::assertLessThan(
            strpos($previewHtml, 'id="sf-section-' . $reordered[1] . '"'),
            strpos($previewHtml, 'id="sf-section-' . $reordered[0] . '"')
        );
    }

    public function test_invalid_draft_order_is_saved_but_unpublishable_content_remains_private(): void
    {
        $this->builder->startDraft(0, $this->admin->id);
        foreach (['category_grid', 'product_list'] as $type) {
            $v = (int) $this->builder->inspect()['page']['lock_version'];
            $this->builder->addSection($v, $this->admin->id, $type);
        }
        $state = $this->builder->inspect();
        $target = array_reverse(array_column($state['draft']['sections'], 'section_key'));
        $version = (int) $state['page']['lock_version'];
        $this->actingAs($this->admin)
            ->patchJson(route('admin.storefront.home.sections.reorder'), [
                'expected_version' => $version, 'section_keys' => $target,
                'publish_after_sort' => true,
            ])->assertOk()->assertJson([
                'saved' => true, 'published' => false, 'lock_version' => $version + 1,
            ]);
        $after = $this->builder->inspect();
        self::assertNull($after['published']);
        self::assertSame($target, array_column($after['draft']['sections'], 'section_key'));
    }

    public function test_up_down_fallback_uses_same_publication_contract(): void
    {
        [$old, $state] = $this->publishedWithTwoSections();
        $first = $state['draft']['sections'][0]['section_key'];
        $version = (int) $state['page']['lock_version'];
        $this->actingAs($this->admin)
            ->patch(route('admin.storefront.home.sections.move', $first), [
                'expected_version' => $version, 'direction' => 'down',
                'publish_after_sort' => true,
            ])->assertRedirect(route('admin.storefront.home.index'));
        $after = $this->builder->inspect();
        self::assertSame($version + 3, (int) $after['page']['lock_version']);
        self::assertSame($first, $after['published']['sections'][1]['section_key']);
        self::assertSame($first, $after['draft']['sections'][1]['section_key']);
        self::assertNotSame($old['id'], $after['published']['id']);
    }
}
