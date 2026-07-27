<?php

namespace App\MediaLibrary;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class ProductMediaPathGenerator implements PathGenerator
{
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
        if (! $this->usesProductNamedPath($media)) {
            return $this->legacyBasePath($media);
        }

        $slug = $this->normalizePathSegment((string) $media->getCustomProperty(
            'product_path_slug',
            ''
        ));

        if ($slug === '') {
            return $this->legacyBasePath($media);
        }

        $folder = match ((string) $media->collection_name) {
            'product_thumbnail' => 'thumbnail',
            'product_gallery'   => 'gallery',
            default             => $this->normalizePathSegment(
                (string) $media->getCustomProperty(
                    'product_media_folder',
                    'media'
                )
            ) ?: 'media',
        };

        return $this->withConfiguredPrefix(
            "products/{$slug}/{$folder}"
        );
    }

    private function normalizePathSegment(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_-]+/i', '-', $value) ?: '';

        return trim($value, '-_');
    }

    private function usesProductNamedPath(Media $media): bool
    {
        return filter_var(
            $media->getCustomProperty('product_named_path', false),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    private function legacyBasePath(Media $media): string
    {
        return $this->withConfiguredPrefix((string) $media->getKey());
    }

    private function withConfiguredPrefix(string $path): string
    {
        $prefix = trim((string) config('media-library.prefix', ''), '/');
        $path = trim($path, '/');

        return $prefix !== ''
            ? $prefix . '/' . $path
            : $path;
    }
}
