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
        $order->loadMissing(['items.product']);

        $items = $order->items->map(function ($item) {
            $product = $item->product ?? null;

            return [
                'item_id'   => (string) ($item->product_id ?? $product?->id ?? ''),
                'item_name' => (string) ($product?->name ?? $item->product_name ?? $item->name ?? 'Product'),
                'price'     => (float) ($item->price ?? $item->unit_price ?? 0),
                'quantity'  => (int) ($item->quantity ?? 1),
            ];
        })->values()->toArray();

        return [
            'transaction_id' => (string) ($order->invoice_no ?? $order->invoice_number ?? $order->id),
            'affiliation'    => config('app.name') . ' Online Store',
            'currency'       => 'BDT',
            'value'          => (float) ($order->total_amount ?? $order->grand_total ?? 0),
            'shipping'       => (float) ($order->delivery_charge ?? $order->shipping_charge ?? 0),
            'tax'            => 0,
            'items'          => $items,
        ];
    }
}