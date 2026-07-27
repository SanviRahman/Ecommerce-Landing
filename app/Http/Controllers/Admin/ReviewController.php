<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\MediaLibrary\LandingMediaSectionMap;
use App\Models\Review;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ReviewController extends Controller
{
    /**
     * Check if the user is an admin.
     */
    private function adminOnly(): void
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }
    }

    /**
     * Base query for reviews.
     */
    private function reviewQuery(bool $trash = false): Builder
    {
        $query = $trash ? Review::onlyTrashed() : Review::query();
        return $query->with('campaign')->latest();
    }

    /**
     * Apply Search and Filters.
     */
    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('review_text', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', (bool) $request->status);
        }

        if ($request->filled('rating') && $request->rating !== 'all') {
            $query->where('rating', $request->rating);
        }

        if ($request->filled('campaign_id') && $request->campaign_id !== 'all') {
            $query->where('campaign_id', $request->campaign_id);
        }

        return $query;
    }

    /**
     * Reusable list response for Index & Trash.
     */
    private function listResponse(Request $request, Builder $query, string $title, bool $isTrash = false)
    {
        $query = $this->applyFilters($query, $request);
        $reviews = $query->paginate(15);
        $campaigns = Campaign::active()->select('id', 'title')->get();

        $breadcrumb = [
            ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['text' => 'Reviews', 'url' => route('admin.reviews.index')],
        ];

        if ($isTrash) {
            $breadcrumb[] = [
                'text' => 'Trash',
                'url' => route('admin.reviews.trashed'),
            ];
        }

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'html' => view('admin.reviews.partials.table', [
                    'reviews' => $reviews,
                    'isTrash' => $isTrash,
                ])->render(),
            ]);
        }

        return view('admin.reviews.index', [
            'reviews' => $reviews,
            'campaigns' => $campaigns,
            'title' => $title,
            'breadcrumb' => $breadcrumb,
            'isTrash' => $isTrash,
        ]);
    }

    public function index(Request $request)
    {
        $this->adminOnly();
        return $this->listResponse($request, $this->reviewQuery(), 'All Reviews');
    }

    public function trash(Request $request)
    {
        $this->adminOnly();
        return $this->listResponse($request, $this->reviewQuery(true), 'Trashed Reviews', true);
    }

    public function create(Request $request)
    {
        $this->adminOnly();

        if (! $request->ajax()) {
            return redirect()->route('admin.reviews.index');
        }

        $review = null;
        $isEdit = false;
        $action = route('admin.reviews.store');
        $campaigns = Campaign::active()->select('id', 'title')->get();

        return response()->json([
            'status' => true,
            'html' => view('admin.reviews.partials.form', compact('review', 'isEdit', 'action', 'campaigns'))->render(),
        ]);
    }

    public function store(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review_text' => ['nullable', 'string'],
            'social_link' => ['nullable', 'url', 'max:255'],
            'status' => ['nullable', 'boolean'],
            'customer_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        DB::transaction(function () use ($request) {
            $review = Review::create([
                'campaign_id' => $request->campaign_id,
                'customer_name' => $request->customer_name,
                'location' => $request->location,
                'rating' => $request->rating,
                'review_text' => $request->review_text,
                'social_link' => $request->social_link,
                'status' => $request->has('status') ? true : false,
            ]);

            if ($request->hasFile('customer_image')) {
                $this->uploadReviewImage(
                    $review,
                    $request->file('customer_image')
                );
            }
        });

        return response()->json([
            'status' => true,
            'message' => 'Review created successfully.',
        ]);
    }

    public function show(Request $request, Review $review)
    {
        $this->adminOnly();

        if (! $request->ajax()) {
            return redirect()->route('admin.reviews.index');
        }

        $review->load('campaign');

        return response()->json([
            'status' => true,
            'html' => view('admin.reviews.partials.show', compact('review'))->render(),
        ]);
    }

    public function edit(Request $request, Review $review)
    {
        $this->adminOnly();

        if (! $request->ajax()) {
            return redirect()->route('admin.reviews.index');
        }

        $isEdit = true;
        $action = route('admin.reviews.update', $review->id);
        $campaigns = Campaign::active()->select('id', 'title')->get();

        return response()->json([
            'status' => true,
            'html' => view('admin.reviews.partials.form', compact('review', 'isEdit', 'action', 'campaigns'))->render(),
        ]);
    }

    public function update(Request $request, Review $review)
    {
        $this->adminOnly();

        $request->validate([
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review_text' => ['nullable', 'string'],
            'social_link' => ['nullable', 'url', 'max:255'],
            'status' => ['nullable', 'boolean'],
            'customer_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        DB::transaction(function () use ($request, $review) {
            $review->update([
                'campaign_id' => $request->campaign_id,
                'customer_name' => $request->customer_name,
                'location' => $request->location,
                'rating' => $request->rating,
                'review_text' => $request->review_text,
                'social_link' => $request->social_link,
                'status' => $request->has('status'),
            ]);

            if ($request->hasFile('customer_image')) {
                $review->clearMediaCollection('review_customer_image');
                $this->uploadReviewImage(
                    $review,
                    $request->file('customer_image')
                );
            }
        });

        return response()->json([
            'status' => true,
            'message' => 'Review updated successfully.',
        ]);
    }

    private function uploadReviewImage(
        Review $review,
        mixed $file
    ): void {
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return;
        }

        $review->loadMissing('campaign');

        $landingSlug = $review->campaign
            ? (Str::slug((string) (
                $review->campaign->slug ?: $review->campaign->title
            )) ?: 'landing-' . (int) $review->campaign->getKey())
            : 'general-reviews';

        $path = LandingMediaSectionMap::forCollection(
            'review_customer_image'
        );
        $directory = sprintf(
            'landings/%s/%s',
            $landingSlug,
            $path['section']
        );

        $disk = Storage::disk('public');

        if (! $disk->exists($directory) && ! $disk->makeDirectory($directory)) {
            throw new \RuntimeException(
                'Unable to create the review media directory: ' . $directory
            );
        }

        $customerSlug = Str::slug((string) $review->customer_name)
            ?: 'customer';
        $extension = strtolower((string) (
            $file->extension()
            ?: $file->getClientOriginalExtension()
            ?: 'jpg'
        ));
        $extension = preg_replace('/[^a-z0-9]+/i', '', $extension) ?: 'jpg';

        $media = $review
            ->addMedia($file)
            ->usingName(
                ($review->campaign?->title ?: 'Review')
                . ' Customer Review '
                . $review->id
            )
            ->usingFileName(sprintf(
                '%s-customer-review-%d-%s.%s',
                $landingSlug,
                (int) $review->id,
                $customerSlug,
                $extension
            ))
            ->withCustomProperties([
                'landing_named_path' => true,
                'landing_path_slug' => $landingSlug,
                'landing_section_folder' => $path['section'],
                'landing_media_folder' => $path['media_type'],
            ])
            ->toMediaCollection('review_customer_image', 'public');

        $relativePath = $media->getPathRelativeToRoot();

        if (! Storage::disk($media->disk)->exists($relativePath)) {
            throw new \RuntimeException(
                'Review image upload failed at: ' . $relativePath
            );
        }
    }

    public function destroy(Review $review)
    {
        $this->adminOnly();
        $review->delete();

        return response()->json([
            'status' => true,
            'message' => 'Review moved to trash successfully.',
        ]);
    }

    public function restore($id)
    {
        $this->adminOnly();
        Review::onlyTrashed()->findOrFail($id)->restore();

        return response()->json([
            'status' => true,
            'message' => 'Review restored successfully.',
        ]);
    }

    public function forceDelete($id)
    {
        $this->adminOnly();
        $review = Review::onlyTrashed()->findOrFail($id);
        
        $review->clearMediaCollection('review_customer_image');
        $review->forceDelete();

        return response()->json([
            'status' => true,
            'message' => 'Review permanently deleted.',
        ]);
    }

    public function multipleAction(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'action' => ['required', 'in:delete,restore,force_delete,active,inactive'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $ids = $request->ids;
        $action = $request->action;

        if ($action === 'delete') {
            Review::whereIn('id', $ids)->delete();
            return response()->json(['status' => true, 'message' => 'Selected reviews moved to trash.']);
        }

        if ($action === 'restore') {
            Review::onlyTrashed()->whereIn('id', $ids)->restore();
            return response()->json(['status' => true, 'message' => 'Selected reviews restored.']);
        }

        if ($action === 'force_delete') {
            $reviews = Review::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($reviews as $review) {
                $review->clearMediaCollection('review_customer_image');
                $review->forceDelete();
            }
            return response()->json(['status' => true, 'message' => 'Selected reviews permanently deleted.']);
        }

        $status = $action === 'active' ? true : false;
        Review::whereIn('id', $ids)->update(['status' => $status]);

        return response()->json([
            'status' => true,
            'message' => 'Selected reviews status updated.',
        ]);
    }

    public function deleteMedia($id)
    {
        $this->adminOnly();
        
        $media = Media::findOrFail($id);
        $media->delete();

        return response()->json([
            'status' => true,
            'message' => 'Customer image deleted successfully.',
        ]);
    }
}