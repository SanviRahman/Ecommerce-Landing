<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\CreatePage;
use App\Models\MediaLibraryAsset;
use App\Models\Product;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaManagementController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;

    private function adminOnly(): void
    {
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }
    }

    /**
     * Media group map. This keeps sidebar/submenu context and filter logic centralized.
     */
    private function groups(): array
    {
        return [
            'all' => [
                'title' => 'All Media',
                'description' => 'All Spatie Media Library files from the whole admin project.',
                'model_types' => [],
                'collections' => [],
            ],

            'category' => [
                'title' => 'Category Media',
                'description' => 'Category image files.',
                'model_types' => [Category::class],
                'collections' => ['category_image'],
            ],

            'products' => [
                'title' => 'Products Media',
                'description' => 'Product thumbnail and product gallery files.',
                'model_types' => [Product::class],
                'collections' => ['product_thumbnail', 'product_gallery'],
            ],

            'campaign' => [
                'title' => 'Campaign Media',
                'description' => 'All landing page / campaign media files.',
                'model_types' => [Campaign::class, Review::class],
                'collections' => [
                    'banner_image',
                    'image_one',
                    'image_two',
                    'image_three',
                    'review_image',
                    'campaign_video',
                    'hero_slider_images',
                    'campaign_product_gallery',
                    'review_customer_image',
                ],
            ],

            'campaign.hero' => [
                'title' => 'Campaign Hero Media',
                'description' => 'Hero banner, hero slider and campaign video files.',
                'model_types' => [Campaign::class],
                'collections' => ['banner_image', 'hero_slider_images', 'campaign_video'],
            ],

            'campaign.section' => [
                'title' => 'Campaign Section Media',
                'description' => 'Campaign section image one, image two and image three files.',
                'model_types' => [Campaign::class],
                'collections' => ['image_one', 'image_two', 'image_three'],
            ],

            'campaign.product' => [
                'title' => 'Campaign Product Media',
                'description' => 'Campaign product gallery files.',
                'model_types' => [Campaign::class],
                'collections' => ['campaign_product_gallery'],
            ],

            'campaign.review' => [
                'title' => 'Campaign Review Media',
                'description' => 'Campaign review image and customer review image files.',
                'model_types' => [Campaign::class, Review::class],
                'collections' => ['review_image', 'review_customer_image'],
            ],

            'campaign.gallery' => [
                'title' => 'Campaign Gallery Media',
                'description' => 'Campaign gallery / product gallery images.',
                'model_types' => [Campaign::class],
                'collections' => ['campaign_product_gallery'],
            ],

            'other' => [
                'title' => 'Other Media',
                'description' => 'Banner, page, site setting and user profile media files.',
                'model_types' => [Banner::class, CreatePage::class, MediaLibraryAsset::class, SiteSetting::class, User::class],
                'collections' => [
                    'banner_image',
                    'page_banner',
                    'site_logo',
                    'site_white_logo',
                    'site_favicon',
                    'avatars',
                    'library',
                ],
            ],
        ];
    }

    private function sectionKey(?string $section): string
    {
        return match ($section) {
            'hero' => 'campaign.hero',
            'section' => 'campaign.section',
            'product' => 'campaign.product',
            'review' => 'campaign.review',
            'gallery' => 'campaign.gallery',
            default => 'campaign',
        };
    }

    private function modelLabels(): array
    {
        return [
            Banner::class => 'Banner',
            Category::class => 'Category',
            Product::class => 'Product',
            Campaign::class => 'Campaign',
            Review::class => 'Review',
            CreatePage::class => 'Create Page',
            SiteSetting::class => 'Site Setting',
            User::class => 'User',
            MediaLibraryAsset::class => 'Shared Media Library',
        ];
    }

    private function collectionLabels(): array
    {
        return [
            'category_image' => 'Category Image',
            'product_thumbnail' => 'Product Thumbnail',
            'product_gallery' => 'Product Gallery',
            'banner_image' => 'Banner / Hero Image',
            'image_one' => 'Section Image One',
            'image_two' => 'Section Image Two',
            'image_three' => 'Section Image Three',
            'review_image' => 'Campaign Review Image',
            'campaign_video' => 'Campaign Video',
            'hero_slider_images' => 'Hero Slider Images',
            'campaign_product_gallery' => 'Campaign Product Gallery',
            'review_customer_image' => 'Customer Review Image',
            'page_banner' => 'Page Banner',
            'site_logo' => 'Site Logo',
            'site_white_logo' => 'Site White Logo',
            'site_favicon' => 'Site Favicon',
            'avatars' => 'User Avatar',
            'library' => 'Shared Media Library',
        ];
    }

    private function hasTrashColumns(): bool
    {
        return Schema::hasColumn('media', 'trashed_at');
    }

    private function normalizeContext(?string $context): string
    {
        $groups = $this->groups();

        return array_key_exists((string) $context, $groups) ? (string) $context : 'all';
    }

    /**
     * Normalize filter values coming from browser autocomplete, old query strings,
     * translated labels, or plural option names. This makes search/filter stable.
     */
    private function normalizedFilterValue(mixed $value, string $default = 'all'): string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return $default;
        }

        return Str::of($value)
            ->lower()
            ->replace([' ', '-'], '_')
            ->toString();
    }

    /**
     * Search owner model display fields without assuming every model has the same columns.
     */
    private function applyOwnerSearch(Builder $query, string $search): void
    {
        $modelTypes = array_keys($this->modelLabels());
        $searchableColumns = ['title', 'name', 'page_name', 'website_name', 'customer_name', 'email', 'slug'];

        $query->orWhereHasMorph('model', $modelTypes, function (Builder $modelQuery, string $type) use ($search, $searchableColumns) {
            try {
                $model = new $type();
                $table = $model->getTable();
            } catch (\Throwable $exception) {
                return;
            }

            $availableColumns = collect($searchableColumns)
                ->filter(fn (string $column) => Schema::hasColumn($table, $column))
                ->values();

            if ($availableColumns->isEmpty()) {
                return;
            }

            $modelQuery->where(function (Builder $ownerQuery) use ($availableColumns, $search) {
                foreach ($availableColumns as $column) {
                    $ownerQuery->orWhere($column, 'like', "%{$search}%");
                }
            });
        });
    }

    private function baseMediaQuery(bool $trash = false): Builder
    {
        $query = Media::query()
            ->with('model')
            ->latest('id');

        if ($this->hasTrashColumns()) {
            $trash
                ? $query->whereNotNull('trashed_at')
                : $query->whereNull('trashed_at');
        }

        return $query;
    }

    private function applyContext(Builder $query, string $context): Builder
    {
        $groups = $this->groups();
        $group = $groups[$context] ?? $groups['all'];

        if (! empty($group['model_types'])) {
            $query->whereIn('model_type', $group['model_types']);
        }

        if (! empty($group['collections'])) {
            $query->whereIn('collection_name', $group['collections']);
        }

        return $query;
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        $search = trim((string) $request->input('search', ''));

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%")
                    ->orWhere('collection_name', 'like', "%{$search}%")
                    ->orWhere('mime_type', 'like', "%{$search}%")
                    ->orWhere('model_type', 'like', "%{$search}%")
                    ->orWhere('disk', 'like', "%{$search}%");

                $this->applyOwnerSearch($q, $search);
            });
        }

        $collection = trim((string) $request->input('collection', 'all'));
        if ($collection !== '' && $this->normalizedFilterValue($collection) !== 'all') {
            $query->where('collection_name', $collection);
        }

        $type = $this->normalizedFilterValue($request->input('type', 'all'));
        $type = match ($type) {
            'images' => 'image',
            'videos' => 'video',
            'documents', 'document_file', 'files', 'file' => 'document',
            default => $type,
        };

        if ($type !== 'all') {
            match ($type) {
                'image' => $query->where('mime_type', 'like', 'image/%'),
                'video' => $query->where('mime_type', 'like', 'video/%'),
                'document' => $query->where(function (Builder $q) {
                    $q->where('mime_type', 'like', 'application/%')
                        ->orWhere('mime_type', 'like', 'text/%');
                }),
                default => null,
            };
        }

        $disk = trim((string) $request->input('disk', 'all'));
        if ($disk !== '' && $this->normalizedFilterValue($disk) !== 'all') {
            $query->where('disk', $disk);
        }

        return $query;
    }

    private function listResponse(Request $request, string $context = 'all', bool $isTrash = false)
    {
        $this->adminOnly();

        abort_if(! Schema::hasTable('media'), 500, 'Media table not found. Please install/run Spatie Media Library migration.');

        $context = $this->normalizeContext($context);
        $groups = $this->groups();
        $group = $groups[$context];

        $query = $this->applyContext($this->baseMediaQuery($isTrash), $context);
        $query = $this->applyFilters($query, $request);

        $perPage = (int) $request->input('per_page', self::DEFAULT_PER_PAGE);
        if (! in_array($perPage, [12, 24, 48, 96], true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        $mediaItems = $query->paginate($perPage)->withQueryString();

        $statsQuery = $this->applyContext($this->baseMediaQuery($isTrash), $context);
        $stats = $this->stats(clone $statsQuery);

        $filterBaseQuery = $this->applyContext($this->baseMediaQuery($isTrash), $context);

        $collections = (clone $filterBaseQuery)
            ->select('collection_name')
            ->distinct()
            ->orderBy('collection_name')
            ->pluck('collection_name')
            ->filter()
            ->values();

        $disks = (clone $filterBaseQuery)
            ->select('disk')
            ->distinct()
            ->orderBy('disk')
            ->pluck('disk')
            ->filter()
            ->values();

        $title = $isTrash ? $group['title'] . ' Trash Bin' : $group['title'];

        $data = [
            'title' => $title,
            'description' => $group['description'],
            'context' => $context,
            'isTrash' => $isTrash,
            'mediaItems' => $mediaItems,
            'stats' => $stats,
            'groups' => $groups,
            'collections' => $collections,
            'disks' => $disks,
            'modelLabels' => $this->modelLabels(),
            'collectionLabels' => $this->collectionLabels(),
            'trashEnabled' => $this->hasTrashColumns(),
            'breadcrumb' => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Media Management', 'url' => route('admin.media-management.index')],
                ['text' => $title, 'url' => url()->current()],
            ],
        ];

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'html' => view('admin.media-management.partials.table', $data)->render(),
                'stats' => $stats,
            ]);
        }

        return view('admin.media-management.index', $data);
    }

    private function stats(Builder $query): array
    {
        return [
            'total' => (clone $query)->count(),
            'images' => (clone $query)->where('mime_type', 'like', 'image/%')->count(),
            'videos' => (clone $query)->where('mime_type', 'like', 'video/%')->count(),
            'documents' => (clone $query)->where(function (Builder $q) {
                $q->where('mime_type', 'like', 'application/%')
                    ->orWhere('mime_type', 'like', 'text/%');
            })->count(),
            'size' => $this->humanFileSize((int) (clone $query)->sum('size')),
        ];
    }


    /**
     * Reusable media-browser endpoint used by all admin create/edit forms.
     * Only active media with an existing physical file is returned.
     */
    public function browser(Request $request): JsonResponse
    {
        $this->adminOnly();

        abort_if(! Schema::hasTable('media'), 500, 'Media table not found.');

        $perPage = min(max((int) $request->input('per_page', 20), 8), 48);
        $query = $this->baseMediaQuery(false);

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search) {
                $searchQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%")
                    ->orWhere('collection_name', 'like', "%{$search}%")
                    ->orWhere('mime_type', 'like', "%{$search}%")
                    ->orWhere('model_type', 'like', "%{$search}%");

                $this->applyOwnerSearch($searchQuery, $search);
            });
        }

        $type = $this->normalizedFilterValue($request->input('type', 'all'));
        $type = match ($type) {
            'images' => 'image',
            'videos' => 'video',
            'documents', 'document_file', 'files' => 'document',
            default => $type,
        };

        if (! in_array($type, ['all', 'image', 'video', 'document'], true)) {
            $type = 'all';
        }

        if ($type === 'image') {
            $query->where('mime_type', 'like', 'image/%');
        } elseif ($type === 'video') {
            $query->where('mime_type', 'like', 'video/%');
        } elseif ($type === 'document') {
            $query->where(function (Builder $documentQuery) {
                $documentQuery->where('mime_type', 'like', 'application/%')
                    ->orWhere('mime_type', 'like', 'text/%');
            });
        }

        $collection = trim((string) $request->input('collection', 'all'));
        if ($collection !== '' && $collection !== 'all') {
            $query->where('collection_name', $collection);
        }

        $mediaItems = $query->paginate($perPage)->withQueryString();

        $items = $mediaItems->getCollection()
            ->map(function (Media $media) {
                $path = null;
                $exists = false;

                try {
                    $path = $media->getPath();
                    $exists = $path && File::exists($path);
                } catch (\Throwable $exception) {
                    $exists = false;
                }

                return [
                    'id' => (int) $media->id,
                    'name' => (string) $media->name,
                    'file_name' => (string) $media->file_name,
                    'mime_type' => (string) $media->mime_type,
                    'collection' => (string) $media->collection_name,
                    'collection_label' => $this->collectionLabels()[$media->collection_name]
                        ?? ucwords(str_replace('_', ' ', $media->collection_name)),
                    'owner' => $this->ownerLabel($media),
                    'owner_type' => $this->modelLabels()[$media->model_type]
                        ?? class_basename($media->model_type),
                    'size' => $this->humanFileSize((int) $media->size),
                    'size_bytes' => (int) $media->size,
                    'url' => $this->mediaUrl($media),
                    'download_url' => route('admin.media-management.download', $media->id),
                    'delete_url' => route('admin.media-management.destroy', $media->id),
                    'exists' => $exists,
                    'created_at' => optional($media->created_at)->format('d M Y, h:i A'),
                ];
            })
            ->values();

        $collectionQuery = $this->baseMediaQuery(false);
        if ($type === 'image') {
            $collectionQuery->where('mime_type', 'like', 'image/%');
        } elseif ($type === 'video') {
            $collectionQuery->where('mime_type', 'like', 'video/%');
        } elseif ($type === 'document') {
            $collectionQuery->where(function (Builder $documentQuery) {
                $documentQuery->where('mime_type', 'like', 'application/%')
                    ->orWhere('mime_type', 'like', 'text/%');
            });
        }

        $collections = $collectionQuery
            ->select('collection_name')
            ->distinct()
            ->orderBy('collection_name')
            ->pluck('collection_name')
            ->filter()
            ->map(fn (string $name) => [
                'value' => $name,
                'label' => $this->collectionLabels()[$name] ?? ucwords(str_replace('_', ' ', $name)),
            ])
            ->values();

        return response()->json([
            'status' => true,
            'items' => $items,
            'collections' => $collections,
            'pagination' => [
                'current_page' => $mediaItems->currentPage(),
                'last_page' => $mediaItems->lastPage(),
                'per_page' => $mediaItems->perPage(),
                'total' => $mediaItems->total(),
                'from' => $mediaItems->firstItem(),
                'to' => $mediaItems->lastItem(),
            ],
        ]);
    }

    /**
     * Upload new reusable files into a dedicated shared-media owner model.
     */
    public function browserUpload(Request $request): JsonResponse
    {
        $this->adminOnly();

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => [
                'required',
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,webp,gif,svg,mp4,webm,pdf,doc,docx,xls,xlsx,csv,txt',
            ],
        ]);

        $library = MediaLibraryAsset::query()->firstOrCreate(
            ['name' => 'Shared Media Library'],
            ['status' => true]
        );

        $uploaded = 0;

        foreach ($validated['files'] as $file) {
            $library
                ->addMedia($file)
                ->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                ->usingFileName($this->safeUniqueFileName($file->getClientOriginalName()))
                ->toMediaCollection('library', 'public');

            $uploaded++;
        }

        return response()->json([
            'status' => true,
            'message' => $uploaded . ' media file(s) uploaded successfully.',
            'uploaded' => $uploaded,
        ]);
    }

    private function safeUniqueFileName(string $originalName): string
    {
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $baseName = Str::slug((string) pathinfo($originalName, PATHINFO_FILENAME));
        $baseName = $baseName !== '' ? $baseName : 'media';

        return $baseName . '-' . now()->format('YmdHis') . '-' . Str::lower(Str::random(6))
            . ($extension !== '' ? '.' . $extension : '');
    }

    public function index(Request $request)
    {
        return $this->listResponse($request, 'all');
    }

    public function category(Request $request)
    {
        return $this->listResponse($request, 'category');
    }

    public function products(Request $request)
    {
        return $this->listResponse($request, 'products');
    }

    public function campaign(Request $request)
    {
        return $this->listResponse($request, 'campaign');
    }

    public function campaignSection(Request $request, string $section)
    {
        return $this->listResponse($request, $this->sectionKey($section));
    }

    public function other(Request $request)
    {
        return $this->listResponse($request, 'other');
    }

    public function trash(Request $request)
    {
        return $this->listResponse($request, $this->normalizeContext($request->input('context', 'all')), true);
    }

    public function edit(Media $media): JsonResponse
    {
        $this->adminOnly();

        $media->load('model');

        return response()->json([
            'status' => true,
            'html' => view('admin.media-management.partials.edit', [
                'media' => $media,
                'modelLabel' => $this->modelLabels()[$media->model_type] ?? class_basename($media->model_type),
                'collectionLabel' => $this->collectionLabels()[$media->collection_name] ?? ucwords(str_replace('_', ' ', $media->collection_name)),
                'ownerLabel' => $this->ownerLabel($media),
                'url' => $this->mediaUrl($media),
            ])->render(),
        ]);
    }

    public function update(Request $request, Media $media): JsonResponse
    {
        $this->adminOnly();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
        ]);

        $customProperties = $media->custom_properties ?? [];
        $customProperties['alt_text'] = $validated['alt_text'] ?? null;
        $customProperties['title'] = $validated['title'] ?? null;
        $customProperties['caption'] = $validated['caption'] ?? null;

        $media->forceFill([
            'name' => $validated['name'],
            'custom_properties' => array_filter($customProperties, fn ($value) => $value !== null && $value !== ''),
        ])->save();

        return response()->json([
            'status' => true,
            'message' => 'Media information updated successfully.',
        ]);
    }

    public function replace(Request $request, Media $media): JsonResponse
    {
        $this->adminOnly();

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,webp,gif,svg,mp4,webm,pdf,doc,docx,xls,xlsx,csv',
            ],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->hasTrashColumns() && $media->trashed_at) {
            return response()->json([
                'status' => false,
                'message' => 'Restore this media before replacing the file.',
            ], 422);
        }

        $uploadedFile = $request->file('file');
        $uploadedMimeType = strtolower((string) $uploadedFile?->getMimeType());
        $currentMimeType = strtolower((string) $media->mime_type);

        $currentIsImage = str_starts_with($currentMimeType, 'image/');
        $currentIsVideo = str_starts_with($currentMimeType, 'video/');
        $uploadedIsImage = str_starts_with($uploadedMimeType, 'image/');
        $uploadedIsVideo = str_starts_with($uploadedMimeType, 'video/');

        if (($currentIsImage && ! $uploadedIsImage) || ($currentIsVideo && ! $uploadedIsVideo)) {
            return response()->json([
                'status' => false,
                'message' => $currentIsImage
                    ? 'Please select an image file for this media item.'
                    : 'Please select a video file for this media item.',
            ], 422);
        }

        $model = $media->model;

        if (! $model || ! method_exists($model, 'addMediaFromRequest')) {
            return response()->json([
                'status' => false,
                'message' => 'This media item is not attached to a valid media model.',
            ], 422);
        }

        $collectionName = $media->collection_name;
        $disk = $media->disk ?: 'public';
        $oldMediaId = $media->id;
        $customProperties = $media->custom_properties ?? [];

        $newMedia = $model
            ->addMediaFromRequest('file')
            ->usingName($validated['name'] ?? $media->name)
            ->withCustomProperties($customProperties)
            ->toMediaCollection($collectionName, $disk);

        $oldMedia = Media::query()->whereKey($oldMediaId)->first();
        if ($oldMedia && (int) $newMedia->id !== (int) $oldMedia->id) {
            $oldMedia->delete();
        }

        return response()->json([
            'status' => true,
            'message' => 'Media file replaced successfully.',
        ]);
    }

    /**
     * Move media to Trash Bin. This does NOT remove the physical file.
     */
    public function destroy(Media $media): JsonResponse
    {
        $this->adminOnly();

        if (! $this->hasTrashColumns()) {
            return response()->json([
                'status' => false,
                'message' => 'Media trash columns are missing. Please run the included migration first.',
            ], 422);
        }

        if (! $media->trashed_at) {
            $media->forceFill([
                'trashed_at' => now(),
                'trashed_by' => auth()->id(),
            ])->save();
        }

        return response()->json([
            'status' => true,
            'message' => 'Media moved to trash successfully.',
        ]);
    }

    /**
     * Resolve media safely for restore/force delete.
     *
     * Important:
     * Older route used {id}, while implicit model binding needs {media}.
     * If a route parameter name mismatch happens, Laravel may inject a new empty
     * Media model. This helper guarantees we always operate on an existing row.
     */
    private function resolveMediaItem(mixed $media): Media
    {
        if ($media instanceof Media && $media->exists) {
            return $media;
        }

        $mediaId = $media instanceof Media ? $media->getKey() : $media;

        return Media::query()->whereKey($mediaId)->firstOrFail();
    }

    public function restore(mixed $media): JsonResponse
    {
        $this->adminOnly();

        if (! $this->hasTrashColumns()) {
            return response()->json([
                'status' => false,
                'message' => 'Media trash columns are missing. Please run the included migration first.',
            ], 422);
        }

        $media = $this->resolveMediaItem($media);

        if (! $media->trashed_at) {
            return response()->json([
                'status' => true,
                'message' => 'Media is already active.',
            ]);
        }

        $media->forceFill([
            'trashed_at' => null,
            'trashed_by' => null,
        ])->save();

        return response()->json([
            'status' => true,
            'message' => 'Media restored successfully.',
        ]);
    }

    /**
     * Permanently remove DB row + physical file through Spatie Media Library.
     */
    public function forceDelete(mixed $media): JsonResponse
    {
        $this->adminOnly();

        $media = $this->resolveMediaItem($media);

        if ($this->hasTrashColumns() && ! $media->trashed_at) {
            return response()->json([
                'status' => false,
                'message' => 'Only trashed media can be permanently deleted. Move it to trash first.',
            ], 422);
        }

        try {
            $media->delete();
        } catch (\Throwable $exception) {
            return response()->json([
                'status' => false,
                'message' => 'Media permanent delete failed: ' . $exception->getMessage(),
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'Media permanently deleted successfully.',
        ]);
    }

    public function multipleAction(Request $request): JsonResponse
    {
        $this->adminOnly();

        $validated = $request->validate([
            'action' => ['required', Rule::in(['delete', 'restore', 'force_delete'])],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:media,id'],
        ]);

        $action = $validated['action'];
        $ids = $validated['ids'];

        if (in_array($action, ['delete', 'restore'], true) && ! $this->hasTrashColumns()) {
            return response()->json([
                'status' => false,
                'message' => 'Media trash columns are missing. Please run the included migration first.',
            ], 422);
        }

        if ($action === 'delete') {
            $mediaItems = Media::query()
                ->whereIn('id', $ids)
                ->when($this->hasTrashColumns(), fn (Builder $query) => $query->whereNull('trashed_at'))
                ->get();

            foreach ($mediaItems as $media) {
                $media->forceFill([
                    'trashed_at' => now(),
                    'trashed_by' => auth()->id(),
                ])->save();
            }

            return response()->json([
                'status' => true,
                'message' => $mediaItems->count() . ' selected media file(s) moved to trash.',
            ]);
        }

        if ($action === 'restore') {
            $mediaItems = Media::query()
                ->whereIn('id', $ids)
                ->whereNotNull('trashed_at')
                ->get();

            foreach ($mediaItems as $media) {
                $media->forceFill([
                    'trashed_at' => null,
                    'trashed_by' => null,
                ])->save();
            }

            return response()->json([
                'status' => true,
                'message' => $mediaItems->count() . ' selected media file(s) restored.',
            ]);
        }

        $mediaItems = Media::query()
            ->whereIn('id', $ids)
            ->when($this->hasTrashColumns(), fn (Builder $query) => $query->whereNotNull('trashed_at'))
            ->get();

        foreach ($mediaItems as $media) {
            try {
                $media->delete();
            } catch (\Throwable $exception) {
                return response()->json([
                    'status' => false,
                    'message' => 'Bulk force delete failed: ' . $exception->getMessage(),
                ], 500);
            }
        }

        return response()->json([
            'status' => true,
            'message' => $mediaItems->count() . ' selected media file(s) permanently deleted.',
        ]);
    }

    public function download(Media $media)
    {
        $this->adminOnly();

        $path = $media->getPath();

        if (! $path || ! File::exists($path)) {
            return back()->with('error', 'Media file not found on storage disk.');
        }

        return response()->download($path, $media->file_name);
    }

    public function mediaUrl(Media $media): string
    {
        try {
            return $media->getFullUrl() ?: $media->getUrl();
        } catch (\Throwable $exception) {
            return asset('vendor/adminlte/dist/img/no-image.png');
        }
    }

    public function ownerLabel(Media $media): string
    {
        $model = $media->model;

        if (! $model) {
            return 'Missing Owner';
        }

        foreach (['title', 'name', 'page_name', 'website_name', 'customer_name', 'email'] as $attribute) {
            if (! empty($model->{$attribute})) {
                return (string) $model->{$attribute};
            }
        }

        return class_basename($media->model_type) . ' #' . $media->model_id;
    }

    public function humanFileSize(int|float|null $bytes): string
    {
        $bytes = (float) ($bytes ?? 0);

        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor(log($bytes, 1024));
        $factor = min($factor, count($units) - 1);

        return round($bytes / (1024 ** $factor), 2) . ' ' . $units[$factor];
    }
}
