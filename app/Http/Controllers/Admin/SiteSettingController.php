<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\SocialMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SiteSettingController extends Controller
{
    private function adminOnly(): void
    {
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }
    }

    public function index()
    {
        $this->adminOnly();

        $siteSetting = SiteSetting::query()->latest()->first();

        // Legacy records remain a read-only fallback until Site Settings takes ownership.
        $legacySocial = SocialMedia::query()->active()->whereIn('platform_name',
            ['youtube', 'facebook', 'instagram', 'linkedin'])
            ->orderByDesc('id')->get()->unique(fn ($item) => strtolower($item->platform_name))
            ->mapWithKeys(fn ($item) => [strtolower($item->platform_name) => $item->link]);

        return view('admin.site-settings.index', [
            'legacySocial' => $legacySocial,
            'siteSetting' => $siteSetting,
            'isEdit' => (bool) $siteSetting,
            'action' => $siteSetting
                ? route('admin.site-settings.update', $siteSetting->id)
                : route('admin.site-settings.store'),
            'title' => 'Site Settings',
            'breadcrumb' => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Site Settings', 'url' => route('admin.site-settings.index')],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->adminOnly();

        if (SiteSetting::query()->exists()) {
            return redirect()
                ->route('admin.site-settings.index')
                ->with('error', 'Site settings already exists. Please update existing settings.');
        }

        $request->validate($this->validationRules());

        return DB::transaction(function () use ($request) {
            $siteSetting = SiteSetting::create($this->settingsPayload($request));

            $this->uploadMedia($siteSetting, $request);

            return redirect()
                ->route('admin.site-settings.index')
                ->with('success', 'Site settings created successfully.');
        });
    }

    public function update(Request $request, SiteSetting $siteSetting)
    {
        $this->adminOnly();

        $request->validate($this->validationRules());

        return DB::transaction(function () use ($request, $siteSetting) {
            // Existing site_settings columns are the single storefront contact source.
            // Campaign-specific footer controls stay owned by the Campaign editor.
            $siteSetting->update($this->settingsPayload($request));

            $this->uploadMedia($siteSetting, $request);

            return redirect()
                ->route('admin.site-settings.index')
                ->with('success', 'Site settings updated successfully.');
        });
    }

    public function deleteMedia($id)
    {
        $this->adminOnly();

        $media = Media::findOrFail($id);

        if (! in_array($media->collection_name, [
            'site_logo',
            'site_white_logo',
            'site_favicon',
            'storefront_logo',
            'storefront_white_logo',
            'storefront_favicon',
        ])) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid media collection.',
            ], 422);
        }

        // Collection alone is not ownership: enforce the actual SiteSetting morph.
        $setting = SiteSetting::query()->latest()->firstOrFail();
        abort_unless($media->model_type === $setting->getMorphClass()
            && (int) $media->model_id === (int) $setting->id, 403);
        $media->delete();

        return response()->json([
            'status' => true,
            'message' => 'Media deleted successfully.',
        ]);
    }

    private function settingsPayload(Request $request): array
    {
        return $request->only([
            'website_name', 'phone', 'hotline', 'whatsapp_number', 'messenger_link',
            'email', 'address', 'business_short_description', 'working_hours',
            'footer_youtube_url', 'footer_facebook_url',
            'footer_instagram_url', 'footer_linkedin_url',
        ]) + [
            'status' => $request->boolean('status'),
            'footer_social_managed' => true,
        ];
    }

    private function validationRules(): array
    {
        return [
            'website_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:25', 'regex:/\A\+?[0-9() .-]+\z/D'],
            'hotline' => ['nullable', 'string', 'max:25', 'regex:/\A\+?[0-9() .-]+\z/D'],
            'whatsapp_number' => ['nullable', 'string', 'max:25', 'regex:/\A\+?[0-9() .-]+\z/D'],
            'messenger_link' => ['nullable', 'url:https', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'address' => ['nullable', 'string', 'max:1500'],
            'business_short_description' => ['nullable', 'string', 'max:500'],
            'working_hours' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'boolean'],
            'footer_youtube_url' => ['nullable', 'url:https', 'max:255'],
            'footer_facebook_url' => ['nullable', 'url:https', 'max:255'],
            'footer_instagram_url' => ['nullable', 'url:https', 'max:255'],
            'footer_linkedin_url' => ['nullable', 'url:https', 'max:255'],

            'site_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'site_white_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'site_favicon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,ico,svg', 'max:2048'],
            // Upload only raster storefront files; prevent active SVG content in public icons.
            'storefront_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'storefront_white_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'storefront_favicon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    private function uploadMedia(SiteSetting $siteSetting, Request $request): void
    {
        $mediaFields = [
            'site_logo',
            'site_white_logo',
            'site_favicon',
            'storefront_logo',
            'storefront_white_logo',
            'storefront_favicon',
        ];

        foreach ($mediaFields as $field) {
            if ($request->hasFile($field)) {
                $siteSetting
                    ->clearMediaCollection($field);

                $upload = $siteSetting->addMediaFromRequest($field);
                if (str_starts_with($field, 'storefront_')) {
                    $upload->withCustomProperties([
                        'homepage_named_path' => true,
                        'homepage_section_folder' => \App\MediaLibrary\HomepageMediaPathGenerator::FOLDERS[$field],
                    ]);
                }
                $upload->toMediaCollection($field, 'public');
            }
        }
    }
}