<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SiteSetting;

/**
 * Approved storefront contact actions. The existing SiteSetting row is the
 * only source: no sample contact buttons and no user-controlled link targets.
 */
final class StorefrontContactLinks
{
    /** @return list<array{label: string, href: string, external: bool}> */
    public function active(): array
    {
        $setting = SiteSetting::query()->where('status', true)->latest('id')->first();
        if ($setting === null) {
            return [];
        }

        return self::fromFields([
            'whatsapp_number' => $setting->whatsapp_number,
            'messenger_link' => $setting->messenger_link,
            'email' => $setting->email,
            'phone' => $setting->phone ?: $setting->hotline,
        ]);
    }

    /**
     * Pure conversion for source/QA tests; never returns unsafe or invented URLs.
     *
     * @param array<string, mixed> $fields
     * @return list<array{label: string, href: string, external: bool}>
     */
    public static function fromFields(array $fields): array
    {
        $links = [];

        $whatsappRaw = trim((string) ($fields['whatsapp_number'] ?? ''));
        if ($whatsappRaw !== '' && preg_match('/\A[+0-9()\s.\-]+\z/D', $whatsappRaw) === 1) {
            $digits = preg_replace('/\D+/', '', $whatsappRaw);
            if (is_string($digits)) {
                // Local Bangladeshi mobile format is normalized to country code 880.
                if (preg_match('/\A01\d{9}\z/D', $digits) === 1) {
                    $digits = '88' . $digits;
                }
                if (preg_match('/\A[1-9]\d{7,14}\z/D', $digits) === 1) {
                    $links[] = ['label' => 'WhatsApp', 'href' => 'https://wa.me/' . $digits, 'external' => true];
                }
            }
        }

        $messenger = trim((string) ($fields['messenger_link'] ?? ''));
        if ($messenger !== '' && strlen($messenger) <= 2048 && filter_var($messenger, FILTER_VALIDATE_URL)) {
            $parts = parse_url($messenger);
            if (is_array($parts) && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
                && ! isset($parts['user']) && ! isset($parts['pass'])
                && ! isset($parts['port']) && ! isset($parts['fragment'])) {
                $host = strtolower((string) ($parts['host'] ?? ''));
                $path = (string) ($parts['path'] ?? '');
                $isDirectMessenger = in_array($host, ['m.me', 'www.m.me'], true) && $path !== '' && $path !== '/';
                $isMessengerThread = in_array($host, ['messenger.com', 'www.messenger.com'], true)
                    && str_starts_with($path, '/t/') && strlen($path) > 3;
                $isFacebookThread = in_array($host, ['facebook.com', 'www.facebook.com'], true)
                    && str_starts_with($path, '/messages/t/') && strlen($path) > 12;
                if ($isDirectMessenger || $isMessengerThread || $isFacebookThread) {
                    $links[] = ['label' => 'Messenger', 'href' => $messenger, 'external' => true];
                }
            }
        }

        $email = trim((string) ($fields['email'] ?? ''));
        if ($email !== '' && strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $links[] = ['label' => 'Email', 'href' => 'mailto:' . $email, 'external' => false];
        }

        $phone = trim((string) ($fields['phone'] ?? ''));
        if ($phone !== '' && preg_match('/\A\+?[0-9()\s.\-]+\z/D', $phone) === 1) {
            $digits = preg_replace('/\D+/', '', $phone);
            if (is_string($digits) && preg_match('/\A\d{8,15}\z/D', $digits) === 1) {
                $dial = str_starts_with($phone, '+') ? '+' . $digits : $digits;
                $links[] = ['label' => 'Call', 'href' => 'tel:' . $dial, 'external' => false];
            }
        }

        return $links;
    }
}
