<?php

namespace App\Services;

use App\Models\CourierAccount;
use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SteadfastCourierService
{
    private int $timeout;

    private int $connectTimeout;

    private bool $forceIpv4;

    private bool $verifySsl;

    public function __construct(
        private readonly SteadfastStatusService $statusService
    ) {
        $this->timeout = max(5, (int) config('steadfast.timeout', 30));
        $this->connectTimeout = max(3, (int) config('steadfast.connect_timeout', 10));
        $this->forceIpv4 = (bool) config('steadfast.force_ipv4', true);
        $this->verifySsl = (bool) config('steadfast.verify_ssl', true);
    }

    public function createOrder(Order $order): array
    {
        $order->loadMissing(['items', 'courierAccount']);

        $courier = $this->resolveCourierAccount($order);

        if ($courier->code !== 'steadfast') {
            throw new RuntimeException('Selected courier is not SteadFast.');
        }

        $this->ensureConfigured($courier);

        if ($order->steadfast_consignment_id || $order->steadfast_tracking_code) {
            throw new RuntimeException('This order already sent to SteadFast.');
        }

        $url = $this->baseUrl($courier) . '/create_order';
        $payload = $this->makeOrderPayload($order);

        try {
            $result = $this->request($courier, 'POST', $url, $payload);
        } catch (Throwable $exception) {
            $message = $this->requestFailureMessage($url);

            $order->update([
                'steadfast_note' => $message,
                'steadfast_response' => [
                    'error' => $exception->getMessage(),
                    'exception' => $exception::class,
                    'base_url' => $this->baseUrl($courier),
                    'curl_loaded' => extension_loaded('curl'),
                    'allow_url_fopen' => $this->allowUrlFopenEnabled(),
                    'openssl_loaded' => extension_loaded('openssl'),
                ],
                'steadfast_synced_at' => now(),
            ]);

            Log::error('SteadFast create order request failed', [
                'order_id' => $order->id,
                'invoice' => $order->invoice_id,
                'host' => parse_url($url, PHP_URL_HOST),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'curl_loaded' => extension_loaded('curl'),
                'allow_url_fopen' => $this->allowUrlFopenEnabled(),
                'openssl_loaded' => extension_loaded('openssl'),
            ]);

            throw new RuntimeException($message, 0, $exception);
        }

        $data = $result['data'];

        if (! $this->isSuccessfulHttpStatus($result['status']) || (int) data_get($data, 'status') !== 200) {
            $message = data_get($data, 'message')
                ?: data_get($data, 'error')
                ?: 'SteadFast order create failed.';

            $order->update([
                'steadfast_note' => $message,
                'steadfast_response' => $data,
                'steadfast_synced_at' => now(),
            ]);

            Log::warning('SteadFast create order returned a failed response', [
                'order_id' => $order->id,
                'invoice' => $order->invoice_id,
                'transport' => $result['transport'],
                'http_status' => $result['status'],
                'api_status' => data_get($data, 'status'),
                'message' => Str::limit((string) $message, 500),
            ]);

            throw new RuntimeException((string) $message);
        }

        $consignmentId = data_get($data, 'consignment.consignment_id')
            ?: data_get($data, 'consignment.id')
            ?: data_get($data, 'data.consignment_id')
            ?: data_get($data, 'data.id')
            ?: data_get($data, 'consignment_id')
            ?: data_get($data, 'id');

        $trackingCode = data_get($data, 'consignment.tracking_code')
            ?: data_get($data, 'data.tracking_code')
            ?: data_get($data, 'tracking_code')
            ?: $consignmentId;

        $deliveryStatus = data_get($data, 'consignment.status')
            ?: data_get($data, 'data.status')
            ?: data_get($data, 'delivery_status');

        $order->update([
            'steadfast_consignment_id' => $consignmentId,
            'steadfast_tracking_code' => $trackingCode,
            'steadfast_status' => $deliveryStatus,
            'steadfast_note' => data_get($data, 'message'),
            'steadfast_response' => $data,
            'steadfast_sent_at' => now(),
            'steadfast_synced_at' => now(),
        ]);

        return $data;
    }

    public function syncStatus(Order $order): array
    {
        $order->loadMissing('courierAccount');

        $courier = $this->resolveCourierAccount($order);

        if ($courier->code !== 'steadfast') {
            throw new RuntimeException('Selected courier is not SteadFast.');
        }

        $this->ensureConfigured($courier);

        $url = $order->steadfast_tracking_code
            ? $this->baseUrl($courier) . '/status_by_trackingcode/' . urlencode($order->steadfast_tracking_code)
            : $this->baseUrl($courier) . '/status_by_invoice/' . urlencode($order->invoice_id);

        try {
            $result = $this->request($courier, 'GET', $url);
        } catch (Throwable $exception) {
            Log::error('SteadFast status sync request failed', [
                'order_id' => $order->id,
                'invoice' => $order->invoice_id,
                'host' => parse_url($url, PHP_URL_HOST),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException($this->requestFailureMessage($url), 0, $exception);
        }

        $data = $result['data'];

        if (! $this->isSuccessfulHttpStatus($result['status']) || (int) data_get($data, 'status', 200) !== 200) {
            $message = data_get($data, 'message')
                ?: data_get($data, 'error')
                ?: 'SteadFast status sync failed.';

            throw new RuntimeException((string) $message);
        }

        $deliveryStatus = data_get($data, 'delivery_status');

        if (blank($deliveryStatus)) {
            throw new RuntimeException('SteadFast response did not contain delivery_status.');
        }

        $this->statusService->apply(
            $order,
            $courier,
            $deliveryStatus,
            $data,
            'api'
        );

        return $data;
    }

    public function getBalance(?CourierAccount $courier = null): array
    {
        $courier = $courier ?: CourierAccount::query()
            ->where('code', 'steadfast')
            ->where('status', true)
            ->latest()
            ->first();

        $this->ensureConfigured($courier);

        $url = $this->baseUrl($courier) . '/get_balance';

        try {
            $result = $this->request($courier, 'GET', $url);
        } catch (Throwable $exception) {
            Log::error('SteadFast balance request failed', [
                'host' => parse_url($url, PHP_URL_HOST),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException($this->requestFailureMessage($url), 0, $exception);
        }

        return $result['data'];
    }

    private function resolveCourierAccount(Order $order): CourierAccount
    {
        $courier = $order->courierAccount;

        if (! $courier && $order->courier_account_id) {
            $courier = CourierAccount::query()->find($order->courier_account_id);
        }

        if (! $courier && $order->courier_service === 'steadfast') {
            $courier = CourierAccount::query()
                ->where('code', 'steadfast')
                ->where('status', true)
                ->latest()
                ->first();
        }

        if (! $courier) {
            throw new RuntimeException('Please select SteadFast courier from admin order details page first.');
        }

        if (! $courier->status) {
            throw new RuntimeException('Selected courier API account is inactive.');
        }

        return $courier;
    }

    private function makeOrderPayload(Order $order): array
    {
        $order->loadMissing('items');

        return [
            'invoice' => $order->invoice_id,
            'recipient_name' => Str::limit($order->customer_name ?: 'Customer', 100, ''),
            'recipient_phone' => $this->normalizePhone($order->phone),
            'recipient_address' => Str::limit($order->address ?: 'N/A', 250, ''),
            'cod_amount' => (float) ($order->total_amount ?? 0),
            'note' => $this->makeNote($order),
            'item_description' => $this->makeItemDescription($order),
            'total_lot' => max(1, (int) $order->items->sum('quantity')),
            'delivery_type' => 0,
        ];
    }

    private function request(
        CourierAccount $courier,
        string $method,
        string $url,
        ?array $payload = null
    ): array {
        $method = strtoupper($method);

        if ($this->laravelHttpTransportAvailable()) {
            try {
                $response = match ($method) {
                    'GET' => $this->client($courier)->get($url),
                    'POST' => $this->client($courier)->post($url, $payload ?? []),
                    default => throw new RuntimeException('Unsupported SteadFast HTTP method.'),
                };

                return $this->resultFromLaravelResponse($response, 'laravel-http');
            } catch (ConnectionException $exception) {
                return $this->requestWithFallbackAfterFailure(
                    $courier,
                    $method,
                    $url,
                    $payload,
                    $exception
                );
            } catch (Throwable $exception) {
                if (! $this->isMissingGuzzleHandlerException($exception)) {
                    throw $exception;
                }

                return $this->requestWithFallbackAfterFailure(
                    $courier,
                    $method,
                    $url,
                    $payload,
                    $exception
                );
            }
        }

        Log::warning('SteadFast Laravel HTTP transport unavailable; using TLS socket fallback.', [
            'host' => parse_url($url, PHP_URL_HOST),
            'curl_loaded' => extension_loaded('curl'),
            'allow_url_fopen' => $this->allowUrlFopenEnabled(),
            'openssl_loaded' => extension_loaded('openssl'),
        ]);

        return $this->requestUsingTlsSocket($courier, $method, $url, $payload);
    }

    private function requestWithFallbackAfterFailure(
        CourierAccount $courier,
        string $method,
        string $url,
        ?array $payload,
        Throwable $exception
    ): array {
        Log::warning('SteadFast Laravel HTTP request failed; retrying with TLS socket fallback.', [
            'host' => parse_url($url, PHP_URL_HOST),
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'curl_loaded' => extension_loaded('curl'),
            'allow_url_fopen' => $this->allowUrlFopenEnabled(),
            'openssl_loaded' => extension_loaded('openssl'),
        ]);

        try {
            return $this->requestUsingTlsSocket($courier, $method, $url, $payload);
        } catch (Throwable $fallbackException) {
            Log::error('SteadFast TLS socket fallback failed', [
                'host' => parse_url($url, PHP_URL_HOST),
                'exception' => $fallbackException::class,
                'message' => $fallbackException->getMessage(),
            ]);

            throw new RuntimeException(
                'SteadFast HTTP transport failed. Enable the PHP cURL extension or check outbound HTTPS access.',
                0,
                $fallbackException
            );
        }
    }

    private function client(CourierAccount $courier): PendingRequest
    {
        $options = [];

        if (! $this->verifySsl) {
            $options['verify'] = false;
        }

        if (
            $this->forceIpv4
            && extension_loaded('curl')
            && defined('CURLOPT_IPRESOLVE')
            && defined('CURL_IPRESOLVE_V4')
        ) {
            $options['curl'] = [
                constant('CURLOPT_IPRESOLVE') => constant('CURL_IPRESOLVE_V4'),
            ];
        }

        return Http::withOptions($options)
            ->connectTimeout($this->connectTimeout)
            ->timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Api-Key' => trim((string) $courier->api_key),
                'Secret-Key' => trim((string) $courier->secret_key),
                'Content-Type' => 'application/json',
                'User-Agent' => 'Laravel-SteadFast-Client/1.0',
            ]);
    }

    private function requestUsingTlsSocket(
        CourierAccount $courier,
        string $method,
        string $url,
        ?array $payload = null
    ): array {
        if (! function_exists('stream_socket_client')) {
            throw new RuntimeException('stream_socket_client is unavailable. Enable PHP cURL in cPanel.');
        }

        if (! extension_loaded('openssl')) {
            throw new RuntimeException('PHP OpenSSL extension is required for the SteadFast HTTPS fallback transport.');
        }

        $parts = parse_url($url);

        if (
            ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
        ) {
            throw new RuntimeException('SteadFast TLS socket fallback requires a valid HTTPS URL.');
        }

        $host = (string) $parts['host'];
        $port = (int) ($parts['port'] ?? 443);
        $path = (string) ($parts['path'] ?? '/');
        $query = (string) ($parts['query'] ?? '');

        if ($path === '') {
            $path = '/';
        }

        if ($query !== '') {
            $path .= '?' . $query;
        }

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => $this->verifySsl,
                'verify_peer_name' => $this->verifySsl,
                'allow_self_signed' => ! $this->verifySsl,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ],
        ]);

        $connectHost = $host;

        if ($this->forceIpv4) {
            $ipv4 = gethostbyname($host);

            if (filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $connectHost = $ipv4;
            }
        }

        $errno = 0;
        $error = '';

        $socket = @stream_socket_client(
            'tls://' . $connectHost . ':' . $port,
            $errno,
            $error,
            $this->connectTimeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (! is_resource($socket)) {
            throw new RuntimeException(
                'SteadFast TLS connection failed: ' . ($error !== '' ? $error : 'unknown error') . " ({$errno})"
            );
        }

        try {
            stream_set_timeout($socket, $this->timeout);

            $body = '';

            if ($method === 'POST') {
                $body = json_encode($payload ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                if ($body === false) {
                    throw new RuntimeException('Unable to encode SteadFast request payload.');
                }
            }

            $hostHeader = $port === 443 ? $host : $host . ':' . $port;
            $headers = [
                $method . ' ' . $path . ' HTTP/1.1',
                'Host: ' . $hostHeader,
                'Accept: application/json',
                'Accept-Encoding: identity',
                'Api-Key: ' . trim((string) $courier->api_key),
                'Secret-Key: ' . trim((string) $courier->secret_key),
                'User-Agent: Laravel-SteadFast-Client/1.0',
                'Connection: close',
            ];

            if ($method === 'POST') {
                $headers[] = 'Content-Type: application/json';
                $headers[] = 'Content-Length: ' . strlen($body);
            }

            $request = implode("\r\n", $headers) . "\r\n\r\n" . $body;
            $offset = 0;
            $requestLength = strlen($request);

            while ($offset < $requestLength) {
                $written = fwrite($socket, substr($request, $offset));

                if ($written === false || $written === 0) {
                    throw new RuntimeException('Unable to write the SteadFast request to the TLS socket.');
                }

                $offset += $written;
            }

            $rawResponse = '';

            while (! feof($socket)) {
                $chunk = fread($socket, 8192);

                if ($chunk === false) {
                    throw new RuntimeException('Unable to read the SteadFast response from the TLS socket.');
                }

                $rawResponse .= $chunk;

                $meta = stream_get_meta_data($socket);

                if (($meta['timed_out'] ?? false) === true) {
                    throw new RuntimeException('SteadFast TLS socket request timed out.');
                }
            }
        } finally {
            fclose($socket);
        }

        $parsed = $this->parseRawHttpResponse($rawResponse);
        $parsed['data'] = $this->decodeBody($parsed['status'], $parsed['body']);
        $parsed['transport'] = 'tls-socket-fallback';

        Log::info('SteadFast API response via TLS socket fallback', [
            'host' => $host,
            'http_status' => $parsed['status'],
            'api_status' => data_get($parsed['data'], 'status'),
            'invoice' => $payload['invoice'] ?? null,
        ]);

        return $parsed;
    }

    private function resultFromLaravelResponse(Response $response, string $transport): array
    {
        return [
            'status' => $response->status(),
            'content_type' => (string) $response->header('Content-Type'),
            'body' => $response->body(),
            'data' => $this->decodeResponse($response),
            'transport' => $transport,
        ];
    }

    private function parseRawHttpResponse(string $rawResponse): array
    {
        if ($rawResponse === '') {
            throw new RuntimeException('SteadFast returned an empty HTTP response.');
        }

        $headerEnd = strpos($rawResponse, "\r\n\r\n");

        if ($headerEnd === false) {
            throw new RuntimeException('SteadFast returned an invalid HTTP response.');
        }

        $rawHeaders = substr($rawResponse, 0, $headerEnd);
        $body = substr($rawResponse, $headerEnd + 4);
        $headerLines = explode("\r\n", $rawHeaders);
        $statusLine = array_shift($headerLines);

        if (! preg_match('/^HTTP\/\S+\s+(\d{3})/', (string) $statusLine, $matches)) {
            throw new RuntimeException('SteadFast returned an invalid HTTP status line.');
        }

        $status = (int) $matches[1];
        $headers = [];

        foreach ($headerLines as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        if (str_contains(strtolower((string) ($headers['transfer-encoding'] ?? '')), 'chunked')) {
            $body = $this->decodeChunkedBody($body);
        }

        return [
            'status' => $status,
            'content_type' => (string) ($headers['content-type'] ?? ''),
            'body' => $body,
        ];
    }

    private function decodeChunkedBody(string $body): string
    {
        $decoded = '';
        $offset = 0;
        $length = strlen($body);

        while ($offset < $length) {
            $lineEnd = strpos($body, "\r\n", $offset);

            if ($lineEnd === false) {
                throw new RuntimeException('Invalid chunked response from SteadFast.');
            }

            $sizeLine = trim(substr($body, $offset, $lineEnd - $offset));
            $sizeToken = explode(';', $sizeLine, 2)[0];

            if ($sizeToken === '' || ! ctype_xdigit($sizeToken)) {
                throw new RuntimeException('Invalid SteadFast chunk size.');
            }

            $chunkSize = hexdec($sizeToken);
            $offset = $lineEnd + 2;

            if ($chunkSize === 0) {
                break;
            }

            if ($offset + $chunkSize > $length) {
                throw new RuntimeException('Incomplete chunked response from SteadFast.');
            }

            $decoded .= substr($body, $offset, $chunkSize);
            $offset += $chunkSize + 2;
        }

        return $decoded;
    }

    private function decodeResponse(Response $response): array
    {
        $data = $response->json();

        if (is_array($data)) {
            return $data;
        }

        return [
            'status' => $response->status(),
            'message' => $response->body(),
        ];
    }

    private function decodeBody(int $status, string $body): array
    {
        $data = json_decode($body, true);

        if (is_array($data)) {
            return $data;
        }

        return [
            'status' => $status,
            'message' => $body,
        ];
    }

    private function laravelHttpTransportAvailable(): bool
    {
        return extension_loaded('curl') || $this->allowUrlFopenEnabled();
    }

    private function allowUrlFopenEnabled(): bool
    {
        return filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL);
    }

    private function isMissingGuzzleHandlerException(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'guzzlehttp requires curl')
            || str_contains($message, 'allow_url_fopen ini setting')
            || str_contains($message, 'custom http handler');
    }

    private function requestFailureMessage(string $url): string
    {
        $baseUrl = rtrim((string) preg_replace('#/(create_order|get_balance|status_by_[^/]+/.*)$#', '', $url), '/');

        return 'SteadFast connection failed from this server. Check PHP cURL/OpenSSL/outbound HTTPS and courier base URL. Current URL: '
            . ($baseUrl !== '' ? $baseUrl : $url);
    }

    private function isSuccessfulHttpStatus(int $status): bool
    {
        return $status >= 200 && $status < 300;
    }

    private function baseUrl(CourierAccount $courier): string
    {
        return rtrim($courier->base_url ?: 'https://portal.packzy.com/api/v1', '/');
    }

    private function ensureConfigured(?CourierAccount $courier): void
    {
        if (! $courier) {
            throw new RuntimeException('No active SteadFast courier API account found.');
        }

        if (blank($courier->base_url)) {
            throw new RuntimeException('SteadFast API base URL is missing.');
        }

        if (blank($courier->api_key) || blank($courier->secret_key)) {
            throw new RuntimeException('SteadFast API key or secret key is missing.');
        }

        if (str_contains($courier->base_url, 'portal.steadfast.com.bd')) {
            throw new RuntimeException('Wrong SteadFast base URL. Use: https://portal.packzy.com/api/v1');
        }
    }

    private function normalizePhone(?string $phone): string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);

        if (str_starts_with($phone, '880') && strlen($phone) === 13) {
            $phone = substr($phone, 2);
        }

        if (strlen($phone) === 10 && str_starts_with($phone, '1')) {
            $phone = '0' . $phone;
        }

        return $phone;
    }

    private function makeNote(Order $order): ?string
    {
        $notes = [];

        if ($order->delivery_area) {
            $notes[] = 'Area: ' . ucwords(str_replace('_', ' ', $order->delivery_area));
        }

        if ($order->is_free_delivery) {
            $notes[] = 'Free Delivery';
        }

        if ($order->customer_note) {
            $notes[] = 'Customer Note: ' . $order->customer_note;
        }

        if ($order->admin_note) {
            $notes[] = 'Admin Note: ' . $order->admin_note;
        }

        return empty($notes)
            ? null
            : Str::limit(implode(' | ', $notes), 250, '');
    }

    private function makeItemDescription(Order $order): ?string
    {
        if (! $order->relationLoaded('items')) {
            $order->load('items');
        }

        $items = $order->items
            ->map(fn ($item) => $item->quantity . ' x ' . $item->product_name)
            ->implode(', ');

        return $items ? Str::limit($items, 250, '') : null;
    }
}
