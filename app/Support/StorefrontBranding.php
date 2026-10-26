<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SiteSetting;
use App\Models\SocialMedia;
use Illuminate\Support\Collection;

/** Public storefront branding reuses existing Admin Site Settings and Social Media. */
final class StorefrontBranding
{
    private ?SiteSetting $settings = null;
    private bool $loaded = false;
    private ?Collection $links = null;

    private function settings(): ?SiteSetting
    {
        if (! $this->loaded) {
            $this->settings = SiteSetting::query()->with('media')->where('status', true)->latest()->first();
            $this->loaded = true;
        }
        return $this->settings;
    }

    public function name(): string
    {
        $name = trim((string) ($this->settings()?->website_name ?? ''));
        return $name !== '' ? $name : (string) config('app.name', 'Storefront');
    }

    public function logo(): ?string
    {
        return $this->settings()?->getFirstMediaUrl('site_logo') ?: null;
    }

    public function favicon(): ?string
    {
        return $this->settings()?->getFirstMediaUrl('site_favicon') ?: null;
    }

    public function socialLinks(): Collection
    {
        if ($this->links !== null) {
            return $this->links;
        }
        $allowed = ['facebook', 'instagram', 'youtube', 'linkedin', 'twitter', 'x', 'tiktok', 'pinterest'];
        $this->links = SocialMedia::query()->active()->orderByDesc('id')->limit(25)->get()
            ->map(static function (SocialMedia $social) use ($allowed): ?array {
                $platform = strtolower(trim((string) $social->platform_name));
                if (! in_array($platform, $allowed, true)) {
                    return null;
                }
                $parts = parse_url((string) $social->link);
                if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
                    || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
                    return null;
                }
                return ['platform' => $platform, 'label' => $social->platform_name, 'url' => $social->link];
            })->filter()->unique('platform')->take(8)->values();
        return $this->links;
    }
}
