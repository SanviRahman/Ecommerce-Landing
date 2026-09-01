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

class PathaoCourierService
{
    private int $timeout;

    private int $connectTimeout;

    private bool $forceIpv4;

    private bool $verifySsl;

    public function __construct(
        private readonly PathaoStatusService $statusService
    ) {
        // Reuse the proven transport settings already used by SteadFast.
        // This keeps deployment compatible with the existing cPanel .env/config.
        $this->timeout = max(5, (int) config('steadfast.timeout', 30));
        $this->connectTimeout = max(3, (int) config('steadfast.connect_timeout', 10));
        $this->forceIpv4 = (bool) config('steadfast.force_ipv4', true);
        $this->verifySsl = (bool) config('steadfast.verify_ssl', true);
    }

    public function createOrder(Order $order): array
    {
        $order->loadMissing(['items', 'courierAccount']);

        $courier = $this->resolveCourierAccount($order);

        if (strtolower((string) $courier->code) !== 'pathao') {
            throw new RuntimeException('Selected courier is not Pathao.');
        }

        $this->ensureConfigured($courier);

        if ($order->pathao_consignment_id) {
            throw new RuntimeException('This order already sent to Pathao.');
        }

        $url = $this->baseUrl($courier) . '/aladdin/api/v1/orders';

        try {
            $payload = $this->makeOrderPayload($order, $courier);
        } catch (RuntimeException $exception) {
            $order->update([
                'pathao_note' => $exception->getMessage(),
                'pathao_response' => [
                    'type' => 'local_validation',
                    'message' => $exception->getMessage(),
                ],
                'pathao_synced_at' => now(),
            ]);

            Log::warning('Pathao order payload validation failed', [
                'order_id' => $order->id,
                'invoice' => $order->invoice_id,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        try {
            $result = $this->sendOrderRequest($courier, $payload);

            if ($result['status'] === 401) {
                $this->refreshToken($courier);
                $courier = $courier->fresh();
                $result = $this->sendOrderRequest($courier, $payload);
            }
        } catch (Throwable $exception) {
            $message = $this->requestFailureMessage($courier);

            $order->update([
                'pathao_note' => $message,
                'pathao_response' => [
                    'error' => $exception->getMessage(),
                    'exception' => $exception::class,
                    'base_url' => $this->baseUrl($courier),
                    'curl_loaded' => extension_loaded('curl'),
                    'allow_url_fopen' => $this->allowUrlFopenEnabled(),
                    'openssl_loaded' => extension_loaded('openssl'),
                ],
                'pathao_synced_at' => now(),
            ]);

            Log::error('Pathao create order request failed', [
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

        if (! $this->isSuccessfulHttpStatus($result['status'])) {
            $message = $this->responseMessage($data, 'Pathao order create failed.');

            $order->update([
                'pathao_note' => $message,
                'pathao_response' => $data,
                'pathao_synced_at' => now(),
            ]);

            Log::warning('Pathao create order returned a failed response', [
                'order_id' => $order->id,
                'invoice' => $order->invoice_id,
                'transport' => $result['transport'],
                'http_status' => $result['status'],
                'message' => Str::limit((string) $message, 500),
                'errors' => $this->validationErrors($data),
            ]);

            throw new RuntimeException($message);
        }

        $consignmentId = data_get($data, 'data.consignment_id')
            ?: data_get($data, 'consignment_id');

        $merchantOrderId = data_get($data, 'data.merchant_order_id')
            ?: data_get($data, 'merchant_order_id')
            ?: $order->invoice_id;

        $orderStatus = data_get($data, 'data.order_status')
            ?: data_get($data, 'order_status')
            ?: 'order_created';

        $deliveryFee = data_get($data, 'data.delivery_fee')
            ?: data_get($data, 'delivery_fee')
            ?: 0;

        $order->update([
            'pathao_consignment_id' => $consignmentId,
            'pathao_merchant_order_id' => $merchantOrderId,
            'pathao_status' => $this->statusService->normalize((string) $orderStatus),
            'pathao_delivery_fee' => (float) $deliveryFee,
            'pathao_note' => data_get($data, 'message', 'Pathao order created successfully.'),
            'pathao_response' => $data,
            'pathao_sent_at' => now(),
            'pathao_synced_at' => now(),
        ]);

        Log::info('Pathao order created successfully', [
            'order_id' => $order->id,
            'invoice' => $order->invoice_id,
            'transport' => $result['transport'],
            'http_status' => $result['status'],
            'consignment_id' => $consignmentId,
        ]);

        return $data;
    }

    public function syncStatus(Order $order): array
    {
        $order->loadMissing('courierAccount');

        $courier = $this->resolveCourierAccount($order);

        if (strtolower((string) $courier->code) !== 'pathao') {
            throw new RuntimeException('Selected courier is not Pathao.');
        }

        $this->ensureAuthenticationConfigured($courier);

        if (blank($order->pathao_consignment_id)) {
            throw new RuntimeException('Pathao consignment ID is missing for this order.');
        }

        $url = $this->baseUrl($courier)
            . '/aladdin/api/v1/orders/'
            . urlencode((string) $order->pathao_consignment_id)
            . '/info';

        try {
            $result = $this->sendStatusRequest($courier, (string) $order->pathao_consignment_id);

            if ($result['status'] === 401) {
                $this->refreshToken($courier);
                $courier = $courier->fresh();
                $result = $this->sendStatusRequest(
                    $courier,
                    (string) $order->pathao_consignment_id
                );
            }
        } catch (Throwable $exception) {
            $message = 'Pathao status connection failed. Please check courier base URL. Current URL: '
                . $this->baseUrl($courier);

            $order->update([
                'pathao_note' => $message,
                'pathao_response' => [
                    'error' => $exception->getMessage(),
                    'exception' => $exception::class,
                    'base_url' => $this->baseUrl($courier),
                ],
                'pathao_synced_at' => now(),
            ]);

            Log::error('Pathao status sync request failed', [
                'order_id' => $order->id,
                'invoice' => $order->invoice_id,
                'host' => parse_url($url, PHP_URL_HOST),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException($message, 0, $exception);
        }

        $data = $result['data'];

        if ($result['status'] === 429) {
            $retryAfter = max(1, (int) ($result['headers']['retry-after'] ?? 60));

            throw new RuntimeException(
                'Pathao API rate limit reached (HTTP 429). Retry after '
                . $retryAfter
                . ' seconds.'
            );
        }

        if (! $this->isSuccessfulHttpStatus($result['status'])) {
            $message = $this->responseMessage($data, 'Pathao status sync failed.');

            $order->update([
                'pathao_note' => $message,
                'pathao_response' => $data,
                'pathao_synced_at' => now(),
            ]);

            Log::warning('Pathao status sync returned a failed response', [
                'order_id' => $order->id,
                'invoice' => $order->invoice_id,
                'transport' => $result['transport'],
                'http_status' => $result['status'],
                'message' => Str::limit((string) $message, 500),
            ]);

            throw new RuntimeException($message);
        }

        $status = $this->statusService->statusFromPayload($data);

        if ($status === '') {
            $order->update([
                'pathao_note' => 'Pathao status response did not contain an order status.',
                'pathao_response' => $data,
                'pathao_synced_at' => now(),
            ]);

            throw new RuntimeException('Pathao response did not contain an order status.');
        }

        $this->statusService->apply(
            $order,
            $courier,
            $status,
            $data,
            'api'
        );

        Log::info('Pathao status synced successfully', [
            'order_id' => $order->id,
            'invoice' => $order->invoice_id,
            'transport' => $result['transport'],
            'http_status' => $result['status'],
            'courier_status' => $status,
        ]);

        return $data;
    }

    public function refreshToken(CourierAccount $courier): array
    {
        $this->ensureAuthenticationConfigured($courier);
        $url = $this->baseUrl($courier) . '/aladdin/api/v1/external/login';

        try {
            $result = $this->issueModernToken($courier);
            $data = $result['data'];

            /*
             * Current Pathao authentication uses Client ID + Client Secret.
             * Some older merchant integrations still provide username/password,
             * so a compatibility request is attempted only when modern login fails.
             */
            if (! $this->isSuccessfulHttpStatus($result['status']) || blank($this->accessTokenFrom($data))) {
                $legacyResult = $this->issueLegacyPasswordToken($courier);

                if ($legacyResult) {
                    $legacyData = $legacyResult['data'];

                    if (
                        $this->isSuccessfulHttpStatus($legacyResult['status'])
                        && filled($this->accessTokenFrom($legacyData))
                    ) {
                        $result = $legacyResult;
                        $data = $legacyData;
                    }
                }
            }
        } catch (Throwable $exception) {
            Log::error('Pathao token request failed', [
                'courier_account_id' => $courier->id,
                'courier_account_name' => $courier->name,
                'host' => parse_url($url, PHP_URL_HOST),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'curl_loaded' => extension_loaded('curl'),
                'allow_url_fopen' => $this->allowUrlFopenEnabled(),
                'openssl_loaded' => extension_loaded('openssl'),
            ]);

            throw new RuntimeException($this->requestFailureMessage($courier), 0, $exception);
        }

        if (! $this->isSuccessfulHttpStatus($result['status']) || blank($this->accessTokenFrom($data))) {
            $message = $this->responseMessage(
                $data,
                'Pathao token generation failed. Check Client ID, Client Secret, username and password.'
            );

            Log::warning('Pathao token generation returned a failed response', [
                'courier_account_id' => $courier->id,
                'courier_account_name' => $courier->name,
                'transport' => $result['transport'],
                'http_status' => $result['status'],
                'message' => Str::limit($message, 500),
            ]);

            throw new RuntimeException($message);
        }

        $token = $this->persistToken($courier, $data);

        Log::info('Pathao access token generated successfully', [
            'courier_account_id' => $courier->id,
            'courier_account_name' => $courier->name,
            'transport' => $result['transport'],
            'http_status' => $result['status'],
            'expires_at' => optional($token['expires_at'] ?? null)->toIso8601String(),
        ]);

        return $token;
    }

    public function accessToken(CourierAccount $courier, bool $forceRefresh = false): string
    {
        if (! $forceRefresh && $this->hasUsableToken($courier)) {
            return trim((string) $courier->token);
        }

        $tokenData = $this->refreshToken($courier);

        return (string) $tokenData['access_token'];
    }

    public function tokenStatus(CourierAccount $courier): array
    {
        if (blank($courier->token)) {
            return [
                'state' => 'missing',
                'label' => 'Token Missing',
                'expires_at' => null,
            ];
        }

        if ($courier->token_expires_at && $courier->token_expires_at->isPast()) {
            return [
                'state' => 'expired',
                'label' => 'Token Expired',
                'expires_at' => $courier->token_expires_at,
            ];
        }

        return [
            'state' => 'active',
            'label' => 'Token Available',
            'expires_at' => $courier->token_expires_at,
        ];
    }

    private function sendOrderRequest(CourierAccount $courier, array $payload): array
    {
        $token = $this->accessToken($courier);

        return $this->request(
            'POST',
            $this->baseUrl($courier) . '/aladdin/api/v1/orders',
            $payload,
            [
                'source' => 'laravel',
                'Authorization' => 'Bearer ' . $token,
            ]
        );
    }

    private function sendStatusRequest(
        CourierAccount $courier,
        string $consignmentId
    ): array {
        $token = $this->accessToken($courier);

        return $this->request(
            'GET',
            $this->baseUrl($courier)
                . '/aladdin/api/v1/orders/'
                . urlencode($consignmentId)
                . '/info',
            null,
            [
                'source' => 'laravel',
                'Authorization' => 'Bearer ' . $token,
            ]
        );
    }

    private function issueModernToken(CourierAccount $courier): array
    {
        return $this->request(
            'POST',
            $this->baseUrl($courier) . '/aladdin/api/v1/external/login',
            [
                'client_id' => trim((string) $courier->api_key),
                'client_secret' => trim((string) $courier->secret_key),
            ]
        );
    }

    private function issueLegacyPasswordToken(CourierAccount $courier): ?array
    {
        if (blank($courier->auth_username) || blank($courier->auth_password)) {
            return null;
        }

        return $this->request(
            'POST',
            $this->baseUrl($courier) . '/aladdin/api/v1/issue-token',
            [
                'grant_type' => 'password',
                'client_id' => trim((string) $courier->api_key),
                'client_secret' => trim((string) $courier->secret_key),
                'username' => trim((string) $courier->auth_username),
                'password' => (string) $courier->auth_password,
            ]
        );
    }

    private function request(
        string $method,
        string $url,
        ?array $payload = null,
        array $headers = []
    ): array {
        $method = strtoupper($method);

        if ($this->laravelHttpTransportAvailable()) {
            try {
                $response = match ($method) {
                    'GET' => $this->client($headers)->get($url),
                    'POST' => $this->client($headers)->post($url, $payload ?? []),
                    default => throw new RuntimeException('Unsupported Pathao HTTP method.'),
                };

                return $this->resultFromLaravelResponse($response, 'laravel-http');
            } catch (ConnectionException $exception) {
                return $this->requestWithFallbackAfterFailure(
                    $method,
                    $url,
                    $payload,
                    $headers,
                    $exception
                );
            } catch (Throwable $exception) {
                if (! $this->isMissingGuzzleHandlerException($exception)) {
                    throw $exception;
                }

                return $this->requestWithFallbackAfterFailure(
                    $method,
                    $url,
                    $payload,
                    $headers,
                    $exception
                );
            }
        }

        Log::warning('Pathao Laravel HTTP transport unavailable; using TLS socket fallback.', [
            'host' => parse_url($url, PHP_URL_HOST),
            'curl_loaded' => extension_loaded('curl'),
            'allow_url_fopen' => $this->allowUrlFopenEnabled(),
            'openssl_loaded' => extension_loaded('openssl'),
        ]);

        return $this->requestUsingTlsSocket($method, $url, $payload, $headers);
    }

    private function requestWithFallbackAfterFailure(
        string $method,
        string $url,
        ?array $payload,
        array $headers,
        Throwable $exception
    ): array {
        Log::warning('Pathao Laravel HTTP request failed; retrying with TLS socket fallback.', [
            'host' => parse_url($url, PHP_URL_HOST),
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'curl_loaded' => extension_loaded('curl'),
            'allow_url_fopen' => $this->allowUrlFopenEnabled(),
            'openssl_loaded' => extension_loaded('openssl'),
        ]);

        try {
            return $this->requestUsingTlsSocket($method, $url, $payload, $headers);
        } catch (Throwable $fallbackException) {
            Log::error('Pathao TLS socket fallback failed', [
                'host' => parse_url($url, PHP_URL_HOST),
                'exception' => $fallbackException::class,
                'message' => $fallbackException->getMessage(),
            ]);

            throw new RuntimeException(
                'Pathao HTTP transport failed. Enable the PHP cURL extension or check outbound HTTPS access.',
                0,
                $fallbackException
            );
        }
    }

    private function client(array $headers = []): PendingRequest
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
            ->withHeaders(array_merge([
                'Content-Type' => 'application/json',
                'User-Agent' => 'Laravel-Pathao-Client/1.0',
            ], $headers));
    }

    private function requestUsingTlsSocket(
        string $method,
        string $url,
        ?array $payload = null,
        array $extraHeaders = []
    ): array {
        if (! function_exists('stream_socket_client')) {
            throw new RuntimeException('stream_socket_client is unavailable. Enable PHP cURL in cPanel.');
        }

        if (! extension_loaded('openssl')) {
            throw new RuntimeException('PHP OpenSSL extension is required for the Pathao HTTPS fallback transport.');
        }

        $parts = parse_url($url);

        if (
            ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
        ) {
            throw new RuntimeException('Pathao TLS socket fallback requires a valid HTTPS URL.');
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
                'Pathao TLS connection failed: ' . ($error !== '' ? $error : 'unknown error') . " ({$errno})"
            );
        }

        try {
            stream_set_timeout($socket, $this->timeout);

            $body = '';

            if ($method === 'POST') {
                $body = json_encode($payload ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                if ($body === false) {
                    throw new RuntimeException('Unable to encode Pathao request payload.');
                }
            }

            $hostHeader = $port === 443 ? $host : $host . ':' . $port;
            $headers = [
                $method . ' ' . $path . ' HTTP/1.1',
                'Host: ' . $hostHeader,
                'Accept: application/json',
                'Accept-Encoding: identity',
                'User-Agent: Laravel-Pathao-Client/1.0',
                'Connection: close',
            ];

            foreach ($extraHeaders as $name => $value) {
                $name = trim((string) $name);
                $value = trim((string) $value);

                if ($name === '' || $value === '') {
                    continue;
                }

                $headers[] = $name . ': ' . $value;
            }

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
                    throw new RuntimeException('Unable to write the Pathao request to the TLS socket.');
                }

                $offset += $written;
            }

            $rawResponse = '';

            while (! feof($socket)) {
                $chunk = fread($socket, 8192);

                if ($chunk === false) {
                    throw new RuntimeException('Unable to read the Pathao response from the TLS socket.');
                }

                $rawResponse .= $chunk;

                $meta = stream_get_meta_data($socket);

                if (($meta['timed_out'] ?? false) === true) {
                    throw new RuntimeException('Pathao TLS socket request timed out.');
                }
            }
        } finally {
            fclose($socket);
        }

        $parsed = $this->parseRawHttpResponse($rawResponse);
        $parsed['data'] = $this->decodeBody($parsed['status'], $parsed['body']);
        $parsed['transport'] = 'tls-socket-fallback';

        $logContext = [
            'host' => $host,
            'path' => $path,
            'http_status' => $parsed['status'],
        ];

        if (! $this->isSuccessfulHttpStatus($parsed['status'])) {
            $logContext['errors'] = $this->validationErrors($parsed['data']);
            $logContext['message'] = Str::limit(
                $this->responseMessage($parsed['data'], 'Pathao request failed.'),
                500
            );
        }

        Log::info('Pathao API response via TLS socket fallback', $logContext);

        return $parsed;
    }

    private function resultFromLaravelResponse(Response $response, string $transport): array
    {
        $headers = [];

        foreach ($response->headers() as $name => $values) {
            $headers[strtolower((string) $name)] = is_array($values)
                ? implode(', ', $values)
                : (string) $values;
        }

        return [
            'status' => $response->status(),
            'headers' => $headers,
            'content_type' => (string) $response->header('Content-Type'),
            'body' => $response->body(),
            'data' => $this->decodeResponse($response),
            'transport' => $transport,
        ];
    }

    private function parseRawHttpResponse(string $rawResponse): array
    {
        if ($rawResponse === '') {
            throw new RuntimeException('Pathao returned an empty HTTP response.');
        }

        $headerEnd = strpos($rawResponse, "\r\n\r\n");

        if ($headerEnd === false) {
            throw new RuntimeException('Pathao returned an invalid HTTP response.');
        }

        $rawHeaders = substr($rawResponse, 0, $headerEnd);
        $body = substr($rawResponse, $headerEnd + 4);
        $headerLines = explode("\r\n", $rawHeaders);
        $statusLine = array_shift($headerLines);

        if (! preg_match('/^HTTP\/\S+\s+(\d{3})/', (string) $statusLine, $matches)) {
            throw new RuntimeException('Pathao returned an invalid HTTP status line.');
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
            'headers' => $headers,
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
                throw new RuntimeException('Invalid chunked response from Pathao.');
            }

            $sizeLine = trim(substr($body, $offset, $lineEnd - $offset));
            $sizeToken = explode(';', $sizeLine, 2)[0];

            if ($sizeToken === '' || ! ctype_xdigit($sizeToken)) {
                throw new RuntimeException('Invalid Pathao chunk size.');
            }

            $chunkSize = hexdec($sizeToken);
            $offset = $lineEnd + 2;

            if ($chunkSize === 0) {
                break;
            }

            if ($offset + $chunkSize > $length) {
                throw new RuntimeException('Incomplete chunked response from Pathao.');
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

    private function requestFailureMessage(CourierAccount $courier): string
    {
        return 'Pathao connection failed from this server. Check PHP cURL/OpenSSL/outbound HTTPS and courier base URL. Current URL: '
            . $this->baseUrl($courier);
    }

    private function isSuccessfulHttpStatus(int $status): bool
    {
        return $status >= 200 && $status < 300;
    }

    private function persistToken(CourierAccount $courier, array $data): array
    {
        $accessToken = $this->accessTokenFrom($data);
        $refreshToken = data_get($data, 'refresh_token')
            ?: data_get($data, 'data.refresh_token');
        $tokenType = data_get($data, 'token_type')
            ?: data_get($data, 'data.token_type')
            ?: 'Bearer';
        $expiresIn = (int) (
            data_get($data, 'expires_in')
                ?: data_get($data, 'data.expires_in')
                ?: 3600
        );

        $expiresIn = max(60, $expiresIn);
        $expiresAt = now()->addSeconds($expiresIn);

        $courier->forceFill([
            'token' => $accessToken,
            'refresh_token' => $refreshToken ?: null,
            'token_type' => $tokenType,
            'token_expires_at' => $expiresAt,
        ])->save();

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => $tokenType,
            'expires_in' => $expiresIn,
            'expires_at' => $expiresAt,
        ];
    }

    private function hasUsableToken(CourierAccount $courier): bool
    {
        if (blank($courier->token)) {
            return false;
        }

        if (! $courier->token_expires_at) {
            return true;
        }

        return $courier->token_expires_at->greaterThan(now()->addMinutes(2));
    }

    private function accessTokenFrom(array $data): ?string
    {
        $token = data_get($data, 'access_token')
            ?: data_get($data, 'data.access_token');

        return is_scalar($token) && trim((string) $token) !== ''
            ? trim((string) $token)
            : null;
    }

    private function resolveCourierAccount(Order $order): CourierAccount
    {
        $courier = $order->courierAccount;

        if (! $courier && $order->courier_account_id) {
            $courier = CourierAccount::query()->find($order->courier_account_id);
        }

        if (! $courier && $order->courier_service === 'pathao') {
            $courier = CourierAccount::query()
                ->where('code', 'pathao')
                ->where('status', true)
                ->latest()
                ->first();
        }

        if (! $courier) {
            throw new RuntimeException('Please select Pathao courier from admin order details page first.');
        }

        if (! $courier->status) {
            throw new RuntimeException('Selected Pathao API account is inactive.');
        }

        return $courier;
    }

    private function makeOrderPayload(Order $order, CourierAccount $courier): array
    {
        $order->loadMissing('items');

        $settings = $courier->settings ?? [];
        $storeId = (int) data_get($settings, 'store_id');
        $deliveryType = (int) data_get($settings, 'delivery_type', 48);
        $itemType = (int) data_get($settings, 'item_type', 2);
        $itemWeight = (float) data_get($settings, 'item_weight', 0.5);

        if ($storeId <= 0) {
            throw new RuntimeException('Pathao Store ID is invalid. Please update the Pathao courier account Store ID.');
        }

        if (! in_array($deliveryType, [12, 48], true)) {
            throw new RuntimeException('Pathao Delivery Type must be 48 (Normal) or 12 (On Demand).');
        }

        if (! in_array($itemType, [1, 2], true)) {
            throw new RuntimeException('Pathao Item Type must be 1 (Document) or 2 (Parcel).');
        }

        if ($itemWeight < 0.5 || $itemWeight > 10) {
            throw new RuntimeException('Pathao item weight must be between 0.5 KG and 10 KG.');
        }

        return [
            'store_id' => $storeId,
            'merchant_order_id' => (string) $order->invoice_id,
            'recipient_name' => $this->normalizeRecipientName($order->customer_name),
            'recipient_phone' => $this->normalizePhone($order->phone),
            'recipient_address' => $this->normalizeAddress($order->address),
            'delivery_type' => $deliveryType,
            'item_type' => $itemType,
            'special_instruction' => Str::limit($this->makeInstruction($order, $courier), 250, ''),
            'item_quantity' => max(1, (int) $order->items->sum('quantity')),
            'item_weight' => $itemWeight,
            'item_description' => Str::limit($this->makeItemDescription($order), 250, ''),
            'amount_to_collect' => max(0, (float) ($order->total_amount ?? 0)),
        ];
    }

    private function baseUrl(CourierAccount $courier): string
    {
        return rtrim($courier->base_url ?: 'https://api-hermes.pathao.com', '/');
    }

    private function ensureConfigured(?CourierAccount $courier): void
    {
        if (! $courier) {
            throw new RuntimeException('No active Pathao courier API account found.');
        }

        $this->ensureAuthenticationConfigured($courier);

        if (blank(data_get($courier->settings ?? [], 'store_id'))) {
            throw new RuntimeException('Pathao Store ID is missing. Please add Store ID in Courier API Accounts.');
        }
    }

    private function ensureAuthenticationConfigured(CourierAccount $courier): void
    {
        if (blank($courier->base_url)) {
            throw new RuntimeException('Pathao API base URL is missing.');
        }

        if (blank($courier->api_key)) {
            throw new RuntimeException('Pathao Client ID is missing.');
        }

        if (blank($courier->secret_key)) {
            throw new RuntimeException('Pathao Client Secret is missing.');
        }
    }

    private function responseMessage(array $data, string $fallback): string
    {
        $message = data_get($data, 'message')
            ?: data_get($data, 'error')
            ?: data_get($data, 'data.message')
            ?: $fallback;

        if (is_array($message)) {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $message = trim((string) $message);
        $errors = $this->validationErrors($data);

        if ($errors === []) {
            return $message !== '' ? $message : $fallback;
        }

        $details = collect($errors)
            ->map(fn ($value, $field) => $field . ': ' . $value)
            ->implode(' | ');

        if ($message === '' || strcasecmp($message, $details) === 0) {
            return $details;
        }

        return $message . ' | ' . $details;
    }

    private function validationErrors(array $data): array
    {
        $sources = [
            data_get($data, 'errors'),
            data_get($data, 'validation'),
            data_get($data, 'data.errors'),
            data_get($data, 'data.validation'),
        ];

        $result = [];

        foreach ($sources as $source) {
            if (! is_array($source)) {
                continue;
            }

            $this->flattenValidationErrors($source, $result);
        }

        return $result;
    }

    private function flattenValidationErrors(array $errors, array &$result, string $prefix = ''): void
    {
        foreach ($errors as $field => $value) {
            $key = $prefix === '' ? (string) $field : $prefix . '.' . $field;

            if (is_array($value)) {
                $hasNestedArray = collect($value)->contains(fn ($item) => is_array($item));

                if ($hasNestedArray) {
                    $this->flattenValidationErrors($value, $result, $key);
                    continue;
                }

                $messages = collect($value)
                    ->filter(fn ($item) => is_scalar($item) && trim((string) $item) !== '')
                    ->map(fn ($item) => trim((string) $item))
                    ->values()
                    ->all();

                if ($messages !== []) {
                    $result[$key] = implode(', ', $messages);
                }

                continue;
            }

            if (is_scalar($value) && trim((string) $value) !== '') {
                $result[$key] = trim((string) $value);
            }
        }
    }

    private function normalizeRecipientName(?string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $name));

        if ($name === '') {
            $name = 'Customer';
        }

        if (mb_strlen($name, 'UTF-8') < 3) {
            $name .= ' Customer';
        }

        return Str::limit($name, 100, '');
    }

    private function normalizePhone(?string $phone): string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone) ?: '';

        if (str_starts_with($phone, '880') && strlen($phone) === 13) {
            $phone = substr($phone, 2);
        }

        if (strlen($phone) === 10 && str_starts_with($phone, '1')) {
            $phone = '0' . $phone;
        }

        if (! preg_match('/^01\d{9}$/', $phone)) {
            throw new RuntimeException('Pathao recipient phone must be a valid 11 digit Bangladesh mobile number (01XXXXXXXXX).');
        }

        return $phone;
    }

    private function normalizeAddress(?string $address): string
    {
        $address = trim((string) preg_replace('/\s+/u', ' ', (string) $address));

        if (mb_strlen($address, 'UTF-8') < 10) {
            throw new RuntimeException('Pathao recipient address must be at least 10 characters. Please enter a complete delivery address before sending.');
        }

        return Str::limit($address, 220, '');
    }

    private function makeInstruction(Order $order, CourierAccount $courier): string
    {
        $notes = [];
        $defaultInstruction = data_get($courier->settings ?? [], 'special_instruction');

        if ($defaultInstruction) {
            $notes[] = $defaultInstruction;
        }

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

        return implode(' | ', $notes) ?: 'Please call before delivery.';
    }

    private function makeItemDescription(Order $order): string
    {
        $items = $order->items
            ->map(fn ($item) => $item->quantity . ' x ' . $item->product_name)
            ->implode(', ');

        return $items ?: 'Product order';
    }
}
