<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
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

        return view('admin.site-settings.index', [
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
            $siteSetting = SiteSetting::create([
                'website_name' => $request->website_name,
            ]);

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
            // Site Settings only owns website identity/branding.
            // Campaign form continues to manage footer/contact information.
            $siteSetting->update([
                'website_name' => $request->website_name,
            ]);

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
        ])) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid media collection.',
            ], 422);
        }

        $media->delete();

        return response()->json([
            'status' => true,
            'message' => 'Media deleted successfully.',
        ]);
    }

    private function validationRules(): array
    {
        return [
            'website_name' => ['required', 'string', 'max:255'],

            'site_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'site_white_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'site_favicon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,ico,svg', 'max:2048'],
        ];
    }

    private function uploadMedia(SiteSetting $siteSetting, Request $request): void
    {
        $mediaFields = [
            'site_logo',
            'site_white_logo',
            'site_favicon',
        ];

        foreach ($mediaFields as $field) {
            if ($request->hasFile($field)) {
                $siteSetting
                    ->clearMediaCollection($field);

                $siteSetting
                    ->addMediaFromRequest($field)
                    ->toMediaCollection($field);
            }
        }
    }
}