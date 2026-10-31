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
        return $this->settings()?->getFirstMediaUrl('storefront_logo')
            ?: ($this->settings()?->getFirstMediaUrl('site_logo') ?: null);
    }

    public function favicon(): ?string
    {
        return $this->settings()?->getFirstMediaUrl('storefront_favicon')
            ?: ($this->settings()?->getFirstMediaUrl('site_favicon') ?: null);
    }

    public function socialLinks(): Collection
    {
        if ($this->links !== null) {
            return $this->links;
        }
        $site = $this->settings();
        $managed = $site !== null && $site->footer_social_managed;
        $allowed = ['facebook', 'instagram', 'youtube', 'linkedin', 'twitter', 'x', 'tiktok', 'pinterest'];
        $links = collect();
        if ($managed) {
            foreach (['facebook', 'instagram', 'youtube', 'linkedin'] as $platform) {
                $url = trim((string) ($site->{'footer_'.$platform.'_url'} ?? ''));
                if ($this->safeHttpsUrl($url)) {
                    $links->push(['platform' => $platform, 'label' => ucfirst($platform), 'url' => $url]);
                }
            }
        } else {
            $links = SocialMedia::query()->active()->orderByDesc('id')->limit(25)->get()
                ->map(function (SocialMedia $social) use ($allowed): ?array {
                    $platform = strtolower(trim((string) $social->platform_name));
                    if (! in_array($platform, $allowed, true) || ! $this->safeHttpsUrl((string) $social->link)) {
                        return null;
                    }
                    return ['platform' => $platform, 'label' => $social->platform_name, 'url' => $social->link];
                })->filter()->unique('platform')->values();
        }

        // Call and WhatsApp are managed by the existing phone fields regardless of
        // whether social links have been migrated from the old SocialMedia screen.
        $phone = trim((string) ($site?->phone ?: $site?->hotline ?: ''));
        $digits = preg_replace('/\D+/', '', $phone);
        if (preg_match('/\A\+?[0-9() .-]+\z/D', $phone) && preg_match('/\A\d{8,15}\z/D', (string) $digits)) {
            $links->push(['platform' => 'phone', 'label' => 'Call Us',
                'url' => 'tel:' . (str_starts_with($phone, '+') ? '+' : '') . $digits]);
        }
        $whatsApp = preg_replace('/\D+/', '', trim((string) ($site?->whatsapp_number ?? '')));
        if (preg_match('/\A\d{8,15}\z/D', (string) $whatsApp)) {
            if (str_starts_with($whatsApp, '01') && strlen($whatsApp) === 11) {
                $whatsApp = '88' . $whatsApp;
            }
            $links->push(['platform' => 'whatsapp', 'label' => 'WhatsApp',
                'url' => 'https://wa.me/' . $whatsApp]);
        }
        return $this->links = $links->unique('platform')->take(10)->values();
    }

    private function safeHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);
        return is_array($parts) && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && ! empty($parts['host']) && ! isset($parts['user']) && ! isset($parts['pass'])
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

}
