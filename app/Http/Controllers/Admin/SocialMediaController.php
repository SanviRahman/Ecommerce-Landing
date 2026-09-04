<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialMedia;
use App\Rules\SocialLink;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SocialMediaController extends Controller
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
     * Base query for social media links.
     */
    private function socialMediaQuery(bool $trash = false): Builder
    {
        $query = $trash ? SocialMedia::onlyTrashed() : SocialMedia::query();
        return $query->latest();
    }

    /**
     * Apply Search and Filters.
     */
    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('platform_name', 'like', "%{$search}%")
                  ->orWhere('link', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', (bool) $request->status);
        }

        return $query;
    }

    /**
     * Reusable list response for Index & Trash.
     */
    private function listResponse(Request $request, Builder $query, string $title, bool $isTrash = false)
    {
        $query = $this->applyFilters($query, $request);
        $socialMedias = $query->paginate(15);

        $breadcrumb = [
            ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['text' => 'Social Media', 'url' => route('admin.social-media.index')],
        ];

        if ($isTrash) {
            $breadcrumb[] = [
                'text' => 'Trash',
                'url' => route('admin.social-media.trashed'),
            ];
        }

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'html' => view('admin.social-media.partials.table', [
                    'socialMedias' => $socialMedias,
                    'isTrash' => $isTrash,
                ])->render(),
            ]);
        }

        return view('admin.social-media.index', [
            'socialMedias' => $socialMedias,
            'title' => $title,
            'breadcrumb' => $breadcrumb,
            'isTrash' => $isTrash,
        ]);
    }

    public function index(Request $request)
    {
        $this->adminOnly();
        return $this->listResponse($request, $this->socialMediaQuery(), 'Social Media Links');
    }

    public function trash(Request $request)
    {
        $this->adminOnly();
        return $this->listResponse($request, $this->socialMediaQuery(true), 'Trashed Social Media', true);
    }

    public function create(Request $request)
    {
        $this->adminOnly();

        if (! $request->ajax()) {
            return redirect()->route('admin.social-media.index');
        }

        $socialMedia = null;
        $isEdit = false;
        $action = route('admin.social-media.store');

        return response()->json([
            'status' => true,
            'html' => view('admin.social-media.partials.form', compact('socialMedia', 'isEdit', 'action'))->render(),
        ]);
    }

    public function store(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'platform_name' => ['required', 'string', 'max:255'],
            'link' => ['required', 'string', 'max:255', new SocialLink()],
            'icon_class' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'boolean'],
        ]);

        SocialMedia::create([
            'platform_name' => $request->platform_name,
            'link' => $request->link,
            'icon_class' => $request->icon_class,
            'status' => $request->has('status') ? true : false,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Social media link added successfully.',
        ]);
    }

    public function show(Request $request, SocialMedia $socialMedia)
    {
        $this->adminOnly();

        if (! $request->ajax()) {
            return redirect()->route('admin.social-media.index');
        }

        return response()->json([
            'status' => true,
            'html' => view('admin.social-media.partials.show', compact('socialMedia'))->render(),
        ]);
    }

    public function edit(Request $request, SocialMedia $socialMedia)
    {
        $this->adminOnly();

        if (! $request->ajax()) {
            return redirect()->route('admin.social-media.index');
        }

        $isEdit = true;
        $action = route('admin.social-media.update', $socialMedia->id);

        return response()->json([
            'status' => true,
            'html' => view('admin.social-media.partials.form', compact('socialMedia', 'isEdit', 'action'))->render(),
        ]);
    }

    public function update(Request $request, SocialMedia $socialMedia)
    {
        $this->adminOnly();

        $request->validate([
            'platform_name' => ['required', 'string', 'max:255'],
            'link' => ['required', 'string', 'max:255', new SocialLink()],
            'icon_class' => ['nullable', 'string', 'max:255'],
        ]);

        $socialMedia->update([
            'platform_name' => $request->platform_name,
            'link' => $request->link,
            'icon_class' => $request->icon_class,
            'status' => $request->has('status'),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Social media link updated successfully.',
        ]);
    }

    public function updateStatus(Request $request, SocialMedia $socialMedia)
    {
        $this->adminOnly();

        $request->validate([
            'status' => ['required', 'boolean'],
        ]);

        $socialMedia->update(['status' => $request->status]);

        return response()->json([
            'status' => true,
            'message' => 'Status updated successfully.',
        ]);
    }

    public function destroy(SocialMedia $socialMedia)
    {
        $this->adminOnly();
        $socialMedia->delete();

        return response()->json([
            'status' => true,
            'message' => 'Social media link moved to trash.',
        ]);
    }

    public function restore($id)
    {
        $this->adminOnly();
        SocialMedia::onlyTrashed()->findOrFail($id)->restore();

        return response()->json([
            'status' => true,
            'message' => 'Social media link restored successfully.',
        ]);
    }

    public function forceDelete($id)
    {
        $this->adminOnly();
        SocialMedia::onlyTrashed()->findOrFail($id)->forceDelete();

        return response()->json([
            'status' => true,
            'message' => 'Social media link permanently deleted.',
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
            SocialMedia::whereIn('id', $ids)->delete();
            return response()->json(['status' => true, 'message' => 'Selected links moved to trash.']);
        }

        if ($action === 'restore') {
            SocialMedia::onlyTrashed()->whereIn('id', $ids)->restore();
            return response()->json(['status' => true, 'message' => 'Selected links restored.']);
        }

        if ($action === 'force_delete') {
            SocialMedia::onlyTrashed()->whereIn('id', $ids)->forceDelete();
            return response()->json(['status' => true, 'message' => 'Selected links permanently deleted.']);
        }

        $status = $action === 'active' ? true : false;
        SocialMedia::whereIn('id', $ids)->update(['status' => $status]);

        return response()->json([
            'status' => true,
            'message' => 'Selected items status updated.',
        ]);
    }
}