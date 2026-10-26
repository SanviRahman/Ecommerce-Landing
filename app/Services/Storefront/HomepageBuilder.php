<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Support\HomeSectionSchema;
use App\Support\LocalHomepageAccess;
use App\Support\P02MigrationSafety;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * P05: immutable publications, draft-only mutations, optimistic page version.
 * Database operations require the isolated test DB or explicit P14 local-only consent.
 * Does not affect the existing / or /campaign routes.
 */
final class HomepageBuilder
{
    // Media collection is code-controlled, not selected through arbitrary model types.
    public const PUBLIC_IMAGE_COLLECTIONS = [
        'hero_slider_images', 'banner_image', 'product_thumbnail', 'product_gallery',
        'category_image', 'image_one', 'image_two', 'image_three',
        'campaign_product_gallery', 'review_customer_image',
    ];

    public const PAGE_SLUG = 'home';
    private const LIMIT_SECTIONS = 30;

    public function assertTestSchema(): void
    {
        if (LocalHomepageAccess::configuredForBuilder()) {
            LocalHomepageAccess::assertActualBuilderDatabase();
        } else {
            P02MigrationSafety::assertIsolatedTestDatabase();
        }
        foreach (['storefront_pages', 'storefront_revisions', 'homepage_sections',
            'homepage_section_items', 'homepage_section_products', 'homepage_section_categories',
            'homepage_audit_events'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException('P05 requires the complete P02 homepage schema: ' . $table);
            }
        }
    }

    /** Read-only snapshot, including private draft only after caller authorization. */
    public function inspect(): array
    {
        $this->assertTestSchema();
        $page = DB::table('storefront_pages')->where('slug', self::PAGE_SLUG)->first();
        if ($page === null) {
            return ['page' => null, 'draft' => null, 'published' => null];
        }
        return [
            'page' => (array) $page,
            'draft' => $page->draft_revision_id ? $this->revision((int) $page->draft_revision_id) : null,
            'published' => $page->published_revision_id ? $this->revision((int) $page->published_revision_id) : null,
        ];
    }

    /** Caller must check active admin permission before exposing this revision. */
    public function revision(int $id): array
    {
        $this->assertTestSchema();
        $page = DB::table('storefront_pages')->where('slug', self::PAGE_SLUG)->first();
        abort_unless($page !== null, 404);
        $revision = DB::table('storefront_revisions')
            ->where('id', $id)->where('storefront_page_id', $page->id)->first();
        abort_unless($revision !== null, 404);
        $sections = DB::table('homepage_sections')->where('storefront_revision_id', $id)
            ->orderBy('sort_order')->orderBy('id')->get();
        $output = [];
        foreach ($sections as $s) {
            $section = (array) $s;
            foreach (['layout', 'styles', 'settings'] as $field) {
                $section[$field] = $this->unjson($section[$field]);
            }
            $section['products'] = DB::table('homepage_section_products')->where('homepage_section_id', $s->id)
                ->orderBy('sort_order')->pluck('product_id')->map(fn ($id) => (int) $id)->all();
            $section['categories'] = DB::table('homepage_section_categories')->where('homepage_section_id', $s->id)
                ->orderBy('sort_order')->pluck('category_id')->map(fn ($id) => (int) $id)->all();
            $section['items'] = DB::table('homepage_section_items')->where('homepage_section_id', $s->id)
                ->orderBy('sort_order')->get()->map(function ($item): array {
                    $a = (array) $item;
                    $a['settings'] = $this->unjson($a['settings']);
                    return $a;
                })->all();
            $output[] = $section;
        }
        $result = (array) $revision;
        $result['settings'] = $this->unjson($result['settings']);
        $result['sections'] = $output;
        return $result;
    }

    public function history(): array
    {
        $this->assertTestSchema();
        $page = DB::table('storefront_pages')->where('slug', self::PAGE_SLUG)->first();
        return $page ? DB::table('storefront_revisions')->where('storefront_page_id', $page->id)
            ->orderByDesc('revision_number')->limit(30)->get()->map(fn ($r) => (array) $r)->all() : [];
    }

    public function startDraft(int $expectedVersion, int $actorId, ?int $restoreFrom = null): void
    {
        $this->assertTestSchema();
        DB::transaction(function () use ($expectedVersion, $actorId, $restoreFrom): void {
            $page = $this->lockedPage();
            if ($page === null) {
                $this->assertVersion($expectedVersion, 0);
                if ($restoreFrom !== null) {
                    $this->reject('Cannot restore before a page exists.');
                }
                $inserted = DB::table('storefront_pages')->insertOrIgnore([
                    'slug' => self::PAGE_SLUG, 'mode' => 'storefront', 'lock_version' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($inserted !== 1) {
                    throw new HttpException(409, 'Homepage created in another tab. Reload to continue.');
                }
                $pageId = (int) $this->lockedPage()->id;
                $revId = $this->createRevision($pageId, 1, null, $actorId);
                DB::table('storefront_pages')->where('id', $pageId)->update(['draft_revision_id' => $revId]);
                $this->audit($pageId, $revId, $actorId, 'draft_start', 0, 1, []);
                return;
            }
            $this->assertVersion($expectedVersion, (int) $page->lock_version);
            if ($page->draft_revision_id !== null && $restoreFrom === null) {
                return; // Retry, never make competing drafts.
            }
            if ($page->draft_revision_id !== null) {
                $this->reject('Discard or publish the current draft before restoring a revision.');
            }
            $parent = $restoreFrom ?? ($page->published_revision_id ? (int) $page->published_revision_id : null);
            if ($parent !== null) {
                $exists = DB::table('storefront_revisions')->where('id', $parent)
                    ->where('storefront_page_id', $page->id)->exists();
                if (! $exists) {
                    $this->reject('This historical revision does not belong to the homepage.');
                }
            }
            $revisionNo = (int) DB::table('storefront_revisions')->where('storefront_page_id', $page->id)
                ->max('revision_number') + 1;
            $revId = $this->createRevision((int) $page->id, $revisionNo, $parent, $actorId);
            if ($parent !== null) {
                $this->cloneRevision($parent, $revId);
            }
            $this->bump($page, $revId, $actorId, 'draft_start', ['from_revision' => $parent],
                ['draft_revision_id' => $revId]);
        }, 3);
    }

    public function addSection(int $expectedVersion, int $actorId, string $type): void
    {
        if (! array_key_exists($type, HomeSectionSchema::TYPES)) {
            $this->reject('Unsupported section type.');
        }
        $this->mutate($expectedVersion, $actorId, 'section_add', function (object $page, object $draft) use ($type): array {
            $count = DB::table('homepage_sections')->where('storefront_revision_id', $draft->id)->count();
            if ($count >= self::LIMIT_SECTIONS) {
                $this->reject('Homepage can have at most 30 sections.');
            }
            $key = (string) Str::uuid();
            DB::table('homepage_sections')->insert($this->initialSection((int) $draft->id, $key, $type, (int) $count));
            return ['section_key' => $key, 'type' => $type];
        });
    }

    /** Titles/styles and selected IDs are edited on the draft ONLY. */
    public function updateSection(int $expectedVersion, int $actorId, string $key, array $data): void
    {
        $this->mutate($expectedVersion, $actorId, 'section_save', function (object $page, object $draft) use ($key, $data): array {
            $section = $this->draftSection((int) $draft->id, $key);
            try {
                $style = HomeSectionSchema::normalizeColors($data['styles'] ?? []);
            } catch (InvalidArgumentException $e) {
                $this->reject($e->getMessage(), 'styles');
            }
            $enabled = (bool) ($data['is_enabled'] ?? false);
            $desktop = (bool) ($data['show_desktop'] ?? false);
            $mobile = (bool) ($data['show_mobile'] ?? false);
            if ($enabled && ! $desktop && ! $mobile) {
                $this->reject('An enabled section must be visible on desktop or mobile.');
            }
            $currentStyles = $this->unjson($section->styles);
            $currentStyles = array_merge($currentStyles, $style);
            DB::table('homepage_sections')->where('id', $section->id)->update([
                'title' => $data['title'] ?? null, 'subtitle' => $data['subtitle'] ?? null,
                'description' => $data['description'] ?? null,
                'is_enabled' => $enabled, 'show_desktop' => $desktop, 'show_mobile' => $mobile,
                'styles' => json_encode($currentStyles, JSON_THROW_ON_ERROR), 'updated_at' => now(),
            ]);
            if ($section->type === 'category_grid') {
                $this->syncSelection('homepage_section_categories', 'category_id', (int) $section->id,
                    $data['category_ids'] ?? [], 24, 'categories');
            } elseif ($section->type === 'product_list') {
                $this->syncSelection('homepage_section_products', 'product_id', (int) $section->id,
                    $data['product_ids'] ?? [], 50, 'products');
            } elseif ($section->type === 'category_products') {
                $categoryId = (int) ($data['source_category_id'] ?? 0);
                if ($categoryId < 1 || ! DB::table('categories')->where('id', $categoryId)
                    ->where('status', true)->whereNull('deleted_at')->where(function ($query): void {
                        $query->whereNull('parent_id')->orWhereIn('parent_id',
                            DB::table('categories')->where('status', true)->whereNull('deleted_at')->whereNull('parent_id')->select('id'));
                    })->exists()) {
                    $this->reject('Select one active source category.', 'source_category_id');
                }
                $limit = (int) ($data['product_limit'] ?? 8);
                if ($limit < 1 || $limit > 24) {
                    $this->reject('Select between 1 and 24 products.', 'product_limit');
                }
                $sort = (string) ($data['product_sort'] ?? 'newest');
                if (! in_array($sort, ['newest', 'name', 'price_asc', 'price_desc'], true)) {
                    $this->reject('Unsupported product sorting.', 'product_sort');
                }
                DB::table('homepage_sections')->where('id', $section->id)->update([
                    'category_id' => $categoryId,
                    'include_descendants' => (bool) ($data['include_descendants'] ?? false),
                    'product_limit' => $limit,
                    'product_sort' => $sort,
                    'source_type' => 'category', 'updated_at' => now(),
                ]);
            }
            return ['section_key' => $key];
        });
    }

    public function addItem(int $expectedVersion, int $actorId, string $sectionKey, array $data): void
    {
        $this->mutate($expectedVersion, $actorId, 'item_add', function (object $page, object $draft) use ($sectionKey, $data): array {
            $section = $this->draftSection((int) $draft->id, $sectionKey);
            $type = match ($section->type) {
                'hero_slider' => 'hero_slide', 'image_text' => 'image_text', 'reviews' => 'review',
                default => null,
            };
            if ($type === null) {
                $this->reject('This section does not support content items.');
            }
            $count = (int) DB::table('homepage_section_items')->where('homepage_section_id', $section->id)->count();
            $max = $type === 'hero_slide' ? 20 : ($type === 'review' ? 30 : 8);
            if ($count >= $max) {
                $this->reject('Section item limit reached.');
            }
            $media = $data['media_id'] ?? null;
            $review = $data['review_id'] ?? null;
            if ($type === 'review') {
                if (! is_int($review) || $review <= 0 || ! $this->eligibleReview($review)) {
                    $this->reject('Choose an active public/global review.');
                }
                $media = null;
            } else {
                if (! is_int($media) || $media <= 0 || ! $this->eligibleMedia($media)) {
                    $this->reject('Choose an existing approved public media file.');
                }
                $review = null;
            }
            try {
                $link = HomeSectionSchema::normalizeUrl($data['link_url'] ?? null);
            } catch (InvalidArgumentException $e) {
                $this->reject($e->getMessage(), 'link_url');
            }
            DB::table('homepage_section_items')->insert([
                'homepage_section_id' => $section->id, 'item_key' => (string) Str::uuid(),
                'type' => $type, 'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'media_id' => $media, 'review_id' => $review,
                'link_url' => $link, 'link_label' => $data['link_label'] ?? null,
                'is_enabled' => true, 'sort_order' => $count,
                'settings' => '{}', 'created_at' => now(), 'updated_at' => now(),
            ]);
            return ['section_key' => $sectionKey, 'item_type' => $type];
        });
    }

    public function deleteItem(int $expectedVersion, int $actorId, string $sectionKey, string $itemKey): void
    {
        $this->mutate($expectedVersion, $actorId, 'item_delete', function (object $page, object $draft) use ($sectionKey, $itemKey): array {
            $section = $this->draftSection((int) $draft->id, $sectionKey);
            $deleted = DB::table('homepage_section_items')->where('homepage_section_id', $section->id)
                ->where('item_key', $itemKey)->delete();
            if ($deleted !== 1) {
                $this->reject('Item does not belong to the draft section.');
            }
            return ['section_key' => $sectionKey, 'item_key' => $itemKey];
        });
    }

    public function deleteSection(int $expectedVersion, int $actorId, string $sectionKey): void
    {
        $this->mutate($expectedVersion, $actorId, 'section_delete', function (object $page, object $draft) use ($sectionKey): array {
            $section = $this->draftSection((int) $draft->id, $sectionKey);
            $this->removeSectionChildren((int) $section->id);
            DB::table('homepage_sections')->where('id', $section->id)->delete();
            return ['section_key' => $sectionKey];
        });
    }

    /** Exact complete permutation; P06 drag UI and P05 move controls use the same API. */
    public function reorder(int $expectedVersion, int $actorId, array $keys): void
    {
        $this->mutate($expectedVersion, $actorId, 'section_reorder', function (object $page, object $draft) use ($keys): array {
            $stored = DB::table('homepage_sections')->where('storefront_revision_id', $draft->id)
                ->orderBy('sort_order')->orderBy('id')->pluck('section_key')->all();
            if (count($keys) !== count($stored) || count(array_unique($keys)) !== count($keys)
                || array_diff($keys, $stored) !== [] || array_diff($stored, $keys) !== []) {
                $this->reject('Reorder requires every draft section exactly once.');
            }
            foreach ($keys as $position => $key) {
                DB::table('homepage_sections')->where('storefront_revision_id', $draft->id)
                    ->where('section_key', $key)->update(['sort_order' => $position, 'updated_at' => now()]);
            }
            return ['order' => $keys];
        });
    }

    public function move(int $expectedVersion, int $actorId, string $key, string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            $this->reject('Unknown move direction.');
        }
        $this->assertTestSchema();
        // Version check and position changes are done under ONE page-row lock.
        DB::transaction(function () use ($expectedVersion, $actorId, $key, $direction): void {
            [$page, $draft] = $this->lockedDraft($expectedVersion);
            $keys = DB::table('homepage_sections')->where('storefront_revision_id', $draft->id)
                ->orderBy('sort_order')->orderBy('id')->pluck('section_key')->all();
            $at = array_search($key, $keys, true);
            if ($at === false) {
                $this->reject('Section not found in this draft.');
            }
            $other = $direction === 'up' ? $at - 1 : $at + 1;
            if ($other < 0 || $other >= count($keys)) {
                return;
            }
            [$keys[$at], $keys[$other]] = [$keys[$other], $keys[$at]];
            foreach ($keys as $order => $sectionKey) {
                DB::table('homepage_sections')->where('storefront_revision_id', $draft->id)
                    ->where('section_key', $sectionKey)->update(['sort_order' => $order, 'updated_at' => now()]);
            }
            $this->bump($page, (int) $draft->id, $actorId, 'section_reorder', ['order' => $keys]);
        }, 3);
    }

    public function saveTheme(int $expectedVersion, int $actorId, array $theme): void
    {
        try {
            $colors = HomeSectionSchema::normalizeColors($theme);
        } catch (InvalidArgumentException $e) {
            $this->reject($e->getMessage(), 'theme');
        }
        $this->mutate($expectedVersion, $actorId, 'theme_save', function (object $page, object $draft) use ($colors, $actorId): array {
            $settings = $this->unjson($draft->settings);
            $settings['theme'] = array_merge($settings['theme'] ?? [], $colors);
            try {
                HomeSectionSchema::validateThemeContrast($settings['theme']);
            } catch (InvalidArgumentException $e) {
                $this->reject($e->getMessage(), 'theme');
            }
            DB::table('storefront_revisions')->where('id', $draft->id)->update([
                'settings' => json_encode($settings, JSON_THROW_ON_ERROR),
                'updated_by' => $actorId, 'updated_at' => now(),
            ]);
            return ['tokens' => array_keys($colors)];
        });
    }

    public function publish(int $expectedVersion, int $actorId): void
    {
        $this->assertTestSchema();
        DB::transaction(function () use ($expectedVersion, $actorId): void {
            [$page, $draft] = $this->lockedDraft($expectedVersion);
            $this->validateForPublication((int) $draft->id);
            if ($page->published_revision_id !== null) {
                DB::table('storefront_revisions')->where('id', $page->published_revision_id)
                    ->where('status', 'published')->update(['status' => 'archived', 'updated_at' => now()]);
            }
            DB::table('storefront_revisions')->where('id', $draft->id)->update([
                'status' => 'published', 'published_by' => $actorId, 'published_at' => now(),
                'updated_by' => $actorId, 'updated_at' => now(),
            ]);
            $this->bump($page, (int) $draft->id, $actorId, 'publish', [],
                ['published_revision_id' => $draft->id, 'draft_revision_id' => null]);
        }, 3);
    }

    private function mutate(int $version, int $actor, string $event, callable $action): void
    {
        $this->assertTestSchema();
        DB::transaction(function () use ($version, $actor, $event, $action): void {
            [$page, $draft] = $this->lockedDraft($version);
            $summary = $action($page, $draft);
            DB::table('storefront_revisions')->where('id', $draft->id)
                ->update(['updated_by' => $actor, 'updated_at' => now()]);
            $this->bump($page, (int) $draft->id, $actor, $event, $summary);
        }, 3);
    }

    private function validateForPublication(int $revisionId): void
    {
        $revision = DB::table('storefront_revisions')->where('id', $revisionId)->first();
        $settings = $this->unjson($revision->settings);
        try {
            HomeSectionSchema::validateThemeContrast($settings['theme'] ?? []);
        } catch (InvalidArgumentException $e) {
            $this->reject($e->getMessage(), 'theme');
        }
        $sections = DB::table('homepage_sections')->where('storefront_revision_id', $revisionId)
            ->where('is_enabled', true)->orderBy('sort_order')->get();
        if ($sections->isEmpty()) {
            $this->reject('Add and enable at least one populated section before publication.');
        }
        foreach ($sections as $section) {
            if (! isset(HomeSectionSchema::TYPES[$section->type]) || (! $section->show_desktop && ! $section->show_mobile)) {
                $this->reject('Unknown or invisible section cannot be published.');
            }
            $colors = $this->unjson($section->styles);
            try {
                HomeSectionSchema::normalizeColors($colors);
                HomeSectionSchema::validateThemeContrast(array_merge($settings['theme'] ?? [], array_filter($colors, static fn ($value) => $value !== null)));
            } catch (InvalidArgumentException $e) {
                $this->reject('Section ' . $section->section_key . ': ' . $e->getMessage());
            }
            if ($section->type === 'category_products') {
                $category = DB::table('categories')->where('id', $section->category_id)
                    ->where('status', true)->whereNull('deleted_at')->first();
                if (! $category || ($category->parent_id !== null && ! DB::table('categories')->where('id', $category->parent_id)
                    ->where('status', true)->whereNull('deleted_at')->whereNull('parent_id')->exists())
                    || (int) $section->product_limit < 1 || (int) $section->product_limit > 24
                    || ! in_array($section->product_sort, ['newest', 'name', 'price_asc', 'price_desc'], true)) {
                    $this->reject('Category product section needs an active category and valid limit/sort.');
                }
            } elseif ($section->type === 'category_grid' || $section->type === 'product_list') {
                $table = $section->type === 'category_grid' ? 'homepage_section_categories' : 'homepage_section_products';
                $column = $section->type === 'category_grid' ? 'category_id' : 'product_id';
                $base = $section->type === 'category_grid' ? 'categories' : 'products';
                $ids = DB::table($table)->where('homepage_section_id', $section->id)->pluck($column)->all();
                if (count($ids) === 0 || (int) DB::table($base)->whereIn('id', $ids)
                    ->where('status', true)->whereNull('deleted_at')->count() !== count($ids)) {
                    $this->reject('Section ' . $section->section_key . ' has missing/inactive ' . $base . '.');
                }
            } else {
                $items = DB::table('homepage_section_items')->where('homepage_section_id', $section->id)
                    ->where('is_enabled', true)->get();
                if ($items->isEmpty()) {
                    $this->reject('Section ' . $section->section_key . ' needs at least one valid item.');
                }
                foreach ($items as $item) {
                    if ($section->type === 'reviews') {
                        if ($item->type !== 'review' || ! $item->review_id || ! $this->eligibleReview((int) $item->review_id)) {
                            $this->reject('Review is no longer public/active.');
                        }
                    } else {
                        if (($item->type !== ($section->type === 'hero_slider' ? 'hero_slide' : 'image_text'))
                            || ! $item->media_id || ! $this->eligibleMedia((int) $item->media_id)) {
                            $this->reject('A referenced image is missing or unapproved.');
                        }
                        try {
                            HomeSectionSchema::normalizeUrl($item->link_url);
                        } catch (InvalidArgumentException $e) {
                            $this->reject('Unsafe homepage CTA link.');
                        }
                    }
                }
            }
        }
    }

    private function eligibleReview(int $id): bool
    {
        return DB::table('reviews')->where('id', $id)->whereNull('deleted_at')
            ->whereNull('campaign_id')->where('status', true)->exists();
    }

    private function eligibleMedia(int $id): bool
    {
        $media = DB::table('media')->where('id', $id)->where('disk', 'public')
            ->whereIn('collection_name', self::PUBLIC_IMAGE_COLLECTIONS)->first();
        if ($media === null) {
            return false;
        }
        // Never turn a database reference into an unverified file link.
        try {
            $model = \Spatie\MediaLibrary\MediaCollections\Models\Media::find($id);
            return $model !== null && is_file($model->getPath());
        } catch (\Throwable) {
            return false;
        }
    }

    private function syncSelection(string $pivot, string $idColumn, int $sectionId, array $ids,
        int $limit, string $table): void
    {
        if (count($ids) > $limit || count($ids) !== count(array_unique($ids))) {
            $this->reject('Selections contain duplicates or exceed the per-section limit.');
        }
        foreach ($ids as $id) {
            if (! is_int($id) || $id <= 0) {
                $this->reject('Select valid integer ' . $table . ' IDs.');
            }
        }
        if ($ids !== [] && DB::table($table)->whereIn('id', $ids)
            ->where('status', true)->whereNull('deleted_at')->count() !== count($ids)) {
            $this->reject('One or more selected ' . $table . ' are inactive, deleted or missing.');
        }
        DB::table($pivot)->where('homepage_section_id', $sectionId)->delete();
        foreach ($ids as $order => $id) {
            DB::table($pivot)->insert([
                'homepage_section_id' => $sectionId, $idColumn => $id, 'sort_order' => $order,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function initialSection(int $revisionId, string $key, string $type, int $order): array
    {
        return [
            'storefront_revision_id' => $revisionId, 'section_key' => $key, 'type' => $type,
            'title' => HomeSectionSchema::TYPES[$type], 'is_enabled' => false,
            'show_desktop' => true, 'show_mobile' => true, 'sort_order' => $order,
            'source_type' => $type === 'category_products' ? 'category' : (in_array($type, ['product_list', 'category_grid'], true) ? 'manual' : 'none'),
            'product_sort' => 'manual', 'product_limit' => 8,
            'layout' => json_encode(['desktop_columns' => 4, 'mobile_columns' => 2], JSON_THROW_ON_ERROR),
            'styles' => '{}', 'settings' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ];
    }

    private function createRevision(int $pageId, int $number, ?int $base, int $actor): int
    {
        return (int) DB::table('storefront_revisions')->insertGetId([
            'storefront_page_id' => $pageId, 'revision_number' => $number,
            'status' => 'draft', 'schema_version' => 1, 'base_revision_id' => $base,
            'settings' => json_encode(['schema_version' => 1, 'theme' => HomeSectionSchema::DEFAULT_THEME], JSON_THROW_ON_ERROR),
            'created_by' => $actor, 'updated_by' => $actor,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function cloneRevision(int $from, int $to): void
    {
        $source = DB::table('storefront_revisions')->where('id', $from)->first();
        DB::table('storefront_revisions')->where('id', $to)->update(['settings' => $source->settings]);
        foreach (DB::table('homepage_sections')->where('storefront_revision_id', $from)->orderBy('sort_order')->get() as $old) {
            $row = (array) $old;
            unset($row['id']);
            $row['storefront_revision_id'] = $to;
            $row['created_at'] = now();
            $row['updated_at'] = now();
            $newSectionId = DB::table('homepage_sections')->insertGetId($row);
            foreach (['homepage_section_products', 'homepage_section_categories', 'homepage_section_items'] as $table) {
                foreach (DB::table($table)->where('homepage_section_id', $old->id)->get() as $oldChild) {
                    $child = (array) $oldChild;
                    unset($child['id']);
                    $child['homepage_section_id'] = $newSectionId;
                    $child['created_at'] = now();
                    $child['updated_at'] = now();
                    DB::table($table)->insert($child);
                }
            }
        }
    }

    private function removeSectionChildren(int $sectionId): void
    {
        foreach (['homepage_section_items', 'homepage_section_products', 'homepage_section_categories'] as $table) {
            DB::table($table)->where('homepage_section_id', $sectionId)->delete();
        }
    }

    private function lockedPage(): ?object
    {
        return DB::table('storefront_pages')->where('slug', self::PAGE_SLUG)->lockForUpdate()->first();
    }

    /** @return array{0:object, 1:object} */
    private function lockedDraft(int $expected): array
    {
        $page = $this->lockedPage();
        if ($page === null) {
            $this->reject('Start a draft first.');
        }
        $this->assertVersion($expected, (int) $page->lock_version);
        if (! $page->draft_revision_id) {
            $this->reject('Create a new draft before editing a publication.');
        }
        $draft = DB::table('storefront_revisions')->where('id', $page->draft_revision_id)
            ->where('storefront_page_id', $page->id)->where('status', 'draft')->first();
        if ($draft === null) {
            throw new RuntimeException('Draft pointer is invalid. Refusing write.');
        }
        return [$page, $draft];
    }

    private function draftSection(int $revision, string $key): object
    {
        $section = DB::table('homepage_sections')->where('storefront_revision_id', $revision)
            ->where('section_key', $key)->first();
        if ($section === null) {
            $this->reject('Section is missing or belongs to another revision.');
        }
        return $section;
    }

    private function assertVersion(int $expected, int $actual): void
    {
        if ($expected !== $actual) {
            throw new HttpException(409, 'Homepage draft changed in another tab. Current version: ' . $actual . '. Reload before saving.');
        }
    }

    private function bump(object $page, int $revision, int $actor, string $event, array $summary,
        array $changes = []): void
    {
        $from = (int) $page->lock_version;
        DB::table('storefront_pages')->where('id', $page->id)->update(array_merge([
            'lock_version' => $from + 1, 'updated_at' => now(),
        ], $changes));
        $this->audit((int) $page->id, $revision, $actor, $event, $from, $from + 1, $summary);
    }

    private function audit(int $page, int $revision, int $actor, string $event,
        int $from, int $to, array $summary): void
    {
        DB::table('homepage_audit_events')->insert([
            'storefront_page_id' => $page, 'storefront_revision_id' => $revision,
            'actor_id' => $actor, 'event' => $event, 'from_version' => $from, 'to_version' => $to,
            'summary' => json_encode($summary, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }

    private function unjson(?string $value): array
    {
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function reject(string $message, string $field = 'homepage'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
