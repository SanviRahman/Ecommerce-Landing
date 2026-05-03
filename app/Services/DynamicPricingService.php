<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ShippingCharge;

class DynamicPricingService
{
    public function calculate(Product $product, int $quantity = 1, ?int $shippingChargeId = null): array
    {
        $quantity = max($quantity, 1);

        $unitPrice = (int) $product->new_price;
        $subTotal = $unitPrice * $quantity;

        $shippingCharge = 0;

        if ($shippingChargeId) {
            $shippingCharge = (int) ShippingCharge::where('status', true)
                ->where('id', $shippingChargeId)
                ->value('delivery_charge');
        }

        $codCharge = 0;
        $totalAmount = $subTotal + $shippingCharge + $codCharge;

        return [
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'sub_total' => $subTotal,
            'shipping_charge' => $shippingCharge,
            'cod_charge' => $codCharge,
            'total_amount' => $totalAmount,
        ];
    }
}