<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Str;

class ExternalOrderPayloadBuilder
{
    public function build(Order $order): array
    {
        $order->loadMissing('items');

        if (! $order->sync_uuid) {
            $order->forceFill([
                'sync_uuid' => (string) Str::uuid(),
            ])->saveQuietly();
        }

        return [
            'external_order_id' => $order->sync_uuid,
            'sync_uuid' => $order->sync_uuid,
            'source_order_id' => (string) $order->id,
            'source_invoice_id' => (string) $order->invoice_id,
            'source_website_name' => (string) config('app.name'),
            'source_website_domain' => rtrim((string) config('app.url'), '/'),

            'customer_name' => $order->customer_name,
            'phone' => $order->phone,
            'address' => $order->address,
            'delivery_area' => $order->delivery_area,

            'sub_total' => (float) $order->sub_total,
            'shipping_charge' => (float) $order->shipping_charge,
            'cod_charge' => (float) $order->cod_charge,
            'total_amount' => (float) $order->total_amount,

            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'order_status' => $order->order_status,
            'customer_note' => $order->customer_note,
            'admin_note' => $order->admin_note,

            'source_ip' => $order->source_ip,
            'user_agent' => $order->user_agent,
            'source_url' => $order->source_url,
            'ordered_at' => $order->created_at?->toIso8601String(),

            'items' => $order->items
                ->map(fn ($item): array => [
                    'source_product_id' => $item->product_id
                        ? (string) $item->product_id
                        : null,
                    'product_name' => $item->product_name,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'discount_amount' => (float) ($item->discount_amount ?? 0),
                    'total_price' => (float) $item->total_price,
                ])
                ->values()
                ->all(),
        ];
    }
}
