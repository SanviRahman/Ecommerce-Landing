<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderTrackingController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->to(route('home') . '#order-track-section');
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
                ->to(route('home') . '#order-track-section')
                ->withErrors($validator)
                ->withInput();
        }

        $campaign = Campaign::resolveHomepageCampaign();

        if (! $campaign || ! (bool) ($campaign->order_tracking_section_status ?? true)) {
            $message = 'Order tracking বর্তমানে বন্ধ আছে।';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'errors' => [
                        'tracking_phone' => [$message],
                    ],
                ], 422);
            }

            return redirect()
                ->to(route('home') . '#order-track-section')
                ->withErrors([
                    'tracking_phone' => $message,
                ]);
        }

        $phone = $this->normalizePhone(
            $validator->validated()['tracking_phone']
        );

        if ($request->expectsJson()) {
            $request->session()->put('order_tracking_phone', $phone);

            $phoneVariants = collect([
                $phone,
                '880' . substr($phone, 1),
                '+880' . substr($phone, 1),
            ])->unique()->values()->all();

            $orders = Order::query()
                ->with(['items.product', 'courierAccount'])
                ->whereIn('phone', $phoneVariants)
                ->latest('id')
                ->limit(20)
                ->get();

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
            ->to(route('home') . '#order-track-section')
            ->with('order_tracking_phone', $phone);
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
