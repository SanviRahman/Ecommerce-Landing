<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Order;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $subTotal = random_int(500, 5000);
            $shippingCharge = random_int(0, 1) ? 70 : 130;
            $codCharge = 0;

            Order::create([
                'invoice_id' => $this->generateInvoiceId(),

                // campaign_id nullable, campaign na thakle null thakbe
                'campaign_id' => Campaign::query()->inRandomOrder()->value('id'),

                'customer_name' => 'Customer ' . $i,
                'phone' => '01711' . random_int(100000, 999999),
                'address' => 'Dhaka, Bangladesh',
                'delivery_area' => $shippingCharge === 70 ? 'inside_dhaka' : 'outside_dhaka',

                'sub_total' => $subTotal,
                'shipping_charge' => $shippingCharge,
                'cod_charge' => $codCharge,
                'total_amount' => $subTotal + $shippingCharge + $codCharge,

                'payment_method' => 'cash_on_delivery',
                'payment_status' => 'cod_pending',

                'order_status' => 'pending',

                'is_fake' => false,
                'admin_note' => null,
                'customer_note' => 'Seed order for testing',

                'source_ip' => '127.0.0.1',
                'user_agent' => 'Seeder',
                'source_url' => url('/'),
            ]);
        }
    }

    private function generateInvoiceId(): string
    {
        do {
            $invoiceId = 'INV-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Order::where('invoice_id', $invoiceId)->exists());

        return $invoiceId;
    }
}