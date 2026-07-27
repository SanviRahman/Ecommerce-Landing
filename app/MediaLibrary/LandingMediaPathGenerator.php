<?php

namespace App\MediaLibrary;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class LandingMediaPathGenerator implements PathGenerator
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
        if (! $this->usesLandingNamedPath($media)) {
            return $this->legacyBasePath($media);
        }

        $slug = $this->normalizePathSegment((string) $media->getCustomProperty(
            'landing_path_slug',
            ''
        ));

        if ($slug === '') {
            return $this->legacyBasePath($media);
        }

        $section = $this->normalizePathSegment((string) $media->getCustomProperty(
            'landing_section_folder',
            ''
        ));

        if ($section !== '') {
            return $this->withConfiguredPrefix(
                "landings/{$slug}/{$section}"
            );
        }

        $legacyFolder = $this->normalizePathSegment((string) $media->getCustomProperty(
            'landing_media_folder',
            (string) $media->collection_name
        ));

        if ($legacyFolder === '') {
            $legacyFolder = 'media';
        }

        return $this->withConfiguredPrefix(
            "landings/{$slug}/{$legacyFolder}"
        );
    }

    private function usesLandingNamedPath(Media $media): bool
    {
        return filter_var(
            $media->getCustomProperty('landing_named_path', false),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    private function normalizePathSegment(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_-]+/i', '-', $value) ?: '';

        return trim($value, '-_');
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
