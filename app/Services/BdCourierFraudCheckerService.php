<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class BdCourierFraudCheckerService
{
    public function check(?string $phone): array
    {
        $phone = $this->normalizePhone($phone);

        if (! $phone) {
            throw new RuntimeException('Valid customer phone number not found.');
        }

        $baseUrl = rtrim((string) config('services.bdcourier.url'), '/');
        $endpoint = '/' . ltrim((string) config('services.bdcourier.check_endpoint'), '/');
        $url = $baseUrl . $endpoint;

        $token = (string) config('services.bdcourier.token');
        $method = strtolower((string) config('services.bdcourier.method', 'POST'));

        if (! $token) {
            throw new RuntimeException('BD Courier API token is missing. Please set BDCOURIER_API_TOKEN in .env.');
        }

        $client = Http::timeout((int) config('services.bdcourier.timeout', 15))
            ->acceptJson()
            ->asJson()
            ->withToken($token)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
                'api_key' => $token,
            ]);

        $payload = [
            'phone' => $phone,
            'phone_number' => $phone,
            'mobile' => $phone,
            'customer_phone' => $phone,
        ];

        $response = $method === 'get'
            ? $client->get($url, ['phone' => $phone, 'phone_number' => $phone])
            : $client->post($url, $payload);

        Log::info('BD Courier fraud check response', [
            'url' => $url,
            'phone' => $phone,
            'status' => $response->status(),
            'content_type' => $response->header('Content-Type'),
            'body' => Str::limit($response->body(), 1000),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'BD Courier API error HTTP ' . $response->status() . ': ' . Str::limit($response->body(), 300)
            );
        }

        $raw = $response->json();

        if (! is_array($raw)) {
            throw new RuntimeException(
                'Invalid JSON response from BD Courier API. Check API URL/endpoint. Response: ' . Str::limit($response->body(), 300)
            );
        }

        return $this->formatResponse($phone, $raw);
    }

    private function normalizePhone(?string $phone): ?string
    {
        $phone = preg_replace('/[^0-9]/', '', (string) $phone);

        if (Str::startsWith($phone, '8801') && strlen($phone) === 13) {
            return '0' . substr($phone, 3);
        }

        if (Str::startsWith($phone, '1') && strlen($phone) === 10) {
            return '0' . $phone;
        }

        if (Str::startsWith($phone, '01') && strlen($phone) === 11) {
            return $phone;
        }

        return $phone ?: null;
    }

    private function formatResponse(string $phone, array $raw): array
    {
        $totalSummary = data_get($raw, 'data.totalSummary')
            ?? data_get($raw, 'totalSummary')
            ?? [];

        $couriers = $this->extractCourierRows($raw);

        $total = (int) ($totalSummary['total'] ?? collect($couriers)->sum('total'));
        $success = (int) ($totalSummary['success'] ?? collect($couriers)->sum('success'));
        $cancel = (int) ($totalSummary['cancel'] ?? collect($couriers)->sum('cancel'));

        $successRatio = isset($totalSummary['successRate'])
            ? round((float) $totalSummary['successRate'], 2)
            : ($total > 0 ? round(($success / $total) * 100, 2) : 0);

        $cancelRatio = isset($totalSummary['cancelRate'])
            ? round((float) $totalSummary['cancelRate'], 2)
            : ($total > 0 ? round(($cancel / $total) * 100, 2) : 0);

        return [
            'phone' => $phone,
            'total' => $total,
            'success' => $success,
            'cancel' => $cancel,
            'success_ratio' => $successRatio,
            'cancel_ratio' => $cancelRatio,
            'risk_level' => $this->riskLevel($total, $successRatio, $cancelRatio),
            'couriers' => $couriers,
            'raw' => $raw,
        ];
    }

    private function extractCourierRows(array $raw): array
    {
        $source = data_get($raw, 'data.Summaries')
            ?? data_get($raw, 'data.summaries')
            ?? data_get($raw, 'Summaries')
            ?? data_get($raw, 'summaries')
            ?? data_get($raw, 'data')
            ?? data_get($raw, 'result')
            ?? data_get($raw, 'couriers')
            ?? [];

        if (! is_array($source)) {
            return [];
        }

        $rows = [];

        foreach ($source as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if (in_array($key, ['totalSummary', 'summary'], true)) {
                continue;
            }

            $courierName = is_string($key)
                ? $key
                : ($value['courier'] ?? $value['name'] ?? $value['company'] ?? 'Courier');

            $total = $this->numberFrom($value, [
                'total',
                'total_order',
                'total_orders',
                'total_parcel',
                'total_delivery',
                'total_deliveries',
            ]);

            $success = $this->numberFrom($value, [
                'success',
                'successful',
                'delivered',
                'delivery_success',
                'success_parcel',
                'success_count',
            ]);

            $cancel = $this->numberFrom($value, [
                'cancel',
                'cancelled',
                'canceled',
                'return',
                'returned',
                'cancel_count',
                'return_count',
            ]);

            if ($total === 0 && ($success > 0 || $cancel > 0)) {
                $total = $success + $cancel;
            }

            $rows[] = [
                'courier' => ucwords(str_replace(['_', '-'], ' ', (string) $courierName)),
                'total' => $total,
                'success' => $success,
                'cancel' => $cancel,
                'success_ratio' => $total > 0 ? round(($success / $total) * 100, 2) : 0,
                'cancel_ratio' => $total > 0 ? round(($cancel / $total) * 100, 2) : 0,
                'message' => $value['message'] ?? null,
                'risk_level' => $value['risk_level'] ?? null,
                'customer_rating' => $value['customer_rating'] ?? null,
            ];
        }

        return $rows;
    }

    private function numberFrom(array $data, array $keys): int
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                return (int) $data[$key];
            }
        }

        return 0;
    }

    private function riskLevel(int $total, float $successRatio, float $cancelRatio): string
    {
        if ($total <= 0) {
            return 'unknown';
        }

        if ($cancelRatio >= 60 || $successRatio < 40) {
            return 'high';
        }

        if ($cancelRatio >= 35 || $successRatio < 65) {
            return 'medium';
        }

        return 'low';
    }
}