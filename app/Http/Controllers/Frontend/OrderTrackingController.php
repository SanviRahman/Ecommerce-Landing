<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Services\Storefront\PublicCatalog;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderTrackingController extends Controller
{
    public function index(Request $request, PublicCatalog $catalog): Response
    {
        // Tracking must remain reachable now that / is a storefront homepage,
        // not the former campaign page containing #order-track-section.
        $searchedPhone = $request->session()->pull('order_tracking_phone');
        $orders = is_string($searchedPhone) && preg_match('/\A01[0-9]{9}\z/D', $searchedPhone)
            ? $this->ordersByPhone($searchedPhone)
            : null;

        return response()->view('storefront.pages.track-order', [
            'siteSetting' => SiteSetting::query()->where('status', true)->latest()->first(),
            'navigation' => $catalog->navigationCategories(),
            'showFrontendHeader' => false,
            'campaign' => null,
            'orders' => $orders,
            'searchedPhone' => is_string($searchedPhone) ? $searchedPhone : '',
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tracking_phone' => ['required', 'regex:/^01[0-9]{9}$/'],
        ], [
            'tracking_phone.required' => 'ফোন নম্বর দিন।',
            'tracking_phone.regex' => 'সঠিক ১১ সংখ্যার বাংলাদেশি ফোন নম্বর দিন।',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $validator->errors()->first('tracking_phone'),
                    'errors' => $validator->errors(),
                ], 422);
            }

            return redirect()
                ->route('order.track')
                ->withErrors($validator)
                ->withInput();
        }

        $phone = $this->normalizePhone(
            $validator->validated()['tracking_phone']
        );

        if ($request->expectsJson()) {
            // AJAX tracking is intentionally transient. Clear any previous
            // tracking session value so a browser refresh never re-runs the
            // last phone-number search automatically.
            $request->session()->forget('order_tracking_phone');

            $orders = $this->ordersByPhone($phone);

            return response()->json([
                'message' => $orders->isEmpty()
                    ? 'এই ফোন নম্বরে কোনো অর্ডার পাওয়া যায়নি।'
                    : 'Order tracking details loaded successfully.',
                'phone' => $phone,
                'count' => $orders->count(),
                'html' => view('frontend.partials.order-tracking-results', [
                    'trackingOrders' => $orders,
                    'trackingSearchedPhone' => $phone,
                ])->render(),
            ]);
        }

        return redirect()
            ->route('order.track')
            ->with('order_tracking_phone', $phone);
    }

    /** Lookups are bounded and keyed only by the validated phone number. */
    private function ordersByPhone(string $phone): \Illuminate\Support\Collection
    {
        $phoneVariants = collect([
            $phone,
            '880' . substr($phone, 1),
            '+880' . substr($phone, 1),
        ])->unique()->values()->all();

        return Order::query()->with(['items.product', 'courierAccount'])
            ->whereIn('phone', $phoneVariants)->latest('id')->limit(20)->get();
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($phone, '880') && strlen($phone) === 13) {
            $phone = '0' . substr($phone, 3);
        }

        return $phone;
    }
}
