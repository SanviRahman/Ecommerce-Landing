<?php

namespace App\Services;

use App\Models\FakeOrderLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderManagementService
{
    public function __construct(
        protected DynamicPricingService $pricingService,
        protected FakeOrderDetectionService $fakeOrderDetectionService
    ) {}

    public function createOrder(array $data, Request $request): Order
    {
        return DB::transaction(function () use ($data, $request) {
            $product = Product::where('status', true)->findOrFail($data['product_id']);

            $pricing = $this->pricingService->calculate(
                $product,
                (int) ($data['quantity'] ?? 1),
                $data['shipping_charge_id'] ?? null
            );

            if (!$product->isInStock($pricing['quantity'])) {
                throw new Exception('Product stock is not available.');
            }

            $fakeCheck = $this->fakeOrderDetectionService->detect([
                'customer_name' => $data['customer_name'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'quantity' => $pricing['quantity'],
            ], $request);

            $order = Order::create([
                'invoice_id' => $this->generateInvoiceId(),
                'campaign_id' => $data['campaign_id'] ?? null,
                'customer_name' => $data['customer_name'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'delivery_area' => $data['delivery_area'] ?? null,
                'sub_total' => $pricing['sub_total'],
                'shipping_charge' => $pricing['shipping_charge'],
                'cod_charge' => $pricing['cod_charge'],
                'total_amount' => $pricing['total_amount'],
                'payment_method' => Order::PAYMENT_COD,
                'payment_status' => Order::PAYMENT_STATUS_COD_PENDING,
                'order_status' => $fakeCheck['is_fake'] ? Order::STATUS_FAKE : Order::STATUS_PENDING,
                'is_fake' => $fakeCheck['is_fake'],
                'customer_note' => $data['customer_note'] ?? null,
                'source_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'source_url' => $request->headers->get('referer'),
                'marked_fake_at' => $fakeCheck['is_fake'] ? now() : null,
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $pricing['quantity'],
                'unit_price' => $pricing['unit_price'],
                'total_price' => $pricing['sub_total'],
            ]);

            if ($fakeCheck['is_fake']) {
                FakeOrderLog::create([
                    'order_id' => $order->id,
                    'fake_reason' => $fakeCheck['reason_text'],
                    'detected_by' => 'system',
                ]);
            } else {
                $product->decrement('stock', $pricing['quantity']);
                $product->increment('sold_quantity', $pricing['quantity']);
            }

            return $order->load('items', 'campaign');
        });
    }

    protected function generateInvoiceId(): string
    {
        do {
            $invoice = 'INV-' . date('ymd') . '-' . random_int(10000, 99999);
        } while (Order::withTrashed()->where('invoice_id', $invoice)->exists());

        return $invoice;
    }
}