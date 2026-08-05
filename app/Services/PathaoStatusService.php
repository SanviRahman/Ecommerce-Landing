<?php

namespace App\Services;

use App\Models\CourierAccount;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PathaoStatusService
{
    public const PENDING_STATUSES = [
        'order_created',
        'order_updated',
        'pickup_requested',
        'assigned_for_pickup',
        'picked',
        'at_the_sorting_hub',
        'in_transit',
        'received_at_last_mile_hub',
        'assigned_for_delivery',
        'on_hold',
        'exchange',
        'payment_invoice',
        'pending',
    ];

    public const DELIVERED_STATUSES = [
        'delivered',
        'partial_delivery',
    ];

    public const CANCELLED_STATUSES = [
        'pickup_failed',
        'pickup_cancelled',
        'return',
        'returned',
        'return_id_created',
        'return_in_transit',
        'returned_to_merchant',
        'delivery_failed',
        'paid_return',
        'cancelled',
    ];

    public const FINAL_CANCELLED_STATUSES = self::CANCELLED_STATUSES;

    public function normalize(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        if ($status === '') {
            return '';
        }

        $normalized = trim((string) preg_replace('/[^a-z0-9]+/', '_', $status), '_');

        $aliases = [
            'created' => 'order_created',
            'order_created' => 'order_created',
            'updated' => 'order_updated',
            'order_updated' => 'order_updated',
            'pickup_request' => 'pickup_requested',
            'pickup_requested' => 'pickup_requested',
            'assigned_for_pickup' => 'assigned_for_pickup',
            'pickup' => 'picked',
            'picked_up' => 'picked',
            'picked' => 'picked',
            'pickup_failed' => 'pickup_failed',
            'pickup_cancelled' => 'pickup_cancelled',
            'pickup_canceled' => 'pickup_cancelled',
            'at_the_sorting_hub' => 'at_the_sorting_hub',
            'sorting_hub' => 'at_the_sorting_hub',
            'in_transit' => 'in_transit',
            'received_at_last_mile_hub' => 'received_at_last_mile_hub',
            'assigned_for_delivery' => 'assigned_for_delivery',
            'delivered' => 'delivered',
            'partial_delivered' => 'partial_delivery',
            'partial_delivery' => 'partial_delivery',
            'returned' => 'returned',
            'return' => 'return',
            'return_id_created' => 'return_id_created',
            'return_in_transit' => 'return_in_transit',
            'returned_to_merchant' => 'returned_to_merchant',
            'delivery_failed' => 'delivery_failed',
            'on_hold' => 'on_hold',
            'hold' => 'on_hold',
            'paid_return' => 'paid_return',
            'exchanged' => 'exchange',
            'exchange' => 'exchange',
            'paid' => 'payment_invoice',
            'payment_invoice' => 'payment_invoice',
            'canceled' => 'cancelled',
            'cancelled' => 'cancelled',
        ];

        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        if (str_contains($normalized, 'return') && str_contains($normalized, 'merchant')) {
            return 'returned_to_merchant';
        }

        if (
            str_contains($normalized, 'return')
            && str_contains($normalized, 'transit')
        ) {
            return 'return_in_transit';
        }

        if (
            str_contains($normalized, 'return')
            && str_contains($normalized, 'id')
            && str_contains($normalized, 'create')
        ) {
            return 'return_id_created';
        }

        if (str_contains($normalized, 'partial') && str_contains($normalized, 'deliver')) {
            return 'partial_delivery';
        }

        if (str_contains($normalized, 'deliver') && ! str_contains($normalized, 'fail')) {
            return 'delivered';
        }

        if (str_contains($normalized, 'pickup') && str_contains($normalized, 'cancel')) {
            return 'pickup_cancelled';
        }

        if (str_contains($normalized, 'pickup') && str_contains($normalized, 'fail')) {
            return 'pickup_failed';
        }

        if (str_contains($normalized, 'delivery') && str_contains($normalized, 'fail')) {
            return 'delivery_failed';
        }

        if (str_contains($normalized, 'return')) {
            return str_contains($normalized, 'paid') ? 'paid_return' : 'returned';
        }

        if (str_contains($normalized, 'cancel')) {
            return 'cancelled';
        }

        if (str_contains($normalized, 'transit')) {
            return 'in_transit';
        }

        if (str_contains($normalized, 'hold')) {
            return 'on_hold';
        }

        if (str_contains($normalized, 'pending')) {
            return 'pending';
        }

        return $normalized;
    }

    public function fromEvent(?string $event): string
    {
        $event = strtolower(trim((string) $event));

        $map = [
            'order.created' => 'order_created',
            'order.updated' => 'order_updated',
            'order.pickup-requested' => 'pickup_requested',
            'order.assigned-for-pickup' => 'assigned_for_pickup',
            'order.pickup' => 'picked',
            'order.picked' => 'picked',
            'order.pickup-failed' => 'pickup_failed',
            'order.pickup-cancelled' => 'pickup_cancelled',
            'order.pickup-canceled' => 'pickup_cancelled',
            'order.at-the-sorting-hub' => 'at_the_sorting_hub',
            'order.in-transit' => 'in_transit',
            'order.received-at-last-mile-hub' => 'received_at_last_mile_hub',
            'order.assigned-for-delivery' => 'assigned_for_delivery',
            'order.delivered' => 'delivered',
            'order.partial-delivery' => 'partial_delivery',
            'order.return' => 'return',
            'order.returned' => 'returned',
            'order.return-id-created' => 'return_id_created',
            'order.return-in-transit' => 'return_in_transit',
            'order.returned-to-merchant' => 'returned_to_merchant',
            'order.delivery-failed' => 'delivery_failed',
            'order.on-hold' => 'on_hold',
            'order.paid-return' => 'paid_return',
            'order.exchanged' => 'exchange',
            'order.paid' => 'payment_invoice',
            'order.payment-invoice' => 'payment_invoice',
        ];

        return $map[$event] ?? $this->normalize($event);
    }

    public function statusFromPayload(array $payload): string
    {
        foreach ([
            data_get($payload, 'data.order_status'),
            data_get($payload, 'order_status'),
            data_get($payload, 'data.order.status'),
            data_get($payload, 'order.status'),
            data_get($payload, 'data.delivery_status'),
            data_get($payload, 'delivery_status'),
            data_get($payload, 'data.status'),
            data_get($payload, 'status'),
        ] as $status) {
            if (! is_string($status) || trim($status) === '') {
                continue;
            }

            $normalized = $this->normalize($status);

            if (! in_array($normalized, ['success', 'successful', 'ok'], true)) {
                return $normalized;
            }
        }

        return $this->fromEvent((string) (
            data_get($payload, 'event')
                ?: data_get($payload, 'data.event')
        ));
    }

    public function category(?string $status): string
    {
        $status = $this->normalize($status);

        if (in_array($status, self::DELIVERED_STATUSES, true)) {
            return 'delivered';
        }

        if (in_array($status, self::CANCELLED_STATUSES, true)) {
            return 'cancelled';
        }

        return 'pending';
    }

    public function autoUpdateEnabled(CourierAccount $courierAccount): bool
    {
        return (bool) $courierAccount->setting('auto_update_order_status', true);
    }

    public function statusSyncEnabled(CourierAccount $courierAccount): bool
    {
        return (bool) $courierAccount->setting('status_sync_enabled', true);
    }

    public function syncIntervalMinutes(CourierAccount $courierAccount): int
    {
        return min(
            max((int) $courierAccount->setting('status_sync_interval_minutes', 15), 5),
            1440
        );
    }

    public function apply(
        Order $order,
        CourierAccount $courierAccount,
        string $status,
        array $payload,
        string $source = 'webhook'
    ): Order {
        $normalizedStatus = $this->normalize($status);
        $category = $this->category($normalizedStatus);

        return DB::transaction(function () use (
            $order,
            $courierAccount,
            $normalizedStatus,
            $category,
            $payload,
            $source
        ): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            $updates = [
                'courier_account_id' => $lockedOrder->courier_account_id ?: $courierAccount->id,
                'courier_service' => 'pathao',
                'pathao_status' => $normalizedStatus ?: null,
                'pathao_consignment_id' => $this->scalar(
                    data_get($payload, 'consignment_id')
                        ?: data_get($payload, 'data.consignment_id')
                        ?: data_get($payload, 'data.order.consignment_id')
                        ?: $lockedOrder->pathao_consignment_id
                ),
                'pathao_merchant_order_id' => $this->scalar(
                    data_get($payload, 'merchant_order_id')
                        ?: data_get($payload, 'data.merchant_order_id')
                        ?: data_get($payload, 'data.order.merchant_order_id')
                        ?: $lockedOrder->pathao_merchant_order_id
                        ?: $lockedOrder->invoice_id
                ),
                'pathao_note' => $this->statusNote($normalizedStatus, $source),
                'pathao_response' => $payload,
                'pathao_sent_at' => $lockedOrder->pathao_sent_at ?: now(),
                'pathao_synced_at' => now(),
            ];

            $deliveryFee = data_get($payload, 'delivery_fee')
                ?? data_get($payload, 'data.delivery_fee')
                ?? data_get($payload, 'data.order.delivery_fee');

            if (is_numeric($deliveryFee)) {
                $updates['pathao_delivery_fee'] = (float) $deliveryFee;
            }

            if ($this->autoUpdateEnabled($courierAccount)) {
                if ($category === 'delivered') {
                    $updates['order_status'] = Order::STATUS_DELIVERED;
                    $updates['delivered_at'] = $lockedOrder->delivered_at ?: now();
                    $updates['cancelled_at'] = null;
                    $updates['custom_order_list'] = null;
                    $updates['is_fake'] = false;
                    $updates['marked_fake_at'] = null;

                    if (Schema::hasColumn('orders', 'custom_order_list_moved_at')) {
                        $updates['custom_order_list_moved_at'] = null;
                    }
                }

                /*
                 * Courier cancellation is stored only in pathao_status.
                 * Local Cancelled Orders are reserved for explicit
                 * Admin/Employee status changes.
                 */
            }

            $lockedOrder->update($updates);

            return $lockedOrder->fresh([
                'courierAccount',
                'courier',
                'items.product',
            ]);
        });
    }

    private function statusNote(string $status, string $source): string
    {
        $label = $status !== ''
            ? ucwords(str_replace('_', ' ', $status))
            : 'Unknown';

        return 'Pathao status synced from ' . $source . ': ' . $label . '.';
    }

    private function scalar(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_scalar($value) ? trim((string) $value) : null;
    }
}
