<?php

namespace App\Services;

use App\Models\CourierAccount;
use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PathaoCourierService
{
    private int $timeout;

    public function __construct(
        private readonly PathaoStatusService $statusService
    ) {
        $this->timeout = (int) config('steadfast.timeout', 30);
    }

    public function createOrder(Order $order): array
    {
        $order->loadMissing(['items', 'courierAccount']);

        $courier = $this->resolveCourierAccount($order);

        if (strtolower((string) $courier->code) !== 'pathao') {
            throw new RuntimeException('Selected courier is not Pathao.');
        }

        $this->ensureConfigured($courier);

        if ($order->pathao_consignment_id) {
            throw new RuntimeException('This order already sent to Pathao.');
        }

        $payload = $this->makeOrderPayload($order, $courier);

        try {
            $response = $this->sendOrderRequest($courier, $payload);

            if ($response->status() === 401) {
                $this->refreshToken($courier);
                $response = $this->sendOrderRequest($courier->fresh(), $payload);
            }
        } catch (ConnectionException $exception) {
            $message = 'Pathao connection failed. Please check courier base URL. Current URL: '
                . $this->baseUrl($courier);

            $order->update([
                'pathao_note' => $message,
                'pathao_response' => [
                    'error' => $exception->getMessage(),
                    'base_url' => $this->baseUrl($courier),
                ],
                'pathao_synced_at' => now(),
            ]);

            throw new RuntimeException($message);
        } catch (Throwable $exception) {
            throw new RuntimeException($exception->getMessage());
        }

        $data = $this->decodeResponse($response);

        if (! $response->successful()) {
            $message = $this->responseMessage($data, 'Pathao order create failed.');

            $order->update([
                'pathao_note' => $message,
                'pathao_response' => $data,
                'pathao_synced_at' => now(),
            ]);

            throw new RuntimeException($message);
        }

        $consignmentId = data_get($data, 'data.consignment_id')
            ?: data_get($data, 'consignment_id');

        $merchantOrderId = data_get($data, 'data.merchant_order_id')
            ?: data_get($data, 'merchant_order_id')
            ?: $order->invoice_id;

        $orderStatus = data_get($data, 'data.order_status')
            ?: data_get($data, 'order_status')
            ?: 'order_created';

        $deliveryFee = data_get($data, 'data.delivery_fee')
            ?: data_get($data, 'delivery_fee')
            ?: 0;

        $order->update([
            'pathao_consignment_id' => $consignmentId,
            'pathao_merchant_order_id' => $merchantOrderId,
            'pathao_status' => $this->statusService->normalize((string) $orderStatus),
            'pathao_delivery_fee' => (float) $deliveryFee,
            'pathao_note' => data_get($data, 'message', 'Pathao order created successfully.'),
            'pathao_response' => $data,
            'pathao_sent_at' => now(),
            'pathao_synced_at' => now(),
        ]);

        return $data;
    }


    public function syncStatus(Order $order): array
    {
        $order->loadMissing('courierAccount');

        $courier = $this->resolveCourierAccount($order);

        if (strtolower((string) $courier->code) !== 'pathao') {
            throw new RuntimeException('Selected courier is not Pathao.');
        }

        $this->ensureAuthenticationConfigured($courier);

        if (blank($order->pathao_consignment_id)) {
            throw new RuntimeException('Pathao consignment ID is missing for this order.');
        }

        try {
            $response = $this->sendStatusRequest($courier, (string) $order->pathao_consignment_id);

            if ($response->status() === 401) {
                $this->refreshToken($courier);
                $response = $this->sendStatusRequest(
                    $courier->fresh(),
                    (string) $order->pathao_consignment_id
                );
            }
        } catch (ConnectionException $exception) {
            $message = 'Pathao status connection failed. Please check courier base URL. Current URL: '
                . $this->baseUrl($courier);

            $order->update([
                'pathao_note' => $message,
                'pathao_response' => [
                    'error' => $exception->getMessage(),
                    'base_url' => $this->baseUrl($courier),
                ],
                'pathao_synced_at' => now(),
            ]);

            throw new RuntimeException($message);
        } catch (Throwable $exception) {
            throw new RuntimeException($exception->getMessage());
        }

        $data = $this->decodeResponse($response);

        if (! $response->successful()) {
            $message = $this->responseMessage($data, 'Pathao status sync failed.');

            $order->update([
                'pathao_note' => $message,
                'pathao_response' => $data,
                'pathao_synced_at' => now(),
            ]);

            throw new RuntimeException($message);
        }

        $status = $this->statusService->statusFromPayload($data);

        if ($status === '') {
            $order->update([
                'pathao_note' => 'Pathao status response did not contain an order status.',
                'pathao_response' => $data,
                'pathao_synced_at' => now(),
            ]);

            throw new RuntimeException('Pathao response did not contain an order status.');
        }

        $this->statusService->apply(
            $order,
            $courier,
            $status,
            $data,
            'api'
        );

        return $data;
    }

    public function refreshToken(CourierAccount $courier): array
    {
        $this->ensureAuthenticationConfigured($courier);

        $response = $this->issueModernToken($courier);
        $data = $this->decodeResponse($response);

        /*
         * Current Pathao authentication uses Client ID + Client Secret.
         * Some older merchant integrations still provide username/password,
         * so a compatibility request is attempted only when modern login fails.
         */
        if (! $response->successful() || blank($this->accessTokenFrom($data))) {
            $legacyResponse = $this->issueLegacyPasswordToken($courier);

            if ($legacyResponse) {
                $legacyData = $this->decodeResponse($legacyResponse);

                if ($legacyResponse->successful() && filled($this->accessTokenFrom($legacyData))) {
                    $response = $legacyResponse;
                    $data = $legacyData;
                }
            }
        }

        if (! $response->successful() || blank($this->accessTokenFrom($data))) {
            throw new RuntimeException($this->responseMessage(
                $data,
                'Pathao token generation failed. Check Client ID, Client Secret, username and password.'
            ));
        }

        return $this->persistToken($courier, $data);
    }

    public function accessToken(CourierAccount $courier, bool $forceRefresh = false): string
    {
        if (! $forceRefresh && $this->hasUsableToken($courier)) {
            return trim((string) $courier->token);
        }

        $tokenData = $this->refreshToken($courier);

        return (string) $tokenData['access_token'];
    }

    public function tokenStatus(CourierAccount $courier): array
    {
        if (blank($courier->token)) {
            return [
                'state' => 'missing',
                'label' => 'Token Missing',
                'expires_at' => null,
            ];
        }

        if ($courier->token_expires_at && $courier->token_expires_at->isPast()) {
            return [
                'state' => 'expired',
                'label' => 'Token Expired',
                'expires_at' => $courier->token_expires_at,
            ];
        }

        return [
            'state' => 'active',
            'label' => 'Token Available',
            'expires_at' => $courier->token_expires_at,
        ];
    }

    private function sendOrderRequest(CourierAccount $courier, array $payload): Response
    {
        $token = $this->accessToken($courier);

        return Http::timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['source' => 'laravel'])
            ->withToken($token)
            ->post($this->baseUrl($courier) . '/aladdin/api/v1/orders', $payload);
    }


    private function sendStatusRequest(
        CourierAccount $courier,
        string $consignmentId
    ): Response {
        $token = $this->accessToken($courier);

        return Http::timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['source' => 'laravel'])
            ->withToken($token)
            ->get(
                $this->baseUrl($courier)
                    . '/aladdin/api/v1/orders/'
                    . urlencode($consignmentId)
                    . '/info'
            );
    }

    private function issueModernToken(CourierAccount $courier): Response
    {
        return Http::timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl($courier) . '/aladdin/api/v1/external/login', [
                'client_id' => trim((string) $courier->api_key),
                'client_secret' => trim((string) $courier->secret_key),
            ]);
    }

    private function issueLegacyPasswordToken(CourierAccount $courier): ?Response
    {
        if (blank($courier->auth_username) || blank($courier->auth_password)) {
            return null;
        }

        return Http::timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl($courier) . '/aladdin/api/v1/issue-token', [
                'grant_type' => 'password',
                'client_id' => trim((string) $courier->api_key),
                'client_secret' => trim((string) $courier->secret_key),
                'username' => trim((string) $courier->auth_username),
                'password' => (string) $courier->auth_password,
            ]);
    }

    private function persistToken(CourierAccount $courier, array $data): array
    {
        $accessToken = $this->accessTokenFrom($data);
        $refreshToken = data_get($data, 'refresh_token')
            ?: data_get($data, 'data.refresh_token');
        $tokenType = data_get($data, 'token_type')
            ?: data_get($data, 'data.token_type')
            ?: 'Bearer';
        $expiresIn = (int) (
            data_get($data, 'expires_in')
                ?: data_get($data, 'data.expires_in')
                ?: 3600
        );

        $expiresIn = max(60, $expiresIn);
        $expiresAt = now()->addSeconds($expiresIn);

        $courier->forceFill([
            'token' => $accessToken,
            'refresh_token' => $refreshToken ?: null,
            'token_type' => $tokenType,
            'token_expires_at' => $expiresAt,
        ])->save();

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => $tokenType,
            'expires_in' => $expiresIn,
            'expires_at' => $expiresAt,
        ];
    }

    private function hasUsableToken(CourierAccount $courier): bool
    {
        if (blank($courier->token)) {
            return false;
        }

        if (! $courier->token_expires_at) {
            return true;
        }

        return $courier->token_expires_at->greaterThan(now()->addMinutes(2));
    }

    private function accessTokenFrom(array $data): ?string
    {
        $token = data_get($data, 'access_token')
            ?: data_get($data, 'data.access_token');

        return is_scalar($token) && trim((string) $token) !== ''
            ? trim((string) $token)
            : null;
    }

    private function resolveCourierAccount(Order $order): CourierAccount
    {
        $courier = $order->courierAccount;

        if (! $courier && $order->courier_account_id) {
            $courier = CourierAccount::query()->find($order->courier_account_id);
        }

        if (! $courier && $order->courier_service === 'pathao') {
            $courier = CourierAccount::query()
                ->where('code', 'pathao')
                ->where('status', true)
                ->latest()
                ->first();
        }

        if (! $courier) {
            throw new RuntimeException('Please select Pathao courier from admin order details page first.');
        }

        if (! $courier->status) {
            throw new RuntimeException('Selected Pathao API account is inactive.');
        }

        return $courier;
    }

    private function makeOrderPayload(Order $order, CourierAccount $courier): array
    {
        $order->loadMissing('items');

        $settings = $courier->settings ?? [];

        return [
            'store_id' => (int) data_get($settings, 'store_id'),
            'merchant_order_id' => $order->invoice_id,
            'recipient_name' => Str::limit($order->customer_name ?: 'Customer', 100, ''),
            'recipient_phone' => $this->normalizePhone($order->phone),
            'recipient_address' => Str::limit($order->address ?: 'N/A', 250, ''),
            'delivery_type' => (int) data_get($settings, 'delivery_type', 48),
            'item_type' => (int) data_get($settings, 'item_type', 2),
            'special_instruction' => Str::limit($this->makeInstruction($order, $courier), 250, ''),
            'item_quantity' => max(1, (int) $order->items->sum('quantity')),
            'item_weight' => (float) data_get($settings, 'item_weight', 0.5),
            'item_description' => Str::limit($this->makeItemDescription($order), 250, ''),
            'amount_to_collect' => (float) ($order->total_amount ?? 0),
        ];
    }

    private function baseUrl(CourierAccount $courier): string
    {
        return rtrim($courier->base_url ?: 'https://api-hermes.pathao.com', '/');
    }

    private function ensureConfigured(?CourierAccount $courier): void
    {
        if (! $courier) {
            throw new RuntimeException('No active Pathao courier API account found.');
        }

        $this->ensureAuthenticationConfigured($courier);

        if (blank(data_get($courier->settings ?? [], 'store_id'))) {
            throw new RuntimeException('Pathao Store ID is missing. Please add Store ID in Courier API Accounts.');
        }
    }

    private function ensureAuthenticationConfigured(CourierAccount $courier): void
    {
        if (blank($courier->base_url)) {
            throw new RuntimeException('Pathao API base URL is missing.');
        }

        if (blank($courier->api_key)) {
            throw new RuntimeException('Pathao Client ID is missing.');
        }

        if (blank($courier->secret_key)) {
            throw new RuntimeException('Pathao Client Secret is missing.');
        }
    }

    private function decodeResponse(Response $response): array
    {
        $data = $response->json();

        if (is_array($data)) {
            return $data;
        }

        return [
            'status' => $response->status(),
            'message' => $response->body(),
        ];
    }

    private function responseMessage(array $data, string $fallback): string
    {
        $message = data_get($data, 'message')
            ?: data_get($data, 'error')
            ?: data_get($data, 'errors.0')
            ?: data_get($data, 'data.message')
            ?: $fallback;

        return is_array($message)
            ? json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : (string) $message;
    }

    private function normalizePhone(?string $phone): string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);

        if (str_starts_with($phone, '880') && strlen($phone) === 13) {
            $phone = substr($phone, 2);
        }

        if (strlen($phone) === 10 && str_starts_with($phone, '1')) {
            $phone = '0' . $phone;
        }

        return $phone;
    }

    private function makeInstruction(Order $order, CourierAccount $courier): string
    {
        $notes = [];
        $defaultInstruction = data_get($courier->settings ?? [], 'special_instruction');

        if ($defaultInstruction) {
            $notes[] = $defaultInstruction;
        }

        if ($order->delivery_area) {
            $notes[] = 'Area: ' . ucwords(str_replace('_', ' ', $order->delivery_area));
        }

        if ($order->is_free_delivery) {
            $notes[] = 'Free Delivery';
        }

        if ($order->customer_note) {
            $notes[] = 'Customer Note: ' . $order->customer_note;
        }

        if ($order->admin_note) {
            $notes[] = 'Admin Note: ' . $order->admin_note;
        }

        return implode(' | ', $notes) ?: 'Please call before delivery.';
    }

    private function makeItemDescription(Order $order): string
    {
        $items = $order->items
            ->map(fn ($item) => $item->quantity . ' x ' . $item->product_name)
            ->implode(', ');

        return $items ?: 'Product order';
    }
}
