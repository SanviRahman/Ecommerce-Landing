<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SteadfastCourierService
{
    private string $baseUrl;

    private ?string $apiKey;

    private ?string $secretKey;

    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('steadfast.base_url'), '/');
        $this->apiKey = config('steadfast.api_key');
        $this->secretKey = config('steadfast.secret_key');
        $this->timeout = (int) config('steadfast.timeout', 30);
    }

    /**
     * Create order on Steadfast.
     */
    public function createOrder(Order $order): array
    {
        $this->ensureConfigured();

        if ($order->courier_service !== 'steadfast') {
            throw new RuntimeException('This order courier service is not SteadFast.');
        }

        if ($order->steadfast_consignment_id || $order->steadfast_tracking_code) {
            throw new RuntimeException('This order already sent to SteadFast.');
        }

        $payload = $this->makeOrderPayload($order);

        $response = $this->client()
            ->post($this->baseUrl . '/create_order', $payload);

        $data = $this->decodeResponse($response);

        if (! $response->successful() || (int) data_get($data, 'status') !== 200) {
            $message = data_get($data, 'message', 'SteadFast order create failed.');

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

    /**
     * Check status by invoice id.
     */
    public function statusByInvoice(string $invoiceId): array
    {
        $this->ensureConfigured();

        $response = $this->client()
            ->get($this->baseUrl . '/status_by_invoice/' . urlencode($invoiceId));

        return $this->decodeResponse($response);
    }

    /**
     * Check status by tracking code.
     */
    public function statusByTrackingCode(string $trackingCode): array
    {
        $this->ensureConfigured();

        $response = $this->client()
            ->get($this->baseUrl . '/status_by_trackingcode/' . urlencode($trackingCode));

        return $this->decodeResponse($response);
    }

    /**
     * Check current SteadFast balance.
     */
    public function getBalance(): array
    {
        $this->ensureConfigured();

        $response = $this->client()
            ->get($this->baseUrl . '/get_balance');

        return $this->decodeResponse($response);
    }

    /**
     * Sync SteadFast status into local order.
     */
    public function syncStatus(Order $order): array
    {
        if ($order->steadfast_tracking_code) {
            $data = $this->statusByTrackingCode($order->steadfast_tracking_code);
        } else {
            $data = $this->statusByInvoice($order->invoice_id);
        }

        $deliveryStatus = data_get($data, 'delivery_status');

        $updateData = [
            'steadfast_status' => $deliveryStatus,
            'steadfast_response' => $data,
            'steadfast_synced_at' => now(),
        ];

        if (config('steadfast.auto_update_order_status')) {
            if ($deliveryStatus === 'delivered') {
                $updateData['order_status'] = 'delivered';
                $updateData['delivered_at'] = now();
            }

            if ($deliveryStatus === 'cancelled') {
                $updateData['order_status'] = 'cancelled';
                $updateData['cancelled_at'] = now();
            }
        }

        $order->update($updateData);

        return $data;
    }

    /**
     * Prepare SteadFast payload from local order.
     */
    private function makeOrderPayload(Order $order): array
    {
        $order->loadMissing('items');

        return [
            'invoice' => $order->invoice_id,
            'recipient_name' => Str::limit($order->customer_name, 100, ''),
            'recipient_phone' => $this->normalizePhone($order->phone),
            'recipient_address' => Str::limit($order->address, 250, ''),
            'cod_amount' => (float) ($order->total_amount ?? 0),
            'note' => $this->makeNote($order),
            'item_description' => $this->makeItemDescription($order),
            'total_lot' => max(1, (int) $order->items->sum('quantity')),
            'delivery_type' => 0,
        ];
    }

    /**
     * Laravel HTTP client with required headers.
     */
    private function client()
    {
        return Http::timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Api-Key' => $this->apiKey,
                'Secret-Key' => $this->secretKey,
                'Content-Type' => 'application/json',
            ]);
    }

    /**
     * Decode response safely.
     */
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

    /**
     * Ensure .env credentials exist.
     */
    private function ensureConfigured(): void
    {
        if (blank($this->apiKey) || blank($this->secretKey)) {
            throw new RuntimeException('SteadFast API key or secret key is missing. Please update .env file.');
        }
    }

    /**
     * Convert BD phone to 11 digit format.
     */
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

    /**
     * Order note for courier.
     */
    private function makeNote(Order $order): ?string
    {
        $notes = [];

        if ($order->delivery_area) {
            $notes[] = 'Area: ' . ucwords(str_replace('_', ' ', $order->delivery_area));
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

    /**
     * Product item description.
     */
    private function makeItemDescription(Order $order): ?string
    {
        if (! $order->relationLoaded('items')) {
            $order->load('items');
        }

        $items = $order->items
            ->map(function ($item) {
                return $item->quantity . ' x ' . $item->product_name;
            })
            ->implode(', ');

        return $items ? Str::limit($items, 250, '') : null;
    }
}