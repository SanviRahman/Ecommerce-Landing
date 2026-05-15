<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrackingPixel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TrackingPixelController extends Controller
{
    private function adminOnly(): void
    {
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }
    }

    private function platforms(): array
    {
        return [
            'meta'             => 'Meta / Facebook Pixel',
            'tiktok'           => 'TikTok Pixel',
            'gtm'              => 'Google Tag Manager',
            'google_analytics' => 'Google Analytics',
        ];
    }

    private function pixelQuery(bool $trash = false): Builder
    {
        return $trash
            ? TrackingPixel::onlyTrashed()->latest()
            : TrackingPixel::query()->latest();
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('pixel_id', 'like', "%{$search}%")
                    ->orWhere('platform', 'like', "%{$search}%")
                    ->orWhere('script_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('platform') && $request->platform !== 'all') {
            $query->where('platform', $request->platform);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', (bool) $request->status);
        }

        return $query;
    }

    private function listResponse(Request $request, Builder $query, string $title, bool $isTrash = false)
    {
        $query = $this->applyFilters($query, $request);

        $trackingPixels = $query->paginate(10);

        $breadcrumb = [
            ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['text' => 'Tracking Pixels', 'url' => route('admin.tracking-pixels.index')],
        ];

        if ($isTrash) {
            $breadcrumb[] = [
                'text' => 'Trash',
                'url'  => route('admin.tracking-pixels.trashed'),
            ];
        }

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'html'   => view('admin.tracking-pixels.partials.table', [
                    'trackingPixels' => $trackingPixels,
                    'platforms'      => $this->platforms(),
                    'isTrash'        => $isTrash,
                ])->render(),
            ]);
        }

        return view('admin.tracking-pixels.index', [
            'trackingPixels' => $trackingPixels,
            'platforms'      => $this->platforms(),
            'title'          => $title,
            'breadcrumb'     => $breadcrumb,
            'isTrash'        => $isTrash,
        ]);
    }

    public function index(Request $request)
    {
        $this->adminOnly();

        return $this->listResponse(
            $request,
            $this->pixelQuery(),
            'Tracking Pixels'
        );
    }

    public function create()
    {
        $this->adminOnly();

        return view('admin.tracking-pixels.create', [
            'trackingPixel' => null,
            'platforms'     => $this->platforms(),
            'isEdit'        => false,
            'action'        => route('admin.tracking-pixels.store'),
            'title'         => 'Create Tracking Pixel',
            'breadcrumb'    => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Tracking Pixels', 'url' => route('admin.tracking-pixels.index')],
                ['text' => 'Create Pixel', 'url' => route('admin.tracking-pixels.create')],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'platform'    => ['required', 'string', 'in:meta,tiktok,gtm,google_analytics'],
            'name'        => ['nullable', 'string', 'max:255'],
            'pixel_id'    => ['nullable', 'string', 'max:255'],
            'script_code' => ['required', 'string'],
            'status'      => ['nullable', 'boolean'],
        ]);

        $scriptBlocks = $this->extractScriptBlocks($request->script_code, $request->platform);

        if (empty($scriptBlocks)) {
            throw ValidationException::withMessages([
                'script_code' => 'Please paste at least one valid tracking script.',
            ]);
        }

        $created = 0;
        $updated = 0;

        foreach ($scriptBlocks as $scriptBlock) {
            $pixelId = $this->extractIdentifier(
                $scriptBlock,
                $request->platform,
                $request->pixel_id
            );

            if (! $pixelId) {
                continue;
            }

            $trackingPixel = TrackingPixel::withTrashed()
                ->where('platform', $request->platform)
                ->where('pixel_id', $pixelId)
                ->first();

            if ($trackingPixel) {
                if ($trackingPixel->trashed()) {
                    $trackingPixel->restore();
                }

                $trackingPixel->update([
                    'name'        => $request->name ?: $this->defaultPixelName($request->platform, $pixelId),
                    'script_code' => $scriptBlock,
                    'status'      => $request->has('status') ? $request->boolean('status') : true,
                ]);

                $updated++;
            } else {
                TrackingPixel::create([
                    'platform'    => $request->platform,
                    'name'        => $request->name ?: $this->defaultPixelName($request->platform, $pixelId),
                    'pixel_id'    => $pixelId,
                    'script_code' => $scriptBlock,
                    'status'      => $request->has('status') ? $request->boolean('status') : true,
                ]);

                $created++;
            }
        }

        if ($created === 0 && $updated === 0) {
            throw ValidationException::withMessages([
                'script_code' => 'No valid pixel ID was found inside the script.',
            ]);
        }

        return redirect()
            ->route('admin.tracking-pixels.index')
            ->with('success', "{$created} pixel created and {$updated} pixel updated successfully.");
    }

    public function show(TrackingPixel $trackingPixel)
    {
        $this->adminOnly();

        return view('admin.tracking-pixels.show', [
            'trackingPixel' => $trackingPixel,
            'platforms'     => $this->platforms(),
            'title'         => 'Tracking Pixel Details',
            'breadcrumb'    => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Tracking Pixels', 'url' => route('admin.tracking-pixels.index')],
                ['text' => 'Details', 'url' => route('admin.tracking-pixels.show', $trackingPixel->id)],
            ],
        ]);
    }

    public function edit(TrackingPixel $trackingPixel)
    {
        $this->adminOnly();

        return view('admin.tracking-pixels.edit', [
            'trackingPixel' => $trackingPixel,
            'platforms'     => $this->platforms(),
            'isEdit'        => true,
            'action'        => route('admin.tracking-pixels.update', $trackingPixel->id),
            'title'         => 'Edit Tracking Pixel',
            'breadcrumb'    => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Tracking Pixels', 'url' => route('admin.tracking-pixels.index')],
                ['text' => 'Edit Pixel', 'url' => route('admin.tracking-pixels.edit', $trackingPixel->id)],
            ],
        ]);
    }

    public function update(Request $request, TrackingPixel $trackingPixel)
    {
        $this->adminOnly();

        $request->validate([
            'platform'    => ['required', 'string', 'in:meta,tiktok,gtm,google_analytics'],
            'name'        => ['nullable', 'string', 'max:255'],
            'pixel_id'    => ['nullable', 'string', 'max:255'],
            'script_code' => ['required', 'string'],
            'status'      => ['nullable', 'boolean'],
        ]);

        $pixelId = $this->extractIdentifier(
            $request->script_code,
            $request->platform,
            $request->pixel_id
        );

        if (! $pixelId) {
            throw ValidationException::withMessages([
                'script_code' => 'No valid pixel ID was found inside the script.',
            ]);
        }

        $exists = TrackingPixel::where('platform', $request->platform)
            ->where('pixel_id', $pixelId)
            ->where('id', '!=', $trackingPixel->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'script_code' => 'This tracking pixel already exists.',
            ]);
        }

        $trackingPixel->update([
            'platform'    => $request->platform,
            'name'        => $request->name ?: $this->defaultPixelName($request->platform, $pixelId),
            'pixel_id'    => $pixelId,
            'script_code' => $request->script_code,
            'status'      => $request->has('status') ? $request->boolean('status') : true,
        ]);

        return redirect()
            ->route('admin.tracking-pixels.index')
            ->with('success', 'Tracking pixel updated successfully.');
    }

    public function updateStatus(Request $request, TrackingPixel $trackingPixel)
    {
        $this->adminOnly();

        $request->validate([
            'status' => ['required', 'boolean'],
        ]);

        $trackingPixel->update([
            'status' => $request->boolean('status'),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Tracking pixel status updated successfully.',
        ]);
    }

    public function destroy(TrackingPixel $trackingPixel)
    {
        $this->adminOnly();

        $trackingPixel->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Tracking pixel moved to trash successfully.',
        ]);
    }

    public function trash(Request $request)
    {
        $this->adminOnly();

        return $this->listResponse(
            $request,
            $this->pixelQuery(true),
            'Tracking Pixel Trash Bin',
            true
        );
    }

    public function restore($id)
    {
        $this->adminOnly();

        TrackingPixel::onlyTrashed()->findOrFail($id)->restore();

        return response()->json([
            'status'  => true,
            'message' => 'Tracking pixel restored successfully.',
        ]);
    }

    public function forceDelete($id)
    {
        $this->adminOnly();

        TrackingPixel::onlyTrashed()->findOrFail($id)->forceDelete();

        return response()->json([
            'status'  => true,
            'message' => 'Tracking pixel permanently deleted successfully.',
        ]);
    }

    public function multipleAction(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'action' => ['required', 'in:delete,restore,force_delete,active,inactive'],
            'ids'    => ['required', 'array'],
            'ids.*'  => ['integer'],
        ]);

        if ($request->action === 'delete') {
            TrackingPixel::whereIn('id', $request->ids)->delete();

            return response()->json([
                'status'  => true,
                'message' => 'Selected tracking pixels moved to trash.',
            ]);
        }

        if ($request->action === 'restore') {
            TrackingPixel::onlyTrashed()->whereIn('id', $request->ids)->restore();

            return response()->json([
                'status'  => true,
                'message' => 'Selected tracking pixels restored.',
            ]);
        }

        if ($request->action === 'force_delete') {
            TrackingPixel::onlyTrashed()->whereIn('id', $request->ids)->forceDelete();

            return response()->json([
                'status'  => true,
                'message' => 'Selected tracking pixels permanently deleted.',
            ]);
        }

        if ($request->action === 'active') {
            TrackingPixel::whereIn('id', $request->ids)->update(['status' => true]);

            return response()->json([
                'status'  => true,
                'message' => 'Selected tracking pixels activated.',
            ]);
        }

        if ($request->action === 'inactive') {
            TrackingPixel::whereIn('id', $request->ids)->update(['status' => false]);

            return response()->json([
                'status'  => true,
                'message' => 'Selected tracking pixels deactivated.',
            ]);
        }

        return response()->json([
            'status'  => false,
            'message' => 'Invalid bulk action selected.',
        ], 422);
    }

    private function extractScriptBlocks(string $input, string $platform): array
    {
        $input = trim($input);

        if ($input === '') {
            return [];
        }

        if ($platform === 'meta') {
            preg_match_all(
                '/<!--\s*Meta Pixel Code\s*-->.*?<!--\s*End Meta Pixel Code\s*-->/is',
                $input,
                $matches
            );

            if (! empty($matches[0])) {
                return collect($matches[0])
                    ->map(fn($script) => trim($script))
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();
            }
        }

        /*
         * If admin pasted one script without comment wrapper.
         */
        if (str_contains(strtolower($input), '<script')) {
            return [$input];
        }

        return [];
    }

    private function extractIdentifier(string $script, string $platform, ?string $manualPixelId = null): ?string
    {
        if ($manualPixelId) {
            return trim($manualPixelId);
        }

        if ($platform === 'meta') {
            preg_match(
                "/fbq\(\s*['\"]init['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i",
                $script,
                $matches
            );

            if (! empty($matches[1])) {
                return trim($matches[1]);
            }

            preg_match(
                "/facebook\.com\/tr\?id=([A-Za-z0-9_\-:.]+)/i",
                $script,
                $urlMatches
            );

            if (! empty($urlMatches[1])) {
                return trim($urlMatches[1]);
            }
        }

        if ($platform === 'tiktok') {
            preg_match(
                "/ttq\.load\(\s*['\"]([^'\"]+)['\"]\s*\)/i",
                $script,
                $matches
            );

            if (! empty($matches[1])) {
                return trim($matches[1]);
            }

            preg_match(
                "/sdkid=([A-Za-z0-9]+)/i",
                $script,
                $urlMatches
            );

            if (! empty($urlMatches[1])) {
                return trim($urlMatches[1]);
            }
        }

        if ($platform === 'gtm') {
            preg_match(
                "/GTM-[A-Z0-9]+/i",
                $script,
                $matches
            );

            if (! empty($matches[0])) {
                return strtoupper(trim($matches[0]));
            }
        }

        if ($platform === 'google_analytics') {
            preg_match(
                "/G-[A-Z0-9]+|UA-[0-9\-]+/i",
                $script,
                $matches
            );

            if (! empty($matches[0])) {
                return strtoupper(trim($matches[0]));
            }
        }

        return 'script-' . Str::random(12);
    }

    private function defaultPixelName(string $platform, string $pixelId): string
    {
        return ($this->platforms()[$platform] ?? ucfirst($platform)) . ' - ' . $pixelId;
    }
}
