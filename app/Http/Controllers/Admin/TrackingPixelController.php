<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrackingPixel;
use Illuminate\Database\Eloquent\Builder;
use App\Services\MetaConversionsApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
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
            'title'         => 'Create Tracking Integration',
            'breadcrumb'    => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Tracking Pixels', 'url' => route('admin.tracking-pixels.index')],
                ['text' => 'Create Integration', 'url' => route('admin.tracking-pixels.create')],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->adminOnly();
        try {
            return $this->storeValidated($request);
        } catch (ValidationException $exception) {
            return $this->safeValidationRedirect($request, $exception);
        }
    }

    private function storeValidated(Request $request)
    {
        // New Add More form; legacy single-field submissions remain supported.
        if ($request->has('pixels')) {
            $validated = $request->validate(array_merge([
                'pixels' => ['required', 'array', 'min:1', 'max:20'],
            ], $this->pixelValidationRules('pixels.*.')));
            $rows = $validated['pixels'];
            $multipleForm = true;
        } else {
            $rows = [$request->validate($this->pixelValidationRules())];
            $multipleForm = false;
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, $multipleForm, &$created, &$updated): void {
            foreach ($rows as $index => $row) {
                $platform = $row['platform'];
                $script = trim((string) ($row['script_code'] ?? ''));
                $blocks = $script !== '' ? $this->extractScriptBlocks($script, $platform) : [];
                if ($blocks === []) {
                    $blocks = [$script]; // ID-only integrations are valid (except GTM).
                }

                $path = $multipleForm ? "pixels.{$index}.pixel_id" : 'pixel_id';
                if (count($blocks) > 1 && trim((string) ($row['pixel_id'] ?? '')) !== '') {
                    throw ValidationException::withMessages([
                        $path => 'For multiple scripts in one field, leave manual ID empty or use Add More.',
                    ]);
                }

                foreach ($blocks as $block) {
                    $pixelId = $this->extractIdentifier($block, $platform, $row['pixel_id'] ?? null);
                    $this->assertValidPixel($platform, $pixelId, $block, $path);

                    $pixel = TrackingPixel::withTrashed()
                        ->where('platform', $platform)
                        ->where('pixel_id', $pixelId)
                        ->first();
                    $attributes = $this->pixelAttributes($row, $pixelId, $block, $pixel, $path);

                    if ($pixel) {
                        if ($pixel->trashed()) {
                            $pixel->restore();
                        }
                        $pixel->update($attributes);
                        $updated++;
                    } else {
                        TrackingPixel::create(array_merge($attributes, [
                            'platform' => $platform,
                            'pixel_id' => $pixelId,
                        ]));
                        $created++;
                    }
                }
            }
        });

        return redirect()->route('admin.tracking-pixels.index')
            ->with('success', "{$created} integration created and {$updated} integration updated successfully.");
    }

    public function show(TrackingPixel $trackingPixel)
    {
        $this->adminOnly();

        return view('admin.tracking-pixels.show', [
            'trackingPixel' => $trackingPixel,
            'platforms'     => $this->platforms(),
            'title'         => 'Tracking Integration Details',
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
            'title'         => 'Edit Tracking Integration',
            'breadcrumb'    => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Tracking Pixels', 'url' => route('admin.tracking-pixels.index')],
                ['text' => 'Edit Integration', 'url' => route('admin.tracking-pixels.edit', $trackingPixel->id)],
            ],
        ]);
    }

    public function update(Request $request, TrackingPixel $trackingPixel)
    {
        $this->adminOnly();
        try {
            return $this->updateValidated($request, $trackingPixel);
        } catch (ValidationException $exception) {
            return $this->safeValidationRedirect($request, $exception);
        }
    }

    private function updateValidated(Request $request, TrackingPixel $trackingPixel)
    {
        $row = $request->validate($this->pixelValidationRules());
        $platform = $row['platform'];
        $script = trim((string) ($row['script_code'] ?? ''));
        $pixelId = $this->extractIdentifier($script, $platform, $row['pixel_id'] ?? null);
        $this->assertValidPixel($platform, $pixelId, $script, 'pixel_id');

        if (TrackingPixel::withTrashed()
            ->where('platform', $platform)->where('pixel_id', $pixelId)
            ->where('id', '!=', $trackingPixel->id)->exists()) {
            throw ValidationException::withMessages([
                'pixel_id' => 'Another tracking integration already uses this platform and ID.',
            ]);
        }

        $trackingPixel->update(array_merge(
            ['platform' => $platform, 'pixel_id' => $pixelId],
            $this->pixelAttributes($row, $pixelId, $script, $trackingPixel, 'pixel_id'),
        ));

        return redirect()->route('admin.tracking-pixels.index')
            ->with('success', 'Tracking integration updated successfully.');
    }

    public function testCapi(Request $request, TrackingPixel $trackingPixel, MetaConversionsApiService $conversions)
    {
        $this->adminOnly();
        if ($trackingPixel->platform !== 'meta' || ! $trackingPixel->capi_enabled
            || ! $trackingPixel->capi_access_token || ! $trackingPixel->capi_test_event_code) {
            return back()->with('error', 'Enable Meta CAPI and save a token plus Test Event Code first.');
        }

        $sent = $conversions->sendTestEvent($trackingPixel, $request);

        return back()->with($sent ? 'success' : 'error', $sent
            ? 'Test event accepted by Meta. Check Events Manager -> Test Events.'
            : 'Meta did not accept the test event. Check the token, code and server logs.');
    }

    /** Never flash CAPI tokens into Laravel's old_input session on validation errors. */
    private function safeValidationRedirect(Request $request, ValidationException $exception)
    {
        $input = $request->except(['capi_access_token', '_token']);
        if (isset($input['pixels']) && is_array($input['pixels'])) {
            foreach ($input['pixels'] as &$row) {
                if (is_array($row)) {
                    unset($row['capi_access_token']);
                }
            }
            unset($row);
        }

        return redirect()->back()->withErrors($exception->errors())->withInput($input);
    }

    private function pixelValidationRules(string $prefix = ''): array
    {
        return [
            $prefix . 'platform' => ['required', 'string', 'in:meta,tiktok,gtm,google_analytics'],
            $prefix . 'name' => ['nullable', 'string', 'max:255'],
            $prefix . 'pixel_id' => ['nullable', 'string', 'max:255'],
            $prefix . 'script_code' => ['nullable', 'string', 'max:20000'],
            $prefix . 'status' => ['nullable', 'boolean'],
            $prefix . 'capi_enabled' => ['nullable', 'boolean'],
            $prefix . 'capi_access_token' => ['nullable', 'string', 'max:4096'],
            $prefix . 'capi_test_event_code' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function assertValidPixel(string $platform, ?string $pixelId, string $script, string $path): void
    {
        if ($platform === 'gtm') {
            $normalizedId = strtoupper(trim((string) $pixelId));

            if (! preg_match('/\AGTM-[A-Z0-9]+\z/D', $normalizedId)) {
                throw ValidationException::withMessages([
                    $path => 'Enter a valid GTM Container ID such as GTM-TS7Q7WGG. A full script is optional.',
                ]);
            }

            // If an administrator keeps a legacy full snippet, make sure it
            // targets the same container as the separately saved ID.
            preg_match_all('/\bGTM-[A-Z0-9]+\b/i', $script, $matches);
            $scriptIds = array_values(array_unique(array_map('strtoupper', $matches[0] ?? [])));

            if (count($scriptIds) > 1 || (count($scriptIds) === 1 && $scriptIds[0] !== $normalizedId)) {
                throw ValidationException::withMessages([
                    $path => 'The GTM Container ID must match the ID inside the optional tracking script.',
                ]);
            }

            return;
        }

        if (! $pixelId) {
            throw ValidationException::withMessages([
                $path => 'Enter a manual pixel ID or provide a script containing a valid ID.',
            ]);
        }

        $pattern = $platform === 'meta'
            ? '/\A[0-9]{5,30}\z/D'
            : '/\A[A-Za-z0-9:_-]{3,255}\z/D';
        if (! preg_match($pattern, $pixelId)) {
            throw ValidationException::withMessages([
                $path => $platform === 'meta'
                    ? 'Meta Pixel ID must contain 5-30 digits.'
                    : 'Invalid tracking identifier format.',
            ]);
        }
    }

    private function pixelAttributes(array $row, string $pixelId, string $script, ?TrackingPixel $existing, string $path): array
    {
        $platform = $row['platform'];
        $enabled = $platform === 'meta' && (bool) ($row['capi_enabled'] ?? false);
        $token = trim((string) ($row['capi_access_token'] ?? ''));
        $previousToken = $existing && $existing->platform === 'meta'
            ? $existing->capi_access_token : null;

        if ($enabled && $token === '' && ! $previousToken) {
            throw ValidationException::withMessages([
                str_replace('pixel_id', 'capi_access_token', $path)
                    => 'A CAPI access token is required when Meta CAPI is enabled.',
            ]);
        }
        if ($platform !== 'meta' && (bool) ($row['capi_enabled'] ?? false)) {
            throw ValidationException::withMessages([
                str_replace('pixel_id', 'capi_enabled', $path)
                    => 'Conversions API is available for Meta Pixels only.',
            ]);
        }

        $attributes = [
            'name' => trim((string) ($row['name'] ?? '')) ?: $this->defaultPixelName($platform, $pixelId),
            'script_code' => $script !== '' ? $script : null,
            'status' => array_key_exists('status', $row) ? (bool) $row['status'] : true,
            'capi_enabled' => $enabled,
            'capi_test_event_code' => $platform === 'meta'
                ? (trim((string) ($row['capi_test_event_code'] ?? '')) ?: null) : null,
        ];

        // Blank on Edit means keep the old encrypted token, not erase it.
        if ($platform !== 'meta') {
            $attributes['capi_access_token'] = null;
        } elseif ($token !== '') {
            $attributes['capi_access_token'] = $token;
        }

        return $attributes;
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
        if ($manualPixelId !== null && trim($manualPixelId) !== '') {
            $manualPixelId = trim($manualPixelId);

            return $platform === 'gtm' ? strtoupper($manualPixelId) : $manualPixelId;
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

        return null;
    }

    private function defaultPixelName(string $platform, string $pixelId): string
    {
        return ($this->platforms()[$platform] ?? ucfirst($platform)) . ' - ' . $pixelId;
    }
}
