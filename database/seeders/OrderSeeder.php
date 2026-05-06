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
        $courierServices = array_keys(config('couriers.list', [
            'steadfast'    => 'SteadFast Courier',
            'pathao'       => 'Pathao Courier',
            'redx'         => 'RedX Courier',
            'paperfly'     => 'Paperfly',
            'e_courier'    => 'eCourier',
            'sundarban'    => 'Sundarban Courier',
            'sa_paribahan' => 'SA Paribahan',
        ]));

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
            $subTotal = random_int(500, 5000);
            $shippingCharge = random_int(0, 1) ? 70 : 130;
            $codCharge = 0;
            $totalAmount = $subTotal + $shippingCharge + $codCharge;

            $deliveryArea = $shippingCharge === 70
                ? 'inside_dhaka'
                : 'outside_dhaka';

            $orderStatus = $orderStatuses[array_rand($orderStatuses)];
            $paymentStatus = $paymentStatuses[array_rand($paymentStatuses)];

            $isFake = $orderStatus === 'fake';

            Order::create([
                'invoice_id' => $this->generateInvoiceId(),

                'campaign_id' => Campaign::query()->inRandomOrder()->value('id'),

                'customer_name' => 'Customer ' . $i,
                'phone' => '01711' . random_int(100000, 999999),
                'address' => 'Dhaka, Bangladesh',

                'delivery_area' => $deliveryArea,
                'courier_service' => $courierServices[array_rand($courierServices)],

                'sub_total' => $subTotal,
                'shipping_charge' => $shippingCharge,
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
                ]) ? now()->subDays(random_int(1, 5)) : null,

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