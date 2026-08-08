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
}
