<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderTrackingController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->to(route('home') . '#order-track-section');
    }

    public function search(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'tracking_phone' => ['required', 'regex:/^01[0-9]{9}$/'],
        ], [
            'tracking_phone.required' => 'ফোন নম্বর দিন।',
            'tracking_phone.regex' => 'সঠিক ১১ সংখ্যার বাংলাদেশি ফোন নম্বর দিন।',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->to(route('home') . '#order-track-section')
                ->withErrors($validator)
                ->withInput();
        }

        $campaign = Campaign::resolveHomepageCampaign();

        if (! $campaign || ! (bool) ($campaign->order_tracking_section_status ?? true)) {
            return redirect()
                ->to(route('home') . '#order-track-section')
                ->withErrors([
                    'tracking_phone' => 'Order tracking বর্তমানে বন্ধ আছে।',
                ]);
        }

        return redirect()
            ->to(route('home') . '#order-track-section')
            ->with('order_tracking_phone', $this->normalizePhone($validator->validated()['tracking_phone']));
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
