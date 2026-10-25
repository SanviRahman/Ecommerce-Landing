<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Services\Storefront\HomepageBuilder;
use App\Support\HomeSectionSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/** Staged authenticated P05 admin controls; no public homepage routing changes. */
final class HomepageBuilderController extends Controller
{
    public function index(HomepageBuilder $builder): View
    {
        $this->adminOnly($builder);
        $state = $builder->inspect();
        $extraProducts = [];
        $extraCategories = [];
        foreach ($state['draft']['sections'] ?? [] as $section) {
            $extraProducts = array_merge($extraProducts, $section['products']);
            $extraCategories = array_merge($extraCategories, $section['categories']);
        }
        $selectedProducts = Product::query()->whereIn('id', array_unique($extraProducts))->where('status', true)->get(['id', 'name']);
        $selectedCategories = Category::query()->whereIn('id', array_unique($extraCategories))->where('status', true)->get(['id', 'name']);
        $productChoices = Product::query()->where('status', true)->orderBy('name')->limit(200)->get(['id', 'name']);
        $categoryChoices = Category::query()->where('status', true)->orderBy('name')->limit(150)->get(['id', 'name']);
        return view('admin.storefront.homepage', [
            'state' => $state,
            'history' => $builder->history(),
            'types' => HomeSectionSchema::TYPES,
            'themeDefaults' => HomeSectionSchema::DEFAULT_THEME,
            'categories' => $categoryChoices->merge($selectedCategories)->unique('id')->sortBy('name')->values(),
            'products' => $productChoices->merge($selectedProducts)->unique('id')->sortBy('name')->values(),
            'reviews' => Review::query()->whereNull('campaign_id')->where('status', true)->orderByDesc('id')
                ->limit(50)->get(['id', 'customer_name']),
            'mediaChoices' => DB::table('media')->where('disk', 'public')
                ->whereIn('collection_name', HomepageBuilder::PUBLIC_IMAGE_COLLECTIONS)->orderByDesc('id')
                ->limit(80)->get(['id', 'file_name']),
        ]);
    }

    public function start(Request $request, HomepageBuilder $builder): RedirectResponse
    {
        $this->adminOnly($builder);
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:0']]);
        $builder->startDraft((int) $data['expected_version'], (int) auth()->id());
        return $this->back('Homepage draft is ready. Public homepage was not changed.');
    }

    public function restore(Request $request, int $revision, HomepageBuilder $builder): RedirectResponse
    {
        $this->adminOnly($builder);
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:0']]);
        $builder->startDraft((int) $data['expected_version'], (int) auth()->id(), $revision);
        return $this->back('A NEW editable draft was cloned from that revision. Published data is unchanged.');
    }

    public function addSection(Request $request, HomepageBuilder $builder): RedirectResponse
    {
        $this->adminOnly($builder);
        $data = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', 'in:' . implode(',', array_keys(HomeSectionSchema::TYPES))],
        ]);
        $builder->addSection((int) $data['expected_version'], (int) auth()->id(), $data['type']);
        return $this->back('Draft section added. It stays disabled until configured.');
    }

    public function saveSection(Request $request, string $sectionKey, HomepageBuilder $builder): RedirectResponse
    {
        $this->adminOnly($builder);
        $data = $request->validate(array_merge([
            'expected_version' => ['required', 'integer', 'min:1'],
            'title' => ['nullable', 'string', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:3000'],
            'is_enabled' => ['nullable', 'boolean'],
            'show_desktop' => ['nullable', 'boolean'],
            'show_mobile' => ['nullable', 'boolean'],
            'category_ids' => ['sometimes', 'array', 'max:24'],
            'category_ids.*' => ['integer', 'min:1', 'distinct'],
            'product_ids' => ['sometimes', 'array', 'max:50'],
            'product_ids.*' => ['integer', 'min:1', 'distinct'],
            'styles' => ['sometimes', 'array:' . implode(',', HomeSectionSchema::STYLE_KEYS)],
        ], $this->colorRules('styles')));
        $data['category_ids'] = array_map('intval', $data['category_ids'] ?? []);
        $data['product_ids'] = array_map('intval', $data['product_ids'] ?? []);
        $builder->updateSection((int) $data['expected_version'], (int) auth()->id(), $sectionKey, $data);
        return $this->back('Draft section saved. Public publication is unchanged.');
    }

    public function move(Request $request, string $sectionKey, HomepageBuilder $builder): RedirectResponse
    {
        $this->adminOnly($builder);
        $data = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'direction' => ['required', 'in:up,down'],
        ]);
        $builder->move((int) $data['expected_version'], (int) auth()->id(), $sectionKey, $data['direction']);
        return $this->back('Section order saved.');
    }

    /** P06: Same protected, draft-only endpoint for HTML fallback and drag UI. */
    public function reorder(Request $request, HomepageBuilder $builder): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->adminOnly($builder);
        $data = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'section_keys' => ['required', 'array', 'max:30'],
            'section_keys.*' => ['required', 'uuid', 'distinct'],
        ]);
        $builder->reorder((int) $data['expected_version'], (int) auth()->id(), $data['section_keys']);

        if ($request->expectsJson()) {
            // HomepageBuilder bumps the optimistic version exactly once on success.
            return response()->json([
                'saved' => true,
                'lock_version' => (int) $data['expected_version'] + 1,
                'section_keys' => array_values($data['section_keys']),
            ])->header('Cache-Control', 'no-store');
        }

        return $this->back('Exact section order persisted.');
    }

    public function deleteSection(Request $request, string $sectionKey, HomepageBuilder $builder): RedirectResponse
    {
        $this->adminOnly($builder);
        $version = $request->validate(['expected_version' => ['required', 'integer', 'min:1']]);
        $builder->deleteSection((int) $version['expected_version'], (int) auth()->id(), $sectionKey);
        return $this->back('Draft section removed; published revisions remain intact.');
    }

    public function addItem(Request $request, string $sectionKey, HomepageBuilder $builder): RedirectResponse
    {
        $this->adminOnly($builder);
        $data = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'title' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'link_url' => ['nullable', 'string', 'max:2048'],
            'link_label' => ['nullable', 'string', 'max:80'],
            'media_id' => ['nullable', 'integer', 'min:1'],
            'review_id' => ['nullable', 'integer', 'min:1'],
        ]);
        foreach (['media_id', 'review_id'] as $id) {
            if (isset($data[$id])) {
                $data[$id] = (int) $data[$id];
            }
        }
        $builder->addItem((int) $data['expected_version'], (int) auth()->id(), $sectionKey, $data);
        return $this->back('Draft item added.');
    }

    public function deleteItem(Request $request, string $sectionKey, string $itemKey,
        HomepageBuilder $builder): RedirectResponse
    {
        $this->adminOnly($builder);
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1']]);
        $builder->deleteItem((int) $data['expected_version'], (int) auth()->id(), $sectionKey, $itemKey);
        return $this->back('Draft item removed.');
    }

    public function theme(Request $request, HomepageBuilder $builder): RedirectResponse
    {
        $this->adminOnly($builder);
        $data = $request->validate(array_merge([
            'expected_version' => ['required', 'integer', 'min:1'],
            'theme' => ['required', 'array:' . implode(',', HomeSectionSchema::STYLE_KEYS)],
        ], $this->colorRules('theme')));
        $builder->saveTheme((int) $data['expected_version'], (int) auth()->id(), $data['theme']);
        return $this->back('Global draft theme saved.');
    }

    public function publish(Request $request, HomepageBuilder $builder): RedirectResponse
    {
        $this->adminOnly($builder);
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1']]);
        $builder->publish((int) $data['expected_version'], (int) auth()->id());
        return $this->back('Revision published in P05 staging. The public / route remains the campaign homepage.');
    }

    public function preview(Request $request, HomepageBuilder $builder): \Illuminate\Http\Response
    {
        $this->adminOnly($builder);
        $data = $request->validate(['revision' => ['nullable', 'integer', 'min:1']]);
        $state = $builder->inspect();
        $id = isset($data['revision']) ? (int) $data['revision']
            : (int) ($state['draft']['id'] ?? $state['published']['id'] ?? 0);
        abort_if($id === 0, 404, 'No homepage revision to preview.');
        $revision = $builder->revision($id); // Strict same-homepage FK ownership check.

        $productIds = $categoryIds = $reviewIds = $mediaIds = [];
        foreach ($revision['sections'] as $section) {
            if (! $section['is_enabled']) {
                continue;
            }
            $productIds = array_merge($productIds, $section['products']);
            $categoryIds = array_merge($categoryIds, $section['categories']);
            foreach ($section['items'] as $item) {
                if ($item['review_id']) $reviewIds[] = (int) $item['review_id'];
                if ($item['media_id']) $mediaIds[] = (int) $item['media_id'];
            }
        }
        $products = Product::query()->whereIn('id', array_unique($productIds))->where('status', true)->get()->keyBy('id');
        $categories = Category::query()->whereIn('id', array_unique($categoryIds))->where('status', true)->get()->keyBy('id');
        $reviews = Review::query()->whereIn('id', array_unique($reviewIds))->where('status', true)
            ->whereNull('campaign_id')->get()->keyBy('id');
        $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()->whereIn('id', array_unique($mediaIds))
            ->where('disk', 'public')->get()->filter(fn ($m) => is_file($m->getPath()))->keyBy('id');

        return response()->view('storefront.preview.home', compact('revision', 'products', 'categories', 'reviews', 'media'))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'same-origin');
    }

    private function colorRules(string $key): array
    {
        $rules = [];
        foreach (HomeSectionSchema::STYLE_KEYS as $token) {
            $rules[$key . '.' . $token] = ['nullable', 'string', 'regex:/\A#[0-9a-fA-F]{6}\z/D'];
        }
        return $rules;
    }

    private function adminOnly(HomepageBuilder $builder): void
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin() && auth()->user()->is_active, 403);
        try {
            $builder->assertTestSchema();
        } catch (RuntimeException) {
            abort(404, 'P05 homepage builder is available only in the isolated local test database.');
        }
    }

    private function back(string $message): RedirectResponse
    {
        return redirect()->route('admin.storefront.home.index')->with('success', $message);
    }
}
