<?php

namespace App\Services;

use App\Models\Order;
use App\Models\TrackingPixel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends optional Meta server events; browser and server share an event_name + event_id.
 * Pixel credentials never enter HTML, JSON responses or application logs.
 */
class MetaConversionsApiService
{
    public function isEnabled(): bool
    {
        return TrackingPixel::query()->active()->meta()
            ->where('capi_enabled', true)->exists();
    }

    public function sendBrowserEvent(array $data, Request $request): int
    {
        if (! in_array($data['event_name'] ?? null, [
            'PageView', 'ViewContent', 'AddToCart', 'InitiateCheckout',
        ], true)) {
            return 0;
        }

        return $this->sendToActivePixels(
            (string) $data['event_name'],
            (string) $data['event_id'],
            $data['custom_data'] ?? [],
            $request,
        );
    }

    /** Mirrors the existing first-party JS makeEventId('purchase', transaction_id). */
    public function purchaseEventId(Order $order): string
    {
        $reference = (string) ($order->invoice_id
            ?? $order->invoice_no
            ?? $order->invoice_number
            ?? ('ORDER-' . $order->id));

        return 'sfa_purchase_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', trim($reference));
    }

    /** Schedule real-order server tracking without delaying the checkout response. */
    public function dispatchPurchaseAfterResponse(Order $order, Request $request): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        app()->terminating(function () use ($order, $request): void {
            try {
                $this->sendPurchase($order, $request);
            } catch (Throwable $exception) {
                Log::warning('Meta CAPI purchase dispatch failed', [
                    'order_id' => $order->id,
                    'exception_type' => get_class($exception),
                ]);
            }
        });
    }

    /**
     * Called after the success response is emitted. A cache claim stops repeated
     * success-page refreshes from triggering another CAPI Purchase per pixel.
     */
    public function sendPurchase(Order $order, Request $request): void
    {
        if (! $order->created_at || $order->created_at->lt(now()->subDays(7))) {
            return; // Meta does not accept arbitrarily old website events.
        }

        $eventId = $this->purchaseEventId($order);
        $order->loadMissing(['items.product']);
        $items = $order->items->map(static fn ($item): array => [
            'id' => (string) ($item->product_id ?? $item->product?->id ?? ''),
            'quantity' => (int) ($item->quantity ?? 1),
            'item_price' => (float) ($item->unit_price ?? $item->price ?? 0),
        ])->filter(static fn ($item): bool => $item['id'] !== '')->values()->all();

        $customData = [
            'currency' => 'BDT',
            'value' => (float) ($order->total_amount ?? 0),
            'order_id' => (string) ($order->invoice_id ?? $order->id),
            'content_type' => 'product',
            'content_ids' => array_values(array_unique(array_column($items, 'id'))),
            'contents' => $items,
            'num_items' => array_sum(array_column($items, 'quantity')),
        ];

        $this->sendToActivePixels('Purchase', $eventId, $customData, $request, $order);
    }

    /** Explicit admin action; the test code is never exposed to clients. */
    public function sendTestEvent(TrackingPixel $pixel, Request $request): bool
    {
        if (! $pixel->status || $pixel->platform !== 'meta' || ! $pixel->capi_enabled
            || ! $pixel->capi_access_token || ! $pixel->capi_test_event_code) {
            return false;
        }

        return $this->sendOne(
            $pixel, 'PageView', 'gtm_test_' . Str::uuid(), [], $request, null, true,
        );
    }

    private function sendToActivePixels(
        string $eventName,
        string $eventId,
        array $customData,
        Request $request,
        ?Order $order = null,
    ): int {
        $delivered = 0;
        // The access token is decrypted only server-side, never SELECTed into the public view.
        $pixels = TrackingPixel::query()->active()->meta()
            ->where('capi_enabled', true)->orderByDesc('id')->get()
            ->filter(static fn (TrackingPixel $pixel): bool => (bool) $pixel->capi_access_token)
            ->unique('pixel_id'); // One server delivery per destination even with legacy duplicates.

        foreach ($pixels as $pixel) {
            if (! $pixel->capi_access_token) {
                continue;
            }

            $cacheKey = null;
            if ($eventName === 'Purchase' && $order) {
                $cacheKey = 'meta_capi_purchase_' . $pixel->id . '_' . $order->id;
                if (! Cache::add($cacheKey, true, now()->addDays(7))) {
                    continue;
                }
            }

            $sent = $this->sendOne($pixel, $eventName, $eventId, $customData, $request, $order);
            if ($sent) {
                $delivered++;
            } elseif ($cacheKey) {
                Cache::forget($cacheKey); // A later page load can retry a failed send.
            }
        }
        return $delivered;
    }

    private function sendOne(
        TrackingPixel $pixel,
        string $eventName,
        string $eventId,
        array $customData,
        Request $request,
        ?Order $order,
        bool $test = false,
    ): bool {
        $userData = [];
        if ($request->ip()) {
            $userData['client_ip_address'] = $request->ip();
        }
        if ($request->userAgent()) {
            $userData['client_user_agent'] = mb_substr($request->userAgent(), 0, 500);
        }

        // Cookies set by Meta itself are not Laravel-encrypted. Read only their
        // raw cookie values; normal Request::cookie() may discard those cookies.
        $cookies = [];
        foreach (explode(';', (string) $request->headers->get('cookie', '')) as $cookie) {
            $parts = explode('=', trim($cookie), 2);
            if (count($parts) === 2 && in_array($parts[0], ['_fbp', '_fbc'], true)) {
                $cookies[$parts[0]] = rawurldecode($parts[1]);
            }
        }
        foreach (['_fbp' => 'fbp', '_fbc' => 'fbc'] as $cookieName => $parameter) {
            $value = $cookies[$cookieName] ?? '';
            if (is_string($value) && preg_match('/\Afb\.1\.[A-Za-z0-9._-]{1,220}\z/D', $value)) {
                $userData[$parameter] = $value;
            }
        }

        if ($order && $order->phone) {
            $number = preg_replace('/\D+/', '', (string) $order->phone);
            if (preg_match('/\A01\d{9}\z/D', $number)) {
                $number = '88' . $number;
            }
            if ($number !== '' && preg_match('/\A\d{10,15}\z/D', $number)) {
                $userData['ph'] = [hash('sha256', $number)];
            }
        }

        $sourceUrl = $request->url();
        $referer = $request->headers->get('referer');
        if ($eventName !== 'Purchase' && is_string($referer)) {
            $host = parse_url($referer, PHP_URL_HOST);
            if (is_string($host) && strcasecmp($host, $request->getHost()) === 0
                && in_array(parse_url($referer, PHP_URL_SCHEME), ['https', 'http'], true)) {
                $sourceUrl = strtok($referer, '?#');
            }
        }

        $event = [
            'event_name' => $eventName,
            'event_time' => time(),
            'event_id' => $eventId,
            'action_source' => 'website',
            'event_source_url' => $sourceUrl,
            'user_data' => $userData,
        ];
        if ($customData !== []) {
            $event['custom_data'] = $customData;
        }

        $payload = ['data' => [$event]];
        // The admin test button always uses a test code. While developing on
        // localhost, actual funnel actions may also appear in Meta Test Events.
        // Production traffic MUST never inherit this saved code.
        if ($pixel->capi_test_event_code && ($test || app()->environment(['local', 'testing']))) {
            $payload['test_event_code'] = $pixel->capi_test_event_code;
        }

        $version = (string) config('services.meta_capi.graph_version', 'v25.0');
        if (! preg_match('/\Av\d{1,2}\.0\z/D', $version)) {
            $version = 'v25.0';
        }

        // A numeric Meta dataset/pixel ID is required. Never interpolate arbitrary URLs.
        if (! preg_match('/\A\d{5,30}\z/D', (string) $pixel->pixel_id)) {
            return false;
        }

        try {
            $response = Http::asJson()
                ->withToken($pixel->capi_access_token)
                ->connectTimeout(2)
                ->timeout(4)
                ->post("https://graph.facebook.com/{$version}/{$pixel->pixel_id}/events", $payload);

            if ($response->successful() && (int) $response->json('events_received', 0) > 0) {
                return true;
            }

            Log::warning('Meta CAPI rejected an event', [
                'integration_id' => $pixel->id,
                'event_name' => $eventName,
                'http_status' => $response->status(),
            ]);
        } catch (Throwable $exception) {
            // Never log exception messages: upstream HTTP errors may include credentials.
            Log::warning('Meta CAPI transport failed', [
                'integration_id' => $pixel->id,
                'event_name' => $eventName,
                'exception_type' => get_class($exception),
            ]);
        }

        return false;
    }
}
