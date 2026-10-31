<?php

// Standalone path contract: no Laravel/Spatie vendor needed for this test.
namespace Spatie\MediaLibrary\Support\PathGenerator {
    interface PathGenerator {
        public function getPath(\Spatie\MediaLibrary\MediaCollections\Models\Media $media): string;
        public function getPathForConversions(\Spatie\MediaLibrary\MediaCollections\Models\Media $media): string;
        public function getPathForResponsiveImages(\Spatie\MediaLibrary\MediaCollections\Models\Media $media): string;
    }
}
namespace Spatie\MediaLibrary\MediaCollections\Models {
    class Media {
        public function __construct(public string $collection_name, private int $id, private array $props = []) {}
        public function getKey(): int { return $this->id; }
        public function getCustomProperty(string $name, mixed $default = null): mixed { return $this->props[$name] ?? $default; }
    }
}
namespace {
    function config(string $key, mixed $fallback = null): mixed { return $GLOBALS['test_config'][$key] ?? $fallback; }
    require __DIR__ . '/../../app/MediaLibrary/HomepageMediaPathGenerator.php';
    use App\MediaLibrary\HomepageMediaPathGenerator;
    use Spatie\MediaLibrary\MediaCollections\Models\Media;
    $p = new HomepageMediaPathGenerator();
    $assert = static function (string $expected, string $actual): void { if ($actual !== $expected) throw new \RuntimeException("Expected {$expected}; got {$actual}"); };
    $GLOBALS['test_config'] = [];
    $hero = new Media('storefront_hero_image', 112, ['homepage_named_path' => true]);
    $assert('homepage/hero-banner/media-112/', $p->getPath($hero));
    $assert('homepage/hero-banner/media-112/conversions/', $p->getPathForConversions($hero));
    $assert('homepage/hero-banner/media-112/responsive-images/', $p->getPathForResponsiveImages($hero));
    $assert('homepage/branding/logo/media-19/', $p->getPath(new Media('storefront_logo', 19, ['homepage_named_path'=>true])));
    $assert('37/', $p->getPath(new Media('storefront_hero_image', 37))); // existing media, no automatic relocation
    $assert('55/', $p->getPath(new Media('site_logo', 55, ['homepage_named_path'=>true]))); // campaign/other owner media unchanged
    $GLOBALS['test_config']['media-library.prefix'] = 'site';
    $assert('site/homepage/hero-banner/media-112/', $p->getPath($hero));
    echo "P55 Homepage path generator: 7/7 passed\n";
}
