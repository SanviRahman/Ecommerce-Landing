<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogRequestLifecycle
{
    /**
     * Log every Laravel HTTP request lifecycle without changing application behavior.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->requestId();
        $startedAt = microtime(true);

        $this->safeLog('info', 'Feature request started', $this->requestContext($request, $requestId));

        try {
            /** @var Response $response */
            $response = $next($request);

            $this->safeLog('info', 'Feature request completed', array_merge(
                $this->requestContext($request, $requestId),
                [
                    'status' => $response->getStatusCode(),
                    'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
                ]
            ));

            return $response;
        } catch (Throwable $exception) {
            $this->safeLog('error', 'Feature request failed', array_merge(
                $this->requestContext($request, $requestId),
                [
                    'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ]
            ));

            // Preserve Laravel's existing exception flow/rendering/reporting.
            throw $exception;
        }
    }

    private function requestId(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (Throwable) {
            return uniqid('req_', true);
        }
    }

    private function requestContext(Request $request, string $requestId): array
    {
        $context = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => $request->path(),
            'route_name' => null,
            'route_action' => null,
            'user_id' => null,
            'ip' => $request->ip(),
        ];

        try {
            $route = $request->route();
            $context['route_name'] = $route?->getName();
            $context['route_action'] = $route?->getActionName();
        } catch (Throwable) {
            // Request logging must never interrupt route execution.
        }

        try {
            $context['user_id'] = $request->user()?->getAuthIdentifier();
        } catch (Throwable) {
            // Authentication context may not be available in early middleware.
        }

        return $context;
    }

    private function safeLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::log($level, $message, $context);
        } catch (Throwable $loggingException) {
            // Logging failure must never break an otherwise working feature.
            @error_log(sprintf(
                '[feature-log-fallback] %s: %s',
                $message,
                $loggingException->getMessage()
            ));
        }
    }
}
