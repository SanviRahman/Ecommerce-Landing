<?php

namespace App\Traits;

trait HasMediaTrait
{
    public function getFirstMediaUrlOrPlaceholder(
        string $collection,
        string $placeholder = 'frontend/images/placeholder.png'
    ): string {
        $url = $this->getFirstMediaUrl($collection);

        return $url ?: asset($placeholder);
    }

    public function getThumbnail(string $collection = 'product_thumbnail'): string
    {
        return $this->getFirstMediaUrlOrPlaceholder($collection);
    }

    public function getGallery(string $collection = 'product_gallery')
    {
        return $this->getMedia($collection);
    }

    public function getBanner(string $collection = 'campaign_banner'): string
    {
        return $this->getFirstMediaUrlOrPlaceholder($collection);
    }

    public function getMediaUrls(string $collection): array
    {
        return $this->getMedia($collection)
            ->map(fn ($media) => $media->getUrl())
            ->toArray();
    }
}