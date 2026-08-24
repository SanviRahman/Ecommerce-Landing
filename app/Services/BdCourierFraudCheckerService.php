<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BdCourierFraudCheckerService
{
    public function check(?string $phone): array
    {
        $phone = $this->normalizePhone($phone);

        if (! $phone) {
            throw new RuntimeException('Valid customer phone number not found.');
        }

        $baseUrl = rtrim(trim((string) config('services.bdcourier.url')), '/');
        $endpoint = '/' . ltrim(trim((string) config('services.bdcourier.check_endpoint')), '/');
        $url = $baseUrl . $endpoint;
        $token = trim((string) config('services.bdcourier.token'));
        $method = strtolower(trim((string) config('services.bdcourier.method', 'post')));

        if ($baseUrl === '' || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException(
                'BD Courier API URL is missing or invalid. Check BDCOURIER_API_URL and clear config cache.'
            );
        }

        if ($token === '') {
            throw new RuntimeException(
                'BD Courier API token is missing. Check BDCOURIER_API_TOKEN and clear config cache.'
            );
        }

        if (! in_array($method, ['get', 'post'], true)) {
            throw new RuntimeException('BDCOURIER_METHOD must be GET or POST.');
        }

        $payload = [
            'phone' => $phone,
            'phone_number' => $phone,
            'mobile' => $phone,
            'customer_phone' => $phone,
        ];

        $transport = 'laravel-http';

        try {
            if ($this->laravelHttpTransportAvailable()) {
                $result = $this->requestUsingLaravelHttp(
                    $method,
                    $url,
                    $token,
                    $payload
                );
            } else {
                $transport = 'tls-socket-fallback';

                Log::warning('BD Courier Laravel HTTP transport unavailable; using TLS socket fallback.', [
                    'host' => parse_url($url, PHP_URL_HOST),
                    'phone' => $this->maskPhone($phone),
                    'curl_loaded' => extension_loaded('curl'),
                    'allow_url_fopen' => $this->allowUrlFopenEnabled(),
                    'openssl_loaded' => extension_loaded('openssl'),
                ]);

                $result = $this->requestUsingTlsSocket(
                    $method,
                    $url,
                    $token,
                    $payload
                );
            }
        } catch (ConnectionException $exception) {
            Log::error('BD Courier fraud check connection failed', [
                'url' => $url,
                'host' => parse_url($url, PHP_URL_HOST),
                'phone' => $this->maskPhone($phone),
                'message' => $exception->getMessage(),
                'curl_loaded' => extension_loaded('curl'),
                'allow_url_fopen' => $this->allowUrlFopenEnabled(),
                'openssl_loaded' => extension_loaded('openssl'),
                'force_ipv4' => (bool) config('services.bdcourier.force_ipv4', true),
                'verify_ssl' => (bool) config('services.bdcourier.verify_ssl', true),
            ]);

            throw new RuntimeException(
                'BD Courier API connection failed from this server.',
                0,
                $exception
            );
        } catch (Throwable $exception) {
            if (
                $transport === 'laravel-http'
                && $this->isMissingGuzzleHandlerException($exception)
            ) {
                try {
                    $transport = 'tls-socket-fallback';

                    Log::warning('BD Courier Guzzle handler unavailable; retrying with TLS socket fallback.', [
                        'host' => parse_url($url, PHP_URL_HOST),
                        'phone' => $this->maskPhone($phone),
                        'message' => $exception->getMessage(),
                    ]);

                    $result = $this->requestUsingTlsSocket(
                        $method,
                        $url,
                        $token,
                        $payload
                    );
                } catch (Throwable $fallbackException) {
                    $this->logRequestFailure(
                        $url,
                        $phone,
                        $fallbackException,
                        'tls-socket-fallback'
                    );

                    throw new RuntimeException(
                        'BD Courier API request failed from this server. Enable PHP cURL in cPanel if the fallback transport is blocked.',
                        0,
                        $fallbackException
                    );
                }
            } else {
                $this->logRequestFailure(
                    $url,
                    $phone,
                    $exception,
                    $transport
                );

                throw new RuntimeException(
                    'BD Courier API request failed from this server.',
                    0,
                    $exception
                );
            }
        }

        Log::info('BD Courier fraud check response', [
            'url' => $url,
            'phone' => $this->maskPhone($phone),
            'transport' => $transport,
            'status' => $result['status'],
            'content_type' => $result['content_type'],
            'body' => Str::limit($result['body'], 1000),
        ]);

        if ($result['status'] < 200 || $result['status'] >= 300) {
            Log::warning('BD Courier fraud check returned non-success HTTP status', [
                'url' => $url,
                'phone' => $this->maskPhone($phone),
                'transport' => $transport,
                'status' => $result['status'],
                'body' => Str::limit($result['body'], 1000),
            ]);

            throw new RuntimeException(
                'BD Courier API returned HTTP ' . $result['status'] . '.'
            );
        }

        $raw = json_decode($result['body'], true);

        if (! is_array($raw)) {
            throw new RuntimeException(
                'BD Courier API returned an invalid JSON response.'
            );
        }

        $apiStatus = strtolower((string) data_get($raw, 'status', ''));

        if (in_array($apiStatus, ['error', 'failed', 'failure', 'false'], true)) {
            $apiMessage = (string) (
                data_get($raw, 'message')
                ?? data_get($raw, 'error')
                ?? 'BD Courier API reported a failed response.'
            );

            Log::warning('BD Courier fraud check API-level failure', [
                'phone' => $this->maskPhone($phone),
                'status' => $apiStatus,
                'message' => Str::limit($apiMessage, 500),
            ]);

            throw new RuntimeException('BD Courier API reported a failed response.');
        }

        return $this->formatResponse($phone, $raw);
    }

    private function requestUsingLaravelHttp(
        string $method,
        string $url,
        string $token,
        array $payload
    ): array {
        $client = $this->client($token);

        $response = $method === 'get'
            ? $client->get($url, [
                'phone' => $payload['phone'],
                'phone_number' => $payload['phone_number'],
            ])
            : $client->post($url, $payload);

        return [
            'status' => $response->status(),
            'content_type' => (string) $response->header('Content-Type'),
            'body' => $response->body(),
        ];
    }

    private function client(string $token): PendingRequest
    {
        $options = [];

        if (! (bool) config('services.bdcourier.verify_ssl', true)) {
            $options['verify'] = false;
        }

        if (
            (bool) config('services.bdcourier.force_ipv4', true)
            && extension_loaded('curl')
            && defined('CURLOPT_IPRESOLVE')
            && defined('CURL_IPRESOLVE_V4')
        ) {
            $options['curl'] = [
                constant('CURLOPT_IPRESOLVE') => constant('CURL_IPRESOLVE_V4'),
            ];
        }

        return Http::withOptions($options)
            ->connectTimeout(max(3, (int) config('services.bdcourier.connect_timeout', 10)))
            ->timeout(max(5, (int) config('services.bdcourier.timeout', 30)))
            ->acceptJson()
            ->asJson()
            ->withToken($token)
            ->withHeaders([
                'api_key' => $token,
                'User-Agent' => 'UpayBazar-Fraud-Checker/1.0',
            ]);
    }

    private function requestUsingTlsSocket(
        string $method,
        string $url,
        string $token,
        array $payload
    ): array {
        if (! function_exists('stream_socket_client')) {
            throw new RuntimeException(
                'stream_socket_client is unavailable. Enable PHP cURL in cPanel.'
            );
        }

        if (! extension_loaded('openssl')) {
            throw new RuntimeException(
                'PHP OpenSSL extension is required for the HTTPS fallback transport.'
            );
        }

        $parts = parse_url($url);

        if (
            ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
        ) {
            throw new RuntimeException(
                'TLS socket fallback requires a valid HTTPS URL.'
            );
        }

        $host = (string) $parts['host'];
        $port = (int) ($parts['port'] ?? 443);
        $path = (string) ($parts['path'] ?? '/');

        if ($path === '') {
            $path = '/';
        }

        $query = (string) ($parts['query'] ?? '');

        if ($method === 'get') {
            $requestQuery = http_build_query([
                'phone' => $payload['phone'],
                'phone_number' => $payload['phone_number'],
            ]);

            $query = $query !== ''
                ? $query . '&' . $requestQuery
                : $requestQuery;
        }

        if ($query !== '') {
            $path .= '?' . $query;
        }

        $verifySsl = (bool) config('services.bdcourier.verify_ssl', true);

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => $verifySsl,
                'verify_peer_name' => $verifySsl,
                'allow_self_signed' => ! $verifySsl,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ],
        ]);

        $connectHost = $host;

        if ((bool) config('services.bdcourier.force_ipv4', true)) {
            $ipv4 = gethostbyname($host);

            if (filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $connectHost = $ipv4;
            }
        }

        $timeout = max(5, (int) config('services.bdcourier.timeout', 30));
        $connectTimeout = max(3, (int) config('services.bdcourier.connect_timeout', 10));
        $errno = 0;
        $error = '';

        $socket = @stream_socket_client(
            'tls://' . $connectHost . ':' . $port,
            $errno,
            $error,
            $connectTimeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (! is_resource($socket)) {
            throw new RuntimeException(
                'TLS socket connection failed: ' . ($error !== '' ? $error : 'unknown error') . " ({$errno})"
            );
        }

        try {
            stream_set_timeout($socket, $timeout);

            $body = $method === 'post'
                ? json_encode($payload, JSON_UNESCAPED_SLASHES)
                : '';

            if ($body === false) {
                throw new RuntimeException(
                    'Unable to encode BD Courier request payload.'
                );
            }

            $headers = [
                strtoupper($method) . ' ' . $path . ' HTTP/1.1',
                'Host: ' . $host,
                'Accept: application/json',
                'Accept-Encoding: identity',
                'Authorization: Bearer ' . $token,
                'api_key: ' . $token,
                'User-Agent: UpayBazar-Fraud-Checker/1.0',
                'Connection: close',
            ];

            if ($method === 'post') {
                $headers[] = 'Content-Type: application/json';
                $headers[] = 'Content-Length: ' . strlen($body);
            }

            $request = implode("\r\n", $headers) . "\r\n\r\n" . $body;

            $offset = 0;
            $requestLength = strlen($request);

            while ($offset < $requestLength) {
                $written = fwrite($socket, substr($request, $offset));

                if ($written === false || $written === 0) {
                    throw new RuntimeException(
                        'Unable to write the BD Courier request to the TLS socket.'
                    );
                }

                $offset += $written;
            }

            $rawResponse = '';

            while (! feof($socket)) {
                $chunk = fread($socket, 8192);

                if ($chunk === false) {
                    throw new RuntimeException(
                        'Unable to read the BD Courier response from the TLS socket.'
                    );
                }

                $rawResponse .= $chunk;

                $meta = stream_get_meta_data($socket);

                if (($meta['timed_out'] ?? false) === true) {
                    throw new RuntimeException(
                        'BD Courier TLS socket request timed out.'
                    );
                }
            }
        } finally {
            fclose($socket);
        }

        return $this->parseRawHttpResponse($rawResponse);
    }

    private function parseRawHttpResponse(string $rawResponse): array
    {
        $headerEnd = strpos($rawResponse, "\r\n\r\n");

        if ($headerEnd === false) {
            throw new RuntimeException(
                'BD Courier returned an invalid HTTP response.'
            );
        }

        $rawHeaders = substr($rawResponse, 0, $headerEnd);
        $body = substr($rawResponse, $headerEnd + 4);
        $headerLines = explode("\r\n", $rawHeaders);
        $statusLine = array_shift($headerLines);

        if (! preg_match('/^HTTP\/\S+\s+(\d{3})/', (string) $statusLine, $matches)) {
            throw new RuntimeException(
                'BD Courier returned an invalid HTTP status line.'
            );
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

        if (
            str_contains(
                strtolower((string) ($headers['transfer-encoding'] ?? '')),
                'chunked'
            )
        ) {
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
                throw new RuntimeException(
                    'Invalid chunked response from BD Courier.'
                );
            }

            $sizeLine = trim(substr($body, $offset, $lineEnd - $offset));
            $sizeToken = explode(';', $sizeLine, 2)[0];
            $chunkSize = hexdec($sizeToken);
            $offset = $lineEnd + 2;

            if ($chunkSize === 0) {
                break;
            }

            if ($offset + $chunkSize > $length) {
                throw new RuntimeException(
                    'Incomplete chunked response from BD Courier.'
                );
            }

            $decoded .= substr($body, $offset, $chunkSize);
            $offset += $chunkSize + 2;
        }

        return $decoded;
    }

    private function laravelHttpTransportAvailable(): bool
    {
        return extension_loaded('curl') || $this->allowUrlFopenEnabled();
    }

    private function allowUrlFopenEnabled(): bool
    {
        return filter_var(
            ini_get('allow_url_fopen'),
            FILTER_VALIDATE_BOOL
        );
    }

    private function isMissingGuzzleHandlerException(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'guzzlehttp requires curl')
            || str_contains($message, 'allow_url_fopen ini setting')
            || str_contains($message, 'custom http handler');
    }

    private function logRequestFailure(
        string $url,
        string $phone,
        Throwable $exception,
        string $transport
    ): void {
        Log::error('BD Courier fraud check request failed', [
            'url' => $url,
            'host' => parse_url($url, PHP_URL_HOST),
            'phone' => $this->maskPhone($phone),
            'transport' => $transport,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'curl_loaded' => extension_loaded('curl'),
            'allow_url_fopen' => $this->allowUrlFopenEnabled(),
            'openssl_loaded' => extension_loaded('openssl'),
        ]);
    }

    private function normalizePhone(?string $phone): ?string
    {
        $phone = preg_replace('/[^0-9]/', '', (string) $phone);

        if (Str::startsWith($phone, '8801') && strlen($phone) === 13) {
            $phone = '0' . substr($phone, 3);
        } elseif (Str::startsWith($phone, '1') && strlen($phone) === 10) {
            $phone = '0' . $phone;
        }

        return Str::startsWith($phone, '01') && strlen($phone) === 11
            ? $phone
            : null;
    }

    private function maskPhone(string $phone): string
    {
        if (strlen($phone) < 7) {
            return '***';
        }

        return substr($phone, 0, 4) . '***' . substr($phone, -4);
    }

    private function formatResponse(string $phone, array $raw): array
    {
        $totalSummary = data_get($raw, 'data.totalSummary')
            ?? data_get($raw, 'data.total_summary')
            ?? data_get($raw, 'totalSummary')
            ?? data_get($raw, 'total_summary')
            ?? data_get($raw, 'summary')
            ?? [];

        $couriers = $this->extractCourierRows($raw);
        $courierCollection = collect($couriers);

        $total = $this->numberFromNullable($totalSummary, [
            'total',
            'total_order',
            'total_orders',
            'total_parcel',
            'total_delivery',
            'total_deliveries',
        ]) ?? (int) $courierCollection->sum('total');

        $success = $this->numberFromNullable($totalSummary, [
            'success',
            'successful',
            'delivered',
            'delivery_success',
            'success_parcel',
            'success_count',
            'success_delivery',
            'delivered_count',
        ]) ?? (int) $courierCollection->sum('success');

        $explicitCancel = $this->numberFromNullable($totalSummary, $this->cancelKeys());
        $cancel = $explicitCancel ?? (int) $courierCollection->sum('cancel');

        /*
         * Some courier APIs return only total + success per courier.
         * In that case cancel/return count must be calculated as total - success.
         * Example: Redx total 8, success 7, cancel missing/0 => cancel should be 1.
         */
        if ($total > 0 && $success >= 0 && $success < $total && ($explicitCancel === null || $cancel === 0)) {
            $cancel = max($cancel, $total - $success);
        }

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

            if (in_array($key, ['totalSummary', 'total_summary', 'summary'], true)) {
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
                'success_delivery',
                'delivered_count',
            ]);

            $explicitCancel = $this->numberFromNullable($value, $this->cancelKeys());
            $cancel = $explicitCancel ?? 0;

            if ($total === 0 && ($success > 0 || $cancel > 0)) {
                $total = $success + $cancel;
            }

            /*
             * Some APIs do not send cancel/return count separately.
             * If total is greater than success, treat the difference as cancel/return.
             */
            if ($total > 0 && $success >= 0 && $success < $total && ($explicitCancel === null || $cancel === 0)) {
                $cancel = max($cancel, $total - $success);
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

    private function cancelKeys(): array
    {
        return [
            'cancel',
            'cancelled',
            'canceled',
            'cancelled_count',
            'canceled_count',
            'cancel_count',
            'return',
            'returned',
            'return_count',
            'returned_count',
            'return_parcel',
            'returned_parcel',
            'return_delivery',
            'returned_delivery',
            'failed',
            'failure',
            'failed_count',
            'delivery_failed',
            'delivery_fail',
        ];
    }

    private function numberFrom(array $data, array $keys): int
    {
        return $this->numberFromNullable($data, $keys) ?? 0;
    }

    private function numberFromNullable(array $data, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];

            if (is_numeric($value)) {
                return (int) $value;
            }

            if (is_string($value)) {
                $normalized = preg_replace('/[^0-9.-]/', '', $value);

                if ($normalized !== '' && is_numeric($normalized)) {
                    return (int) $normalized;
                }
            }
        }

        return null;
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
