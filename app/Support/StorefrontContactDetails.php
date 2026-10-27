<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SiteSetting;

/** Only public, active Site Settings fields may appear on the storefront. */
final class StorefrontContactDetails
{
    /** @return array<string, string|null> */
    public function get(): array
    {
        $site = SiteSetting::query()->where('status', true)->latest('id')->first();
        $email = trim((string) ($site?->email ?? ''));
        $phone = trim((string) ($site?->phone ?: $site?->hotline ?: ''));
        $address = trim((string) ($site?->address ?? ''));
        $number = preg_replace('/\D+/', '', $phone);
        $validPhone = preg_match('/\A\+?[0-9() .-]+\z/D', $phone) === 1
            && is_string($number) && preg_match('/\A\d{8,15}\z/D', $number) === 1;
        $validEmail = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        $validAddress = $address !== '' && mb_strlen($address) <= 1500;
        $encodedAddress = $validAddress ? rawurlencode($address) : null;

        return [
            'phone' => $validPhone ? $phone : null,
            'phone_href' => $validPhone ? 'tel:' . (str_starts_with($phone, '+') ? '+' : '') . $number : null,
            'email' => $validEmail ? $email : null,
            'email_href' => $validEmail ? 'mailto:' . $email : null,
            'address' => $validAddress ? $address : null,
            'map_embed' => $encodedAddress ? 'https://www.google.com/maps?q=' . $encodedAddress . '&output=embed' : null,
            'map_link' => $encodedAddress ? 'https://www.google.com/maps/search/?api=1&query=' . $encodedAddress : null,
            'description' => trim((string) ($site?->business_short_description ?? '')) ?: null,
            'working_hours' => trim((string) ($site?->working_hours ?? '')) ?: null,
        ];
    }
}
