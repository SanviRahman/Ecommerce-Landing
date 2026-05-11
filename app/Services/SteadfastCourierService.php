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

class SteadfastCourierService
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

        if ($courier->code !== 'steadfast') {
            throw new RuntimeException('Selected courier is not SteadFast.');
        }

        $this->ensureConfigured($courier);

        if ($order->steadfast_consignment_id || $order->steadfast_tracking_code) {
            throw new RuntimeException('This order already sent to SteadFast.');
        }

        try {
            $response = $this->client($courier)
                ->post($this->baseUrl($courier) . '/create_order', $this->makeOrderPayload($order));
        } catch (ConnectionException $e) {
            $message = 'SteadFast connection failed. Please check courier base URL. Current URL: ' . $this->baseUrl($courier);

            $order->update([
                'steadfast_note' => $message,
                'steadfast_response' => [
                    'error' => $e->getMessage(),
                    'base_url' => $this->baseUrl($courier),
                ],
                'steadfast_synced_at' => now(),
            ]);

            throw new RuntimeException($message);
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage());
        }

        $data = $this->decodeResponse($response);

        if (! $response->successful() || (int) data_get($data, 'status') !== 200) {
            $message = data_get($data, 'message')
                ?: data_get($data, 'error')
                ?: 'SteadFast order create failed.';

            $order->update([
                'steadfast_note' => $message,
                'steadfast_response' => $data,
                'steadfast_synced_at' => now(),
            ]);

            throw new RuntimeException($message);
        }

        $consignment = data_get($data, 'consignment', []);

        $order->update([
            'steadfast_consignment_id' => data_get($consignment, 'consignment_id'),
            'steadfast_tracking_code' => data_get($consignment, 'tracking_code'),
            'steadfast_status' => data_get($consignment, 'status'),
            'steadfast_note' => data_get($data, 'message'),
            'steadfast_response' => $data,
            'steadfast_sent_at' => now(),
            'steadfast_synced_at' => now(),
        ]);

        return $data;
    }

    public function syncStatus(Order $order): array
    {
        $order->loadMissing('courierAccount');

        $courier = $this->resolveCourierAccount($order);

        if ($courier->code !== 'steadfast') {
            throw new RuntimeException('Selected courier is not SteadFast.');
        }

        $this->ensureConfigured($courier);

        try {
            if ($order->steadfast_tracking_code) {
                $response = $this->client($courier)
                    ->get($this->baseUrl($courier) . '/status_by_trackingcode/' . urlencode($order->steadfast_tracking_code));
            } else {
                $response = $this->client($courier)
                    ->get($this->baseUrl($courier) . '/status_by_invoice/' . urlencode($order->invoice_id));
            }
        } catch (ConnectionException $e) {
            throw new RuntimeException('SteadFast connection failed. Please check courier base URL. Current URL: ' . $this->baseUrl($courier));
        }

        $data = $this->decodeResponse($response);
        $deliveryStatus = data_get($data, 'delivery_status');

        $updateData = [
            'steadfast_status' => $deliveryStatus,
            'steadfast_response' => $data,
            'steadfast_synced_at' => now(),
        ];

        if (config('steadfast.auto_update_order_status')) {
            if ($deliveryStatus === 'delivered') {
                $updateData['order_status'] = Order::STATUS_DELIVERED;
                $updateData['delivered_at'] = now();
            }

            if ($deliveryStatus === 'cancelled') {
                $updateData['order_status'] = Order::STATUS_CANCELLED;
                $updateData['cancelled_at'] = now();
            }
        }

        $order->update($updateData);

        return $data;
    }

    public function getBalance(?CourierAccount $courier = null): array
    {
        $courier = $courier ?: CourierAccount::query()
            ->where('code', 'steadfast')
            ->where('status', true)
            ->latest()
            ->first();

        $this->ensureConfigured($courier);

        try {
            $response = $this->client($courier)
                ->get($this->baseUrl($courier) . '/get_balance');
        } catch (ConnectionException $e) {
            throw new RuntimeException('SteadFast connection failed. Please check courier base URL. Current URL: ' . $this->baseUrl($courier));
        }

        return $this->decodeResponse($response);
    }

    private function resolveCourierAccount(Order $order): CourierAccount
    {
        $courier = $order->courierAccount;

        if (! $courier && $order->courier_account_id) {
            $courier = CourierAccount::query()->find($order->courier_account_id);
        }

        if (! $courier && $order->courier_service === 'steadfast') {
            $courier = CourierAccount::query()
                ->where('code', 'steadfast')
                ->where('status', true)
                ->latest()
                ->first();
        }

        if (! $courier) {
            throw new RuntimeException('Please select SteadFast courier from admin order details page first.');
        }

        if (! $courier->status) {
            throw new RuntimeException('Selected courier API account is inactive.');
        }

        return $courier;
    }

    private function makeOrderPayload(Order $order): array
    {
        $order->loadMissing('items');

        return [
            'invoice' => $order->invoice_id,
            'recipient_name' => Str::limit($order->customer_name ?: 'Customer', 100, ''),
            'recipient_phone' => $this->normalizePhone($order->phone),
            'recipient_address' => Str::limit($order->address ?: 'N/A', 250, ''),
            'cod_amount' => (float) ($order->total_amount ?? 0),
            'note' => $this->makeNote($order),
            'item_description' => $this->makeItemDescription($order),
            'total_lot' => max(1, (int) $order->items->sum('quantity')),
            'delivery_type' => 0,
        ];
    }

    private function client(CourierAccount $courier)
    {
        return Http::timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Api-Key' => trim((string) $courier->api_key),
                'Secret-Key' => trim((string) $courier->secret_key),
                'Content-Type' => 'application/json',
            ]);
    }

    private function baseUrl(CourierAccount $courier): string
    {
        return rtrim($courier->base_url ?: 'https://portal.packzy.com/api/v1', '/');
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

    private function ensureConfigured(?CourierAccount $courier): void
    {
        if (! $courier) {
            throw new RuntimeException('No active SteadFast courier API account found.');
        }

        if (blank($courier->base_url)) {
            throw new RuntimeException('SteadFast API base URL is missing.');
        }

        if (blank($courier->api_key) || blank($courier->secret_key)) {
            throw new RuntimeException('SteadFast API key or secret key is missing.');
        }

        if (str_contains($courier->base_url, 'portal.steadfast.com.bd')) {
            throw new RuntimeException('Wrong SteadFast base URL. Use: https://portal.packzy.com/api/v1');
        }
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

    private function makeNote(Order $order): ?string
    {
        $notes = [];

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

        return empty($notes)
            ? null
            : Str::limit(implode(' | ', $notes), 250, '');
    }

    private function makeItemDescription(Order $order): ?string
    {
        if (! $order->relationLoaded('items')) {
            $order->load('items');
        }

        $items = $order->items
            ->map(fn ($item) => $item->quantity . ' x ' . $item->product_name)
            ->implode(', ');

        return $items ? Str::limit($items, 250, '') : null;
    }
}