<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/** Code-owned P05 section registry: never accept Blade paths, CSS or executable HTML from users. */
final class HomeSectionSchema
{
    public const TYPES = [
        'hero_slider' => 'Hero / Banner',
        'category_grid' => 'Shop by Category',
        'category_products' => 'Category-wise Products',
        'product_list' => 'Product Collection',
        'image_text' => 'Image & Text',
        'reviews' => 'Customer Reviews',
    ];

    public const STYLE_KEYS = [
        'background', 'heading', 'text', 'accent', 'button_background', 'button_text',
        'button_hover_background', 'button_hover_text', 'button_border',
        'secondary_button_background', 'secondary_button_text',
        'secondary_button_hover_background', 'secondary_button_hover_text',
        'secondary_button_border', 'card_background', 'card_text', 'card_border', 'focus_ring',
    ];

    public const DEFAULT_THEME = [
        'background' => '#FFFFFF', 'heading' => '#17251C', 'text' => '#35443B',
        'accent' => '#28794A', 'button_background' => '#21653F',
        'button_text' => '#FFFFFF', 'button_hover_background' => '#174D30',
        'button_hover_text' => '#FFFFFF', 'button_border' => '#21653F',
        'secondary_button_background' => '#FFFFFF', 'secondary_button_text' => '#174D30',
        'secondary_button_hover_background' => '#ECF5EF',
        'secondary_button_hover_text' => '#174D30', 'secondary_button_border' => '#21653F',
        'card_background' => '#FFFFFF', 'card_text' => '#17251C',
        'card_border' => '#DAE6DC', 'focus_ring' => '#174D30',
    ];

    public static function normalizeColors(array $values): array
    {
        foreach (array_keys($values) as $key) {
            if (! in_array($key, self::STYLE_KEYS, true)) {
                throw new InvalidArgumentException('Unknown design token: ' . $key);
            }
        }

        $result = [];
        foreach ($values as $key => $value) {
            if ($value === null || $value === '') {
                $result[$key] = null; // Explicit global inheritance.
                continue;
            }
            if (! is_string($value) || preg_match('/\A#[0-9a-fA-F]{6}\z/D', $value) !== 1) {
                throw new InvalidArgumentException('Invalid hexadecimal color: ' . $key);
            }
            $result[$key] = strtoupper($value);
        }

        return $result;
    }

    public static function normalizeUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7F\\\\]/', $url)) {
            throw new InvalidArgumentException('Invalid link URL.');
        }
        if (preg_match('~\A/(?!/)[a-zA-Z0-9/_?=&%#.+-]*\z~D', $url) === 1) {
            $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));
            $segments = explode('/', trim($path, '/'));
            $private = ['admin', 'api', 'command', 'webhooks', 'login', 'logout', 'password', 'vendor'];
            if (array_intersect($segments, ['.', '..']) !== []
                || str_contains($path, '//')
                || in_array(strtolower((string) ($segments[0] ?? '')), $private, true)) {
                throw new InvalidArgumentException('Protected or ambiguous internal path is not publishable.');
            }
            return $url;
        }
        if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
            $parts = parse_url($url);
            if (is_array($parts) && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
                && ! empty($parts['host']) && ! isset($parts['user']) && ! isset($parts['pass'])) {
                return $url;
            }
        }
        throw new InvalidArgumentException('Only safe internal paths or HTTPS links are supported.');
    }

    public static function contrast(string $colorA, string $colorB): float
    {
        $lum = static function (string $hex): float {
            $rgb = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
            $channels = array_map(static function (int $v): float {
                $c = $v / 255;
                return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            }, $rgb);
            return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        };
        $a = $lum($colorA);
        $b = $lum($colorB);
        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    public static function validateThemeContrast(array $theme): void
    {
        $resolved = array_merge(self::DEFAULT_THEME, array_filter($theme, static fn ($v) => $v !== null));
        foreach ([
            ['heading', 'background'], ['text', 'background'],
            ['button_text', 'button_background'], ['button_hover_text', 'button_hover_background'],
            ['card_text', 'card_background'], ['secondary_button_text', 'secondary_button_background'],
        ] as [$foreground, $background]) {
            if (self::contrast($resolved[$foreground], $resolved[$background]) < 4.5) {
                throw new InvalidArgumentException('Unreadable color contrast: ' . $foreground . ' / ' . $background);
            }
        }
    }
}
