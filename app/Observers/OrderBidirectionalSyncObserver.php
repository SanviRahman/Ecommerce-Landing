<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\ExternalOrderSyncService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Throwable;

class OrderBidirectionalSyncObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Order $order): void
    {
        if ($order->created_via === Order::CREATED_VIA_EXTERNAL_API) {
            return;
        }

        try {
            app(ExternalOrderSyncService::class)->syncNewOrder($order);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function updated(Order $order): void
    {
        if ($order->created_via === Order::CREATED_VIA_EXTERNAL_API) {
            return;
        }

        if (! $order->wasChanged([
            'order_status',
            'payment_status',
            'customer_name',
            'phone',
            'address',
            'delivery_area',
            'sub_total',
            'shipping_charge',
            'cod_charge',
            'total_amount',
            'customer_note',
            'admin_note',
            'shipped_at',
            'delivered_at',
            'cancelled_at',
        ])) {
            return;
        }

        try {
            app(ExternalOrderSyncService::class)->syncUpdatedOrder($order);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
