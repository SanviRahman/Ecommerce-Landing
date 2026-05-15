<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order;

class OrderSuccessController extends Controller
{
    public function show(string $token)
    {
        $order = Order::query()
            ->with([
                'items',
                'items.product',
                'campaign',
            ])
            ->where('success_token', $token)
            ->firstOrFail();

        return view('frontend.pages.success', [
            'order' => $order,
            'title' => 'Order Successful',
        ]);
    }
}