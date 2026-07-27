<?php

namespace App\MediaLibrary;

use Illuminate\Support\Str;

final class LandingMediaSectionMap
{
    private const PATHS = [
        'banner_image' => [
            'section' => 'hero-section',
            'media_type' => 'banner-image',
        ],
        'campaign_video' => [
            'section' => 'hero-section',
            'media_type' => 'campaign-video',
        ],
        'hero_slider_images' => [
            'section' => 'hero-section',
            'media_type' => 'hero-slider-image',
        ],
        'image_one' => [
            'section' => 'comparison-section',
            'media_type' => 'left-image',
        ],
        'image_two' => [
            'section' => 'comparison-section',
            'media_type' => 'right-image',
        ],
        'image_three' => [
            'section' => 'service-section',
            'media_type' => 'section-image',
        ],
        'campaign_product_gallery' => [
            'section' => 'product-gallery-section',
            'media_type' => 'product-gallery-image',
        ],
        'review_image' => [
            'section' => 'review-section',
            'media_type' => 'review-image',
        ],
        'review_customer_image' => [
            'section' => 'review-section',
            'media_type' => 'customer-review-image',
        ],
    ];

    public static function forCollection(string $collection): array
    {
        if (isset(self::PATHS[$collection])) {
            return self::PATHS[$collection];
        }

        $mediaType = Str::slug($collection) ?: 'media';

        return [
            'section' => 'other-section',
            'media_type' => $mediaType,
        ];
    }

    public static function collections(): array
    {
        return array_keys(self::PATHS);
    }
}
