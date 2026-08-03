<?php

namespace App\Services;

use App\Models\CourierAccount;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

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
            'order.picked' => 'picked',
            'order.pickup-failed' => 'pickup_failed',
            'order.pickup-cancelled' => 'pickup_cancelled',
            'order.at-the-sorting-hub' => 'at_the_sorting_hub',
            'order.in-transit' => 'in_transit',
            'order.received-at-last-mile-hub' => 'received_at_last_mile_hub',
            'order.assigned-for-delivery' => 'assigned_for_delivery',
            'order.delivered' => 'delivered',
            'order.partial-delivery' => 'partial_delivery',
            'order.returned' => 'returned',
            'order.delivery-failed' => 'delivery_failed',
            'order.on-hold' => 'on_hold',
            'order.paid-return' => 'paid_return',
            'order.exchanged' => 'exchange',
            'order.paid' => 'payment_invoice',
        ];

        return $map[$event] ?? $this->normalize($event);
    }

    public function statusFromPayload(array $payload): string
    {
        $status = data_get($payload, 'order_status')
            ?: data_get($payload, 'status')
            ?: data_get($payload, 'data.order_status')
            ?: data_get($payload, 'data.status');

        $normalized = $this->normalize(is_scalar($status) ? (string) $status : '');

        if ($normalized !== '') {
            return $normalized;
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

    public function apply(
        Order $order,
        CourierAccount $courierAccount,
        string $status,
        array $payload,
        string $source = 'webhook'
    ): Order {
        $status = $this->normalize($status);
        $autoUpdate = (bool) data_get($courierAccount->settings ?? [], 'auto_update_order_status', true);

        return DB::transaction(function () use (
            $order,
            $courierAccount,
            $status,
            $payload,
            $source,
            $autoUpdate
        ): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            $updates = [
                'courier_account_id' => $courierAccount->id,
                'courier_service' => 'pathao',
                'pathao_status' => $status,
                'pathao_consignment_id' => $this->scalar(
                    data_get($payload, 'consignment_id')
                        ?: data_get($payload, 'data.consignment_id')
                        ?: $lockedOrder->pathao_consignment_id
                ),
                'pathao_merchant_order_id' => $this->scalar(
                    data_get($payload, 'merchant_order_id')
                        ?: data_get($payload, 'data.merchant_order_id')
                        ?: $lockedOrder->pathao_merchant_order_id
                        ?: $lockedOrder->invoice_id
                ),
                'pathao_note' => 'Pathao status updated from ' . $source . '.',
                'pathao_response' => $payload,
                'pathao_synced_at' => now(),
            ];

            $deliveryFee = data_get($payload, 'delivery_fee')
                ?? data_get($payload, 'data.delivery_fee');

            if (is_numeric($deliveryFee)) {
                $updates['pathao_delivery_fee'] = (float) $deliveryFee;
            }

            if ($autoUpdate && $lockedOrder->order_status !== Order::STATUS_DELIVERED) {
                if (in_array($status, self::DELIVERED_STATUSES, true)) {
                    $updates['order_status'] = Order::STATUS_DELIVERED;
                } elseif (in_array($status, self::FINAL_CANCELLED_STATUSES, true)) {
                    $updates['order_status'] = Order::STATUS_CANCELLED;
                }
            }

            $lockedOrder->update($updates);

            return $lockedOrder->fresh(['items', 'courierAccount']);
        });
    }

    private function scalar(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_scalar($value) ? trim((string) $value) : null;
    }
}
