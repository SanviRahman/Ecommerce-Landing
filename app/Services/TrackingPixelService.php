<?php

namespace App\Services;

use App\Models\Order;
use App\Models\TrackingPixel;

class TrackingPixelService
{
    public function activePixels()
    {
        return TrackingPixel::active()->get();
    }

    public function metaPixels()
    {
        return TrackingPixel::active()->meta()->get();
    }

    public function tiktokPixels()
    {
        return TrackingPixel::active()->tiktok()->get();
    }

    public function gtmPixels()
    {
        return TrackingPixel::active()->gtm()->get();
    }

    public function googleAnalyticsPixels()
    {
        return TrackingPixel::active()->googleAnalytics()->get();
    }

    public function purchasePayload(Order $order): array
    {
        $order->loadMissing('items');

        return [
            'value' => (int) $order->total_amount,
            'currency' => 'BDT',
            'content_ids' => $order->items->pluck('product_id')->filter()->values()->toArray(),
            'content_type' => 'product',
            'num_items' => $order->items->sum('quantity'),
        ];
    }
}