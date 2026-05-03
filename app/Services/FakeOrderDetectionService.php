<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FakeOrderDetectionService
{
    public function detect(array $data, Request $request): array
    {
        $reasons = [];

        $phone = $data['phone'] ?? null;
        $quantity = (int) ($data['quantity'] ?? 1);
        $address = trim($data['address'] ?? '');
        $ip = $request->ip();

        if ($phone && !preg_match('/^(01)[0-9]{9}$/', $phone)) {
            $reasons[] = 'Invalid phone number pattern';
        }

        if (strlen($address) < 10) {
            $reasons[] = 'Incomplete address';
        }

        if ($quantity >= 100) {
            $reasons[] = 'Suspicious quantity';
        }

        if ($phone) {
            $recentPhoneOrders = Order::where('phone', $phone)
                ->where('created_at', '>=', Carbon::now()->subMinutes(10))
                ->count();

            if ($recentPhoneOrders >= 2) {
                $reasons[] = 'Same phone number multiple orders within short time';
            }
        }

        if ($ip) {
            $recentIpOrders = Order::where('source_ip', $ip)
                ->where('created_at', '>=', Carbon::now()->subMinutes(10))
                ->count();

            if ($recentIpOrders >= 3) {
                $reasons[] = 'Same IP multiple orders within short time';
            }
        }

        $name = strtolower(trim($data['customer_name'] ?? ''));

        if (in_array($name, ['test', 'demo', 'abc', 'asdf', 'qwerty'])) {
            $reasons[] = 'Test-like customer name';
        }

        return [
            'is_fake' => count($reasons) > 0,
            'reasons' => $reasons,
            'reason_text' => implode(', ', $reasons),
        ];
    }
}