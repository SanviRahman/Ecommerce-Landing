<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SteadfastTestOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $campaign = Campaign::query()
            ->where('status', true)
            ->latest()
            ->first();

        $product = Product::query()
            ->where('status', true)
            ->latest()
            ->first();

        if (! $product) {
            $this->command?->warn('No active product found. Please seed product first.');
            return;
        }

        $quantity = 1;
        $unitPrice = (int) ($product->new_price ?? 0);
        $subTotal = $unitPrice * $quantity;
        $shippingCharge = 70;
        $codCharge = 0;
        $totalAmount = $subTotal + $shippingCharge + $codCharge;

        $order = Order::create([
            'invoice_id' => $this->generateInvoiceId(),

            'campaign_id' => $campaign?->id,

            'customer_name' => 'SteadFast Test Customer',
            'phone' => '01711111111',
            'address' => 'Mirpur 10, Dhaka, Bangladesh',

            'delivery_area' => 'inside_dhaka',
            'courier_service' => 'steadfast',

            'sub_total' => $subTotal,
            'shipping_charge' => $shippingCharge,
            'cod_charge' => $codCharge,
            'total_amount' => $totalAmount,

            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'cod_pending',
            'order_status' => 'pending',

            'is_fake' => false,

            'admin_note' => 'Seeder test order for SteadFast integration.',
            'customer_note' => 'Please call before delivery.',

            'source_ip' => '127.0.0.1',
            'user_agent' => 'Seeder',
            'source_url' => url('/'),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'total_price' => $subTotal,
        ]);

        $this->command?->info('SteadFast test order created: ' . $order->invoice_id);
    }

    private function generateInvoiceId(): string
    {
        do {
            $invoiceId = 'INV-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Order::where('invoice_id', $invoiceId)->exists());

        return $invoiceId;
    }
}