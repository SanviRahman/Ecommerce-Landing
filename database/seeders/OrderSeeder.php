<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\CourierAccount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::query()
            ->where('status', true)
            ->get();

        if ($products->isEmpty()) {
            $this->command?->warn('No active product found. Please seed products first.');
            return;
        }

        $campaignIds = Campaign::query()
            ->where('status', true)
            ->pluck('id')
            ->toArray();

        $courierAccounts = CourierAccount::query()
            ->active()
            ->whereIn('code', ['steadfast', 'pathao'])
            ->get();

        $orderStatuses = [
            'pending',
            'confirmed',
            'processing',
            'shipped',
            'delivered',
            'cancelled',
            'fake',
        ];

        $paymentStatuses = [
            'cod_pending',
            'collected',
            'failed',
            'unpaid',
        ];

        for ($i = 1; $i <= 20; $i++) {
            $selectedProducts = $products
                ->random(min(random_int(1, 3), $products->count()))
                ->values();

            $subTotal = 0;
            $orderItems = [];

            foreach ($selectedProducts as $product) {
                $quantity = random_int(1, 3);
                $unitPrice = (int) ($product->new_price ?? 0);
                $lineTotal = $unitPrice * $quantity;

                $subTotal += $lineTotal;

                $orderItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'is_free_delivery' => (bool) $product->is_free_delivery,
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | Free Delivery Logic
            |--------------------------------------------------------------------------
            | যদি order-এর সব product free delivery হয়, shipping charge 0 হবে।
            | Mixed / non-free product থাকলে delivery charge হবে।
            */
            $allItemsFreeDelivery = collect($orderItems)->every(function ($item) {
                return (bool) $item['is_free_delivery'];
            });

            $deliveryArea = random_int(0, 1)
                ? 'inside_dhaka'
                : 'outside_dhaka';

            $shippingCharge = $allItemsFreeDelivery
                ? 0
                : ($deliveryArea === 'inside_dhaka' ? 80 : 150);

            $codCharge = 0;
            $totalAmount = $subTotal + $shippingCharge + $codCharge;

            $orderStatus = $orderStatuses[array_rand($orderStatuses)];
            $paymentStatus = $paymentStatuses[array_rand($paymentStatuses)];
            $isFake = $orderStatus === 'fake';

            /*
            |--------------------------------------------------------------------------
            | Courier Default Logic
            |--------------------------------------------------------------------------
            | কিছু order No Courier থাকবে, কিছু order courier selected থাকবে।
            | User panel order default null হবে, admin পরে select করবে।
            */
            $courier = null;

            if ($courierAccounts->isNotEmpty() && random_int(1, 100) <= 45) {
                $courier = $courierAccounts->random();
            }

            $order = Order::create([
                'invoice_id' => $this->generateInvoiceId(),

                'campaign_id' => ! empty($campaignIds)
                    ? $campaignIds[array_rand($campaignIds)]
                    : null,

                'customer_name' => 'Customer ' . $i,
                'phone' => '01711' . random_int(100000, 999999),
                'address' => 'Dhaka, Bangladesh',

                'delivery_area' => $deliveryArea,

                'courier_service' => $courier?->code,
                'courier_account_id' => $courier?->id,

                'sub_total' => $subTotal,
                'shipping_charge' => $shippingCharge,
                'is_free_delivery' => $allItemsFreeDelivery,
                'cod_charge' => $codCharge,
                'total_amount' => $totalAmount,

                'payment_method' => 'cash_on_delivery',
                'payment_status' => $paymentStatus,

                'order_status' => $orderStatus,

                'is_fake' => $isFake,

                'admin_note' => $isFake
                    ? 'This order looks suspicious.'
                    : null,

                'customer_note' => 'Seed order for testing',

                'source_ip' => '127.0.0.1',
                'user_agent' => 'Seeder',
                'source_url' => url('/'),

                'confirmed_at' => in_array($orderStatus, [
                    'confirmed',
                    'processing',
                    'shipped',
                    'delivered',
                ], true) ? now()->subDays(random_int(1, 5)) : null,

                'delivered_at' => $orderStatus === 'delivered'
                    ? now()->subDays(random_int(0, 3))
                    : null,

                'cancelled_at' => $orderStatus === 'cancelled'
                    ? now()->subDays(random_int(0, 3))
                    : null,

                'marked_fake_at' => $isFake
                    ? now()->subDays(random_int(0, 3))
                    : null,
            ]);

            foreach ($orderItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'product_name' => $item['product']->name,
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'total_price' => $item['line_total'],
                ]);
            }
        }

        $this->command?->info('Orders seeded successfully with dynamic courier and free delivery logic.');
    }

    private function generateInvoiceId(): string
    {
        do {
            $invoiceId = 'INV-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Order::where('invoice_id', $invoiceId)->exists());

        return $invoiceId;
    }
}