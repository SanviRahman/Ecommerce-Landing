<?php

namespace App\Services;

use App\Models\ExternalWebsite;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

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

        /*
         * Resolve and validate the source business timeline before touching the
         * database. The receiver must never use API receive time as order time.
         */
        $timeline = $this->sourceTimeline($payload);

        $existingOrder = $this->findExistingOrder(
            $externalWebsite,
            $externalOrderId,
            $incomingSyncUuid
        );

        if ($existingOrder) {
            $this->refreshExistingOrderMetadata(
                $existingOrder,
                $payload,
                $requestIp,
                $timeline
            );

            return [
                'order' => $existingOrder->fresh([
                    'externalWebsite',
                    'assignedEmployee',
                    'items',
                ]),
                'created' => false,
            ];
        }

        try {
            return DB::transaction(function () use (
                $externalWebsite,
                $payload,
                $requestIp,
                $externalOrderId,
                $incomingSyncUuid,
                $timeline
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

                $order = new Order();

                $order->forceFill([
                    'invoice_id' => $this->generateInvoiceId(),
                    'success_token' => Str::random(40),
                    'external_website_id' => $externalWebsite->id,
                    'external_order_id' => $externalOrderId,
                    'external_payload' => [
                        ...$payload,
                        '_integration' => [
                            'received_at' => now()->utc()->toIso8601String(),
                            'request_ip' => $requestIp,
                            'source_website_id' => $externalWebsite->id,
                            'source_website_slug' => $externalWebsite->slug,
                            'source_timeline_verified' => true,
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

                    /*
                     * Set source lifecycle values before the model creating event.
                     * This prevents workflow hooks from stamping historical API
                     * orders with the receiver's current time.
                     */
                    'confirmed_at' => $timeline['confirmed_at'],
                    'shipped_at' => $timeline['shipped_at'],
                    'delivered_at' => $timeline['delivered_at'],
                    'cancelled_at' => $timeline['cancelled_at'],
                    'invoice_printed_at' => $timeline['invoice_printed_at'],
                    'invoice_print_count' => $timeline['invoice_print_count'],
                ]);

                if (Schema::hasColumn('orders', 'source_ordered_at')) {
                    $order->source_ordered_at = $timeline['created_at'];
                }

                $order->created_at = $timeline['created_at'];
                $order->updated_at = $timeline['updated_at'];
                $order->save();

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

                /*
                 * Re-apply after create as a final guard in case a model hook
                 * touched any workflow timestamp while the row was being saved.
                 */
                $this->applySourceTimeline($order, $timeline);

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
                $this->refreshExistingOrderMetadata(
                    $existingOrder,
                    $payload,
                    $requestIp,
                    $timeline
                );

                return [
                    'order' => $existingOrder->fresh([
                        'externalWebsite',
                        'assignedEmployee',
                        'items',
                    ]),
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

    private function refreshExistingOrderMetadata(
        Order $order,
        array $payload,
        string $requestIp,
        array $timeline
    ): void {
        $existingPayload = is_array($order->external_payload)
            ? $order->external_payload
            : [];

        $integration = is_array($existingPayload['_integration'] ?? null)
            ? $existingPayload['_integration']
            : [];

        $integration['refreshed_at'] = now()->utc()->toIso8601String();
        $integration['last_request_ip'] = $requestIp;
        $integration['source_timeline_verified'] = true;

        $updates = [
            'external_payload' => [
                ...$payload,
                '_integration' => $integration,
            ],
        ];

        if (array_key_exists('order_status', $payload)) {
            $updates['order_status'] = $this->normalizeOrderStatus($payload['order_status']);
        }

        if (array_key_exists('payment_status', $payload)) {
            $updates['payment_status'] = $this->normalizePaymentStatus($payload['payment_status']);
        }

        if (array_key_exists('payment_method', $payload)) {
            $updates['payment_method'] = $this->normalizePaymentMethod($payload['payment_method']);
        }

        $order->forceFill($updates)->saveQuietly();
        $this->applySourceTimeline($order, $timeline);
    }

    private function sourceTimeline(array $payload): array
    {
        $orderedAt = $this->parseDate($payload['ordered_at'] ?? null);

        if (! $orderedAt) {
            throw new RuntimeException(
                'External order rejected because ordered_at is missing or invalid.'
            );
        }

        $sourceUpdatedAt = $this->parseDate($payload['source_updated_at'] ?? null)
            ?? $orderedAt->copy();

        if ($sourceUpdatedAt->lessThan($orderedAt)) {
            throw new RuntimeException(
                'External order rejected because source_updated_at is earlier than ordered_at.'
            );
        }

        $status = $this->normalizeOrderStatus(
            $payload['order_status'] ?? Order::STATUS_PROCESSING
        );

        $confirmedAt = $this->parseDate($payload['confirmed_at'] ?? null);
        $shippedAt = $this->parseDate($payload['shipped_at'] ?? null);
        $deliveredAt = $this->parseDate($payload['delivered_at'] ?? null);
        $cancelledAt = $this->parseDate($payload['cancelled_at'] ?? null);
        $invoicePrintedAt = $this->parseDate($payload['invoice_printed_at'] ?? null);
        $invoicePrintCount = max(0, (int) ($payload['invoice_print_count'] ?? 0));

        $wasShipped = filter_var(
            $payload['was_shipped'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        /*
         * When an older source row does not contain a dedicated lifecycle
         * timestamp, use source_updated_at/ordered_at — never receiver now().
         */
        if ($status === Order::STATUS_CONFIRMED && ! $confirmedAt) {
            $confirmedAt = $sourceUpdatedAt->copy();
        }

        if ($wasShipped && ! $shippedAt) {
            $shippedAt = $sourceUpdatedAt->copy();
        }

        if ($status === Order::STATUS_DELIVERED && ! $deliveredAt) {
            $deliveredAt = $sourceUpdatedAt->copy();
        }

        if (
            in_array($status, [Order::STATUS_CANCELLED, Order::STATUS_CANCELED], true)
            && ! $cancelledAt
        ) {
            $cancelledAt = $sourceUpdatedAt->copy();
        }

        if ($status === Order::STATUS_COMPLETE_INVOICE && ! $invoicePrintedAt) {
            $invoicePrintedAt = $sourceUpdatedAt->copy();
            $invoicePrintCount = max(1, $invoicePrintCount);
        }

        return [
            'created_at' => $orderedAt,
            'updated_at' => $sourceUpdatedAt,
            'confirmed_at' => $confirmedAt,
            'shipped_at' => $wasShipped ? $shippedAt : null,
            'delivered_at' => $deliveredAt,
            'cancelled_at' => $cancelledAt,
            'invoice_printed_at' => $invoicePrintedAt,
            'invoice_print_count' => $invoicePrintCount,
        ];
    }

    private function applySourceTimeline(Order $order, array $timeline): void
    {
        $order->forceFill([
            'confirmed_at' => $timeline['confirmed_at'],
            'shipped_at' => $timeline['shipped_at'],
            'delivered_at' => $timeline['delivered_at'],
            'cancelled_at' => $timeline['cancelled_at'],
            'invoice_printed_at' => $timeline['invoice_printed_at'],
            'invoice_print_count' => $timeline['invoice_print_count'],
        ]);

        if (Schema::hasColumn('orders', 'source_ordered_at')) {
            $order->source_ordered_at = $timeline['created_at'];
        }

        $order->created_at = $timeline['created_at'];
        $order->updated_at = $timeline['updated_at'];
        $order->saveQuietly();
    }

    private function parseDate(mixed $value): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
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
            'courier_pending' => Order::STATUS_COURIER_PENDING,
            'courier_cancelled', 'courier_canceled' => Order::STATUS_COURIER_CANCELLED,
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
