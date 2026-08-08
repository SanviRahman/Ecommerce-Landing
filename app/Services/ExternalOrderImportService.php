<?php

namespace App\Services;

use App\Models\ExternalWebsite;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExternalOrderImportService
{
    public function __construct(
        private readonly CustomerIdentityService $customerIdentityService
    ) {
    }

    public function import(ExternalWebsite $externalWebsite, array $payload, string $requestIp): array
    {
        $externalOrderId = trim((string) $payload['external_order_id']);
        $incomingSyncUuid = $this->resolveIncomingSyncUuid($payload, $externalOrderId);

        $existingOrder = $this->findExistingOrder(
            $externalWebsite,
            $externalOrderId,
            $incomingSyncUuid
        );

        if ($existingOrder) {
            return [
                'order' => $existingOrder,
                'created' => false,
            ];
        }

        try {
            return DB::transaction(function () use (
                $externalWebsite,
                $payload,
                $requestIp,
                $externalOrderId,
                $incomingSyncUuid
            ) {
                $customer = $this->customerIdentityService->resolveOrCreate(
                    $payload['customer_name'],
                    $payload['phone'],
                    'phone'
                );

                $normalizedItems = $this->normalizeItems($payload['items']);
                $calculatedSubTotal = collect($normalizedItems)->sum('total_price');

                $subTotal = $this->money(
                    $payload['sub_total'] ?? $calculatedSubTotal
                );
                $shippingCharge = $this->money($payload['shipping_charge'] ?? 0);
                $codCharge = $this->money($payload['cod_charge'] ?? 0);
                $totalAmount = $this->money(
                    $payload['total_amount'] ?? ($subTotal + $shippingCharge + $codCharge)
                );

                $sourceInvoice = trim((string) ($payload['source_invoice_id'] ?? ''));
                $sourceWebsiteName = trim((string) ($payload['source_website_name'] ?? $externalWebsite->name));
                $sourceNote = $sourceInvoice !== ''
                    ? "Received from {$sourceWebsiteName}. Source invoice: {$sourceInvoice}."
                    : "Received from {$sourceWebsiteName}.";

                $incomingAdminNote = trim((string) ($payload['admin_note'] ?? ''));
                $adminNote = $incomingAdminNote !== ''
                    ? $sourceNote . ' ' . $incomingAdminNote
                    : $sourceNote;

                $order = Order::query()->create([
                    'invoice_id' => $this->generateInvoiceId(),
                    'success_token' => Str::random(40),
                    'external_website_id' => $externalWebsite->id,
                    'external_order_id' => $externalOrderId,
                    'external_payload' => [
                        ...$payload,
                        '_integration' => [
                            'received_at' => now()->toIso8601String(),
                            'request_ip' => $requestIp,
                            'source_website_id' => $externalWebsite->id,
                            'source_website_slug' => $externalWebsite->slug,
                        ],
                    ],
                    'api_received_at' => now(),
                    'sync_uuid' => $incomingSyncUuid,
                    'customer_id' => $customer->id,
                    'created_via' => Order::CREATED_VIA_EXTERNAL_API,

                    'customer_name' => $customer->name,
                    'phone' => $customer->phone,
                    'address' => $payload['address'],
                    'delivery_area' => $payload['delivery_area'] ?? null,

                    'courier_service' => null,
                    'courier_account_id' => null,
                    'courier_id' => null,

                    'sub_total' => $subTotal,
                    'shipping_charge' => $shippingCharge,
                    'is_free_delivery' => $shippingCharge === 0,
                    'cod_charge' => $codCharge,
                    'total_amount' => $totalAmount,

                    'payment_method' => $this->normalizePaymentMethod(
                        $payload['payment_method'] ?? Order::PAYMENT_COD
                    ),
                    'payment_status' => $this->normalizePaymentStatus(
                        $payload['payment_status'] ?? Order::PAYMENT_STATUS_COD_PENDING
                    ),
                    'order_status' => $this->normalizeOrderStatus(
                        $payload['order_status'] ?? Order::STATUS_PROCESSING
                    ),

                    'is_fake' => false,
                    'admin_note' => $adminNote,
                    'customer_note' => $payload['customer_note'] ?? null,

                    'source_ip' => $payload['source_ip'] ?? null,
                    'user_agent' => $payload['user_agent'] ?? null,
                    'source_url' => $payload['source_url'] ?? $externalWebsite->domain,
                ]);

                foreach ($normalizedItems as $item) {
                    OrderItem::query()->create([
                        'order_id' => $order->id,
                        'product_id' => null,
                        'product_name' => $item['product_name'],
                        'unit_price' => $item['unit_price'],
                        'quantity' => $item['quantity'],
                        'discount_amount' => $item['discount_amount'],
                        'total_price' => $item['total_price'],
                    ]);
                }

                $externalWebsite->forceFill([
                    'last_order_received_at' => now(),
                ])->saveQuietly();

                return [
                    'order' => $order->fresh([
                        'externalWebsite',
                        'assignedEmployee',
                        'items',
                    ]),
                    'created' => true,
                ];
            });
        } catch (QueryException $exception) {
            $existingOrder = $this->findExistingOrder(
                $externalWebsite,
                $externalOrderId,
                $incomingSyncUuid
            );

            if ($existingOrder) {
                return [
                    'order' => $existingOrder,
                    'created' => false,
                ];
            }

            throw $exception;
        }
    }

    private function findExistingOrder(
        ExternalWebsite $externalWebsite,
        string $externalOrderId,
        string $syncUuid
    ): ?Order {
        return Order::query()
            ->where(function ($query) use ($externalWebsite, $externalOrderId, $syncUuid): void {
                $query->where(function ($externalQuery) use ($externalWebsite, $externalOrderId): void {
                    $externalQuery
                        ->where('external_website_id', $externalWebsite->id)
                        ->where('external_order_id', $externalOrderId);
                });

                if ($syncUuid !== '') {
                    $query->orWhere('sync_uuid', $syncUuid);
                }
            })
            ->first();
    }

    private function resolveIncomingSyncUuid(array $payload, string $externalOrderId): string
    {
        $candidate = trim((string) ($payload['sync_uuid'] ?? $externalOrderId));

        return Str::isUuid($candidate)
            ? $candidate
            : (string) Str::uuid();
    }

    private function normalizeItems(array $items): array
    {
        return collect($items)
            ->map(function (array $item): array {
                $quantity = max(1, (int) $item['quantity']);
                $unitPrice = $this->money($item['unit_price']);
                $discountAmount = min(
                    $this->money($item['discount_amount'] ?? 0),
                    $unitPrice * $quantity
                );
                $totalPrice = array_key_exists('total_price', $item)
                    && $item['total_price'] !== null
                    ? $this->money($item['total_price'])
                    : max(0, ($unitPrice * $quantity) - $discountAmount);

                return [
                    'product_name' => trim((string) $item['product_name']),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discountAmount,
                    'total_price' => $totalPrice,
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeOrderStatus(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        return match ($status) {
            'pending' => Order::STATUS_PENDING,
            'confirmed', 'complete', 'completed' => Order::STATUS_CONFIRMED,
            'complete_invoice', 'invoice_complete' => Order::STATUS_COMPLETE_INVOICE,
            'shipped', 'dispatched' => Order::STATUS_SHIPPED,
            'delivered' => Order::STATUS_DELIVERED,
            'cancelled', 'canceled' => Order::STATUS_CANCELLED,
            'fake' => Order::STATUS_FAKE,
            'stock_out', 'stockout' => Order::STATUS_STOCK_OUT,
            default => Order::STATUS_PROCESSING,
        };
    }

    private function normalizePaymentStatus(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        return match ($status) {
            'collected', 'paid', 'success', 'successful' => Order::PAYMENT_STATUS_COLLECTED,
            'failed', 'declined' => Order::PAYMENT_STATUS_FAILED,
            'unpaid' => Order::PAYMENT_STATUS_UNPAID,
            default => Order::PAYMENT_STATUS_COD_PENDING,
        };
    }

    private function normalizePaymentMethod(?string $method): string
    {
        $method = strtolower(trim((string) $method));

        if ($method === '') {
            return Order::PAYMENT_COD;
        }

        return Str::limit($method, 100, '');
    }

    private function money(mixed $value): int
    {
        return max(0, (int) round((float) $value));
    }

    private function generateInvoiceId(): string
    {
        do {
            $invoiceId = 'EXT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Order::query()->where('invoice_id', $invoiceId)->exists());

        return $invoiceId;
    }
}
