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

        return response()
            ->view('frontend.pages.success', [
                'order' => $order,
                'title' => 'Order Successful',
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }
}