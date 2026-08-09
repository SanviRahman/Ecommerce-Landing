<?php

namespace App\Services;

use App\Models\CourierWebhookLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CourierWebhookLogger
{
    private static ?array $columns = null;

    public function start(array $data): ?CourierWebhookLog
    {
        if (! $this->available()) {
            return null;
        }

        $lookup = $this->filter([
            'provider' => $data['provider'] ?? null,
            'courier_account_id' => $data['courier_account_id'] ?? null,
            'event_key' => $data['event_key'] ?? null,
        ]);

        if (! isset($lookup['event_key'])) {
            return null;
        }

        try {
            $attributes = $this->filter($data);
            $attributes['result'] = $attributes['result'] ?? 'received';
            $attributes['received_at'] = $attributes['received_at'] ?? now();

            $log = CourierWebhookLog::query()->firstOrCreate($lookup, $attributes);

            if (! $log->wasRecentlyCreated && $this->hasColumn('attempts')) {
                $log->increment('attempts');
                $log->refresh();
            }

            return $log;
        } catch (Throwable $exception) {
            /*
             * Two identical callbacks can arrive at the same millisecond.
             * If both pass firstOrCreate's initial lookup, the database unique
             * key allows only one insert. Recover that winning row so duplicate
             * protection still works instead of processing the callback unlogged.
             */
            try {
                $existing = CourierWebhookLog::query()->where($lookup)->first();

                if ($existing) {
                    if ($this->hasColumn('attempts')) {
                        $existing->increment('attempts');
                        $existing->refresh();
                    }

                    return $existing;
                }
            } catch (Throwable $lookupException) {
                Log::warning('Existing courier webhook log could not be recovered.', [
                    'provider' => $data['provider'] ?? null,
                    'courier_account_id' => $data['courier_account_id'] ?? null,
                    'event_key' => $data['event_key'] ?? null,
                    'message' => $lookupException->getMessage(),
                ]);
            }

            Log::warning('Courier webhook log could not be created.', [
                'provider' => $data['provider'] ?? null,
                'courier_account_id' => $data['courier_account_id'] ?? null,
                'event_key' => $data['event_key'] ?? null,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function processed(?CourierWebhookLog $log, array $data = []): void
    {
        $this->safeUpdate($log, array_merge($data, [
            'result' => 'processed',
            'error_message' => null,
            'processed_at' => now(),
        ]));
    }

    public function ignored(?CourierWebhookLog $log, string $message, array $data = []): void
    {
        $this->safeUpdate($log, array_merge($data, [
            'result' => 'ignored',
            'error_message' => $message,
            'processed_at' => now(),
        ]));
    }

    public function failed(?CourierWebhookLog $log, Throwable|string $error, array $data = []): void
    {
        $message = $error instanceof Throwable ? $error->getMessage() : $error;

        $this->safeUpdate($log, array_merge($data, [
            'result' => 'failed',
            'error_message' => $message,
            'processed_at' => now(),
        ]));
    }

    public function completed(?CourierWebhookLog $log): bool
    {
        return $log !== null && in_array($log->result, ['processed', 'ignored'], true);
    }

    public function safeHeaders(array $headers): array
    {
        $blocked = [
            'authorization',
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'php-auth-pw',
            'x-pathao-signature',
            'x-pathao-merchant-webhook-integration-secret',
        ];

        foreach ($headers as $name => $value) {
            if (in_array(strtolower((string) $name), $blocked, true)) {
                unset($headers[$name]);
            }
        }

        return $headers;
    }

    private function safeUpdate(?Model $log, array $data): void
    {
        if (! $log || ! $this->available()) {
            return;
        }

        try {
            $filtered = $this->filter($data);

            if ($filtered !== []) {
                $log->update($filtered);
            }
        } catch (Throwable $exception) {
            Log::warning('Courier webhook log could not be updated.', [
                'log_id' => $log->getKey(),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function available(): bool
    {
        try {
            return Schema::hasTable('courier_webhook_logs');
        } catch (Throwable) {
            return false;
        }
    }

    private function filter(array $data): array
    {
        $columns = array_flip($this->columns());

        return array_intersect_key($data, $columns);
    }

    private function columns(): array
    {
        if (self::$columns !== null) {
            return self::$columns;
        }

        try {
            return self::$columns = Schema::getColumnListing('courier_webhook_logs');
        } catch (Throwable) {
            return self::$columns = [];
        }
    }

    private function hasColumn(string $column): bool
    {
        return in_array($column, $this->columns(), true);
    }
}
