<?php

namespace App\Services;

use App\Models\CourierAccount;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SteadfastStatusService
{
    public const PENDING_STATUSES = [
        'pending',
        'hold',
        'in_review',
        'delivered_approval_pending',
        'partial_delivered_approval_pending',
        'unknown_approval_pending',
        'unknown',
    ];

    public const DELIVERED_STATUSES = [
        'delivered',
        'partial_delivered',
    ];

    public const CANCELLED_STATUSES = [
        'cancelled_approval_pending',
        'cancelled',
    ];

    public const FINAL_CANCELLED_STATUSES = [
        'cancelled',
    ];

    public function normalize(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        if ($status === '') {
            return '';
        }

        $normalized = trim((string) preg_replace('/[^a-z0-9]+/', '_', $status), '_');

        $aliases = [
            'inreview' => 'in_review',
            'in_review' => 'in_review',
            'on_hold' => 'hold',
            'canceled' => 'cancelled',
            'cancel' => 'cancelled',
            'partially_delivered' => 'partial_delivered',
            'partial_delivery' => 'partial_delivered',
        ];

        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        if (
            str_contains($normalized, 'cancel')
            && str_contains($normalized, 'approval')
            && str_contains($normalized, 'pending')
        ) {
            return 'cancelled_approval_pending';
        }

        if (
            str_contains($normalized, 'partial')
            && str_contains($normalized, 'deliver')
            && str_contains($normalized, 'approval')
            && str_contains($normalized, 'pending')
        ) {
            return 'partial_delivered_approval_pending';
        }

        if (
            str_contains($normalized, 'deliver')
            && str_contains($normalized, 'approval')
            && str_contains($normalized, 'pending')
        ) {
            return 'delivered_approval_pending';
        }

        if (str_contains($normalized, 'partial') && str_contains($normalized, 'deliver')) {
            return 'partial_delivered';
        }

        if (str_contains($normalized, 'cancel')) {
            return 'cancelled';
        }

        if (str_contains($normalized, 'deliver')) {
            return 'delivered';
        }

        if (str_contains($normalized, 'review')) {
            return 'in_review';
        }

        if (str_contains($normalized, 'hold')) {
            return 'hold';
        }

        if (str_contains($normalized, 'pending')) {
            return 'pending';
        }

        if (str_contains($normalized, 'unknown')) {
            return str_contains($normalized, 'approval')
                ? 'unknown_approval_pending'
                : 'unknown';
        }

        return $normalized;
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
        ?string $status,
        array $payload,
        string $source = 'api'
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
        ) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            $payloadConsignmentId = data_get($payload, 'consignment_id')
                ?: data_get($payload, 'consignment.consignment_id')
                ?: data_get($payload, 'data.consignment_id');

            $payloadTrackingCode = data_get($payload, 'tracking_code')
                ?: data_get($payload, 'consignment.tracking_code')
                ?: data_get($payload, 'data.tracking_code');

            $updateData = [
                'courier_account_id' => $lockedOrder->courier_account_id ?: $courierAccount->id,
                'courier_service' => 'steadfast',
                'steadfast_consignment_id' => $lockedOrder->steadfast_consignment_id ?: $payloadConsignmentId,
                'steadfast_tracking_code' => $lockedOrder->steadfast_tracking_code ?: $payloadTrackingCode,
                'steadfast_status' => $normalizedStatus ?: null,
                'steadfast_response' => $payload,
                'steadfast_note' => $this->statusNote($normalizedStatus, $source),
                'steadfast_sent_at' => $lockedOrder->steadfast_sent_at ?: now(),
                'steadfast_synced_at' => now(),
            ];

            if ($this->autoUpdateEnabled($courierAccount)) {
                if ($category === 'delivered') {
                    $updateData['order_status'] = Order::STATUS_DELIVERED;
                    $updateData['delivered_at'] = $lockedOrder->delivered_at ?: now();
                    $updateData['cancelled_at'] = null;
                    $updateData['custom_order_list'] = null;
                    $updateData['is_fake'] = false;
                    $updateData['marked_fake_at'] = null;

                    if (Schema::hasColumn('orders', 'custom_order_list_moved_at')) {
                        $updateData['custom_order_list_moved_at'] = null;
                    }
                }

                if (
                    $category === 'cancelled'
                    && ! in_array($lockedOrder->order_status, [
                        Order::STATUS_DELIVERED,
                        Order::STATUS_CANCELLED,
                        Order::STATUS_CANCELED,
                    ], true)
                ) {
                    $updateData['order_status'] = Order::STATUS_COURIER_CANCELLED;
                }

                if (
                    $category === 'pending'
                    && ! in_array($lockedOrder->order_status, [
                        Order::STATUS_DELIVERED,
                        Order::STATUS_CANCELLED,
                        Order::STATUS_CANCELED,
                    ], true)
                ) {
                    $updateData['order_status'] = Order::STATUS_COURIER_PENDING;
                }
            }

            $lockedOrder->update($updateData);

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

        return 'SteadFast status synced from ' . $source . ': ' . $label . '.';
    }
}
