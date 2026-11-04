<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\MetaConversionsApiService;
use Illuminate\Http\Request;

class OrderSuccessController extends Controller
{
    public function show(string $token, Request $request, MetaConversionsApiService $conversions)
    {
        $order = Order::query()
            ->with([
                'items',
                'items.product',
                'campaign',
            ])
            ->where('success_token', $token)
            ->firstOrFail();

        // Fallback for a bookmarked success URL; the shared cache avoids duplicate sends.
        $conversions->dispatchPurchaseAfterResponse($order, $request);

        return response()
            ->view('frontend.pages.success', [
                'order' => $order,
                'campaign' => $order->campaign,
                'showFrontendHeaderContent' => $order->campaign !== null,
                'title' => 'Order Successful',
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }
}