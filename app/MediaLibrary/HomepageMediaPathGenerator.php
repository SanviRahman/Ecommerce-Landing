<?php

declare(strict_types=1);

namespace App\MediaLibrary;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Dedicated Homepage uploads live under human-readable sections.
 * Old media retains numeric paths unless explicitly verified+organized.
 * Shared/campaign/product media never changes ownership or path.
 */
final class HomepageMediaPathGenerator implements PathGenerator
{
    public const FOLDERS = [
        'storefront_hero_image' => 'hero-banner',
        'storefront_logo' => 'branding/logo',
        'storefront_white_logo' => 'branding/white-logo',
        'storefront_favicon' => 'branding/favicon',
    ];

    public function getPath(Media $media): string
    {
        return $this->basePath($media) . '/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->basePath($media) . '/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->basePath($media) . '/responsive-images/';
    }

    private function basePath(Media $media): string
    {
        $prefix = trim((string) config('media-library.prefix', ''), '/');
        $collection = (string) $media->collection_name;
        $named = filter_var($media->getCustomProperty('homepage_named_path', false), FILTER_VALIDATE_BOOLEAN);
        $id = (int) $media->getKey();

        $path = $named && isset(self::FOLDERS[$collection])
            ? 'homepage/' . self::FOLDERS[$collection] . '/media-' . $id
            : (string) $id;

        return $prefix === '' ? $path : $prefix . '/' . $path;
    }
}
