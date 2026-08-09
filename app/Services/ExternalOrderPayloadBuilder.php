<?php

namespace App\Services;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class ExternalOrderPayloadBuilder
{
    public const TIMELINE_VERSION = 2;

    public function build(Order $order): array
    {
        $order->loadMissing('items');

        if (! $order->sync_uuid) {
            $order->forceFill([
                'sync_uuid' => (string) Str::uuid(),
            ])->saveQuietly();
        }

        if (! $order->created_at) {
            throw new RuntimeException(
                'Order sync stopped because the source order has no created_at timestamp.'
            );
        }

        $sourceShippedAt = $this->sourceShippedAt($order);

        return [
            'external_order_id' => $order->sync_uuid,
            'sync_uuid' => $order->sync_uuid,
            'source_order_id' => (string) $order->id,
            'source_invoice_id' => (string) $order->invoice_id,
            'source_website_name' => (string) config('app.name'),
            'source_website_domain' => rtrim((string) config('app.url'), '/'),

            /*
             * Timeline contract version 2 makes the source business timeline
             * explicit. Receivers must never substitute API receive time for the
             * original order creation time because that corrupts daily reports.
             */
            'source_timeline_version' => self::TIMELINE_VERSION,
            'source_timezone' => method_exists(Order::class, 'displayTimezone')
                ? Order::displayTimezone()
                : (string) config('app.timezone', 'UTC'),

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

            'ordered_at' => $this->isoUtc($order->created_at),
            'source_updated_at' => $this->isoUtc($order->updated_at ?? $order->created_at),
            'confirmed_at' => $this->isoUtc($order->confirmed_at),
            'shipped_at' => $this->isoUtc($sourceShippedAt),
            'was_shipped' => $this->wasShipped($order),
            'delivered_at' => $this->isoUtc($order->delivered_at),
            'cancelled_at' => $this->isoUtc($order->cancelled_at),
            'invoice_printed_at' => $this->isoUtc($order->invoice_printed_at),
            'invoice_print_count' => (int) ($order->invoice_print_count ?? 0),

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

    private function wasShipped(Order $order): bool
    {
        if ($order->order_status === Order::STATUS_SHIPPED || $order->shipped_at) {
            return true;
        }

        if (
            $order->courier_service === 'steadfast'
            && (
                trim((string) $order->steadfast_consignment_id) !== ''
                || $order->steadfast_sent_at
            )
        ) {
            return true;
        }

        return $order->courier_service === 'pathao'
            && (
                trim((string) $order->pathao_consignment_id) !== ''
                || $order->pathao_sent_at
            );
    }

    private function sourceShippedAt(Order $order): ?Carbon
    {
        if (! $this->wasShipped($order)) {
            return null;
        }

        return $order->shipped_at
            ?? $order->steadfast_sent_at
            ?? $order->pathao_sent_at
            ?? $order->updated_at
            ?? $order->created_at;
    }

    private function isoUtc(?CarbonInterface $value): ?string
    {
        if (! $value) {
            return null;
        }

        return Carbon::instance($value)
            ->utc()
            ->toIso8601String();
    }
}
