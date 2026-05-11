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

    public function __construct()
    {
        $this->timeout = (int) config('steadfast.timeout', 30);
    }

    public function createOrder(Order $order): array
    {
        $order->loadMissing(['items', 'courierAccount']);

        $courier = $this->resolveCourierAccount($order);

        if ($courier->code !== 'pathao') {
            throw new RuntimeException('Selected courier is not Pathao.');
        }

        $this->ensureConfigured($courier);

        if ($order->pathao_consignment_id) {
            throw new RuntimeException('This order already sent to Pathao.');
        }

        try {
            $response = $this->client($courier)
                ->post($this->baseUrl($courier) . '/aladdin/api/v1/orders', $this->makeOrderPayload($order, $courier));
        } catch (ConnectionException $e) {
            $message = 'Pathao connection failed. Please check courier base URL. Current URL: ' . $this->baseUrl($courier);

            $order->update([
                'pathao_note' => $message,
                'pathao_response' => [
                    'error' => $e->getMessage(),
                    'base_url' => $this->baseUrl($courier),
                ],
                'pathao_synced_at' => now(),
            ]);

            throw new RuntimeException($message);
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage());
        }

        $data = $this->decodeResponse($response);

        if (! $response->successful()) {
            $message = data_get($data, 'message')
                ?: data_get($data, 'error')
                ?: data_get($data, 'errors.0')
                ?: 'Pathao order create failed.';

            $order->update([
                'pathao_note' => is_array($message) ? json_encode($message) : $message,
                'pathao_response' => $data,
                'pathao_synced_at' => now(),
            ]);

            throw new RuntimeException(is_array($message) ? json_encode($message) : $message);
        }

        $consignmentId = data_get($data, 'data.consignment_id')
            ?: data_get($data, 'consignment_id');

        $merchantOrderId = data_get($data, 'data.merchant_order_id')
            ?: data_get($data, 'merchant_order_id')
            ?: $order->invoice_id;

        $orderStatus = data_get($data, 'data.order_status')
            ?: data_get($data, 'order_status');

        $deliveryFee = data_get($data, 'data.delivery_fee')
            ?: data_get($data, 'delivery_fee')
            ?: 0;

        $order->update([
            'pathao_consignment_id' => $consignmentId,
            'pathao_merchant_order_id' => $merchantOrderId,
            'pathao_status' => $orderStatus,
            'pathao_delivery_fee' => (float) $deliveryFee,
            'pathao_note' => data_get($data, 'message', 'Pathao order created successfully.'),
            'pathao_response' => $data,
            'pathao_sent_at' => now(),
            'pathao_synced_at' => now(),
        ]);

        return $data;
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

            /*
            |--------------------------------------------------------------------------
            | Pathao Delivery Types
            |--------------------------------------------------------------------------
            | 48 = Normal delivery
            | 12 = On demand / same day, if enabled for merchant
            */
            'delivery_type' => (int) data_get($settings, 'delivery_type', 48),

            /*
            |--------------------------------------------------------------------------
            | Pathao Item Types
            |--------------------------------------------------------------------------
            | 1 = Document
            | 2 = Parcel
            */
            'item_type' => (int) data_get($settings, 'item_type', 2),

            'special_instruction' => Str::limit($this->makeInstruction($order, $courier), 250, ''),

            'item_quantity' => max(1, (int) $order->items->sum('quantity')),
            'item_weight' => (float) data_get($settings, 'item_weight', 0.5),
            'item_description' => Str::limit($this->makeItemDescription($order), 250, ''),

            'amount_to_collect' => (float) ($order->total_amount ?? 0),
        ];
    }

    private function client(CourierAccount $courier)
    {
        return Http::timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->withToken(trim((string) $courier->token));
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

        if (blank($courier->base_url)) {
            throw new RuntimeException('Pathao API base URL is missing.');
        }

        if (blank($courier->token)) {
            throw new RuntimeException('Pathao access token is missing. Please add token in Courier API Accounts.');
        }

        if (blank(data_get($courier->settings ?? [], 'store_id'))) {
            throw new RuntimeException('Pathao store_id is missing. Please add store_id in Courier API Accounts settings.');
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