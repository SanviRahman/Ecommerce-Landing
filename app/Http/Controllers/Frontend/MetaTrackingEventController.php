<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\MetaConversionsApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaTrackingEventController extends Controller
{
    /** Only the supported browser funnel events are relayed; a client cannot fabricate a Purchase. */
    public function store(Request $request, MetaConversionsApiService $conversions): JsonResponse
    {
        $data = $request->validate([
            'event_name' => ['required', 'in:PageView,ViewContent,AddToCart,InitiateCheckout'],
            'event_id' => ['required', 'string', 'regex:/\A[A-Za-z0-9_-]{8,128}\z/D'],
            'custom_data' => ['nullable', 'array'],
            'custom_data.value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'custom_data.currency' => ['nullable', 'in:BDT'],
            'custom_data.content_type' => ['nullable', 'in:product,product_group'],
            'custom_data.content_ids' => ['nullable', 'array', 'max:30'],
            'custom_data.content_ids.*' => ['string', 'max:100'],
            'custom_data.contents' => ['nullable', 'array', 'max:30'],
            'custom_data.contents.*.id' => ['required', 'string', 'max:100'],
            'custom_data.contents.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'custom_data.contents.*.item_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'custom_data.num_items' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        // The request uses the site's session CSRF token. Failures must not interrupt shopping.
        $delivered = $conversions->sendBrowserEvent($data, $request);

        // Accepted by our endpoint does not guarantee that Meta received the event.
        return response()->json([
            'accepted' => true,
            'server_events_delivered' => $delivered,
        ])->header('Cache-Control', 'no-store');
    }
}
