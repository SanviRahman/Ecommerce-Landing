<?php

namespace App\Services;

use App\Models\BlockedCustomer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderBlockService
{
    public function normalizePhone(?string $phone): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone) ?: '';

        return $phone !== '' ? $phone : null;
    }

    public function normalizeIp(?string $ipAddress): ?string
    {
        $ipAddress = trim((string) $ipAddress);

        if ($ipAddress === '' || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $ipAddress;
    }

    public function findActiveBlocks(?string $phone, ?string $ipAddress): Collection
    {
        $phone = $this->normalizePhone($phone);
        $ipAddress = $this->normalizeIp($ipAddress);

        if (! $phone && ! $ipAddress) {
            return collect();
        }

        return BlockedCustomer::query()
            ->active()
            ->where(function (Builder $query) use ($phone, $ipAddress) {
                if ($phone) {
                    $query->where(function (Builder $phoneQuery) use ($phone) {
                        $phoneQuery->where('block_phone', true)
                            ->where('phone', $phone);
                    });
                }

                if ($ipAddress) {
                    $method = $phone ? 'orWhere' : 'where';

                    $query->{$method}(function (Builder $ipQuery) use ($ipAddress) {
                        $ipQuery->where('block_ip', true)
                            ->where('ip_address', $ipAddress);
                    });
                }
            })
            ->with(['sourceOrder:id,invoice_id,assigned_employee_id', 'blockedBy:id,name,email'])
            ->latest('id')
            ->get();
    }

    public function findActiveBlock(?string $phone, ?string $ipAddress): ?BlockedCustomer
    {
        return $this->findActiveBlocks($phone, $ipAddress)->first();
    }

    public function createBlock(array $data, User $actor): BlockedCustomer
    {
        $payload = $this->preparePayload($data);

        return DB::transaction(function () use ($payload, $actor) {
            $conflict = $this->conflictQuery($payload)
                ->lockForUpdate()
                ->first();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'block_phone' => "This phone or IP is already active in block record #{$conflict->id}.",
                ]);
            }

            return BlockedCustomer::query()->create(array_merge($payload, [
                'status'       => true,
                'blocked_by'   => $actor->id,
                'blocked_at'   => now(),
                'unblocked_by' => null,
                'unblocked_at' => null,
            ]));
        });
    }

    public function updateBlock(BlockedCustomer $blockedCustomer, array $data, User $actor): BlockedCustomer
    {
        $payload = $this->preparePayload($data);

        return DB::transaction(function () use ($blockedCustomer, $payload, $actor) {
            $blockedCustomer = BlockedCustomer::query()
                ->lockForUpdate()
                ->findOrFail($blockedCustomer->id);

            if ($blockedCustomer->status) {
                $conflict = $this->conflictQuery($payload, $blockedCustomer->id)
                    ->lockForUpdate()
                    ->first();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'block_phone' => "This phone or IP is already active in block record #{$conflict->id}.",
                    ]);
                }
            }

            $blockedCustomer->update($payload);

            return $blockedCustomer->fresh([
                'sourceOrder:id,invoice_id,assigned_employee_id',
                'blockedBy:id,name,email',
                'unblockedBy:id,name,email',
            ]);
        });
    }

    public function setStatus(BlockedCustomer $blockedCustomer, bool $status, User $actor): BlockedCustomer
    {
        return DB::transaction(function () use ($blockedCustomer, $status, $actor) {
            $blockedCustomer = BlockedCustomer::query()
                ->lockForUpdate()
                ->findOrFail($blockedCustomer->id);

            if ($status) {
                $payload = $this->preparePayload($blockedCustomer->only([
                    'source_order_id',
                    'customer_name',
                    'phone',
                    'ip_address',
                    'block_phone',
                    'block_ip',
                    'reason',
                ]));

                $conflict = $this->conflictQuery($payload, $blockedCustomer->id)
                    ->lockForUpdate()
                    ->first();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'status' => "This phone or IP is already active in block record #{$conflict->id}.",
                    ]);
                }

                $blockedCustomer->update([
                    'status'       => true,
                    'blocked_by'   => $actor->id,
                    'blocked_at'   => now(),
                    'unblocked_by' => null,
                    'unblocked_at' => null,
                ]);
            } else {
                $blockedCustomer->update([
                    'status'       => false,
                    'unblocked_by' => $actor->id,
                    'unblocked_at' => now(),
                ]);
            }

            return $blockedCustomer->fresh([
                'sourceOrder:id,invoice_id,assigned_employee_id',
                'blockedBy:id,name,email',
                'unblockedBy:id,name,email',
            ]);
        });
    }

    private function preparePayload(array $data): array
    {
        $blockPhone = filter_var($data['block_phone'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $blockIp = filter_var($data['block_ip'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $phone = $this->normalizePhone($data['phone'] ?? null);
        $ipAddress = $this->normalizeIp($data['ip_address'] ?? null);

        $errors = [];

        if (! $blockPhone && ! $blockIp) {
            $errors['block_phone'] = 'Select at least one identifier: phone or IP address.';
        }

        if ($blockPhone && (! $phone || ! preg_match('/^01\d{9}$/', $phone))) {
            $errors['phone'] = 'A valid 11-digit Bangladeshi phone number is required when phone blocking is selected.';
        }

        if ($blockIp && ! $ipAddress) {
            $errors['ip_address'] = 'A valid IPv4 or IPv6 address is required when IP blocking is selected.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $customerName = trim((string) ($data['customer_name'] ?? ''));
        $reason = trim((string) ($data['reason'] ?? ''));

        return [
            'source_order_id' => ! empty($data['source_order_id'])
                ? (int) $data['source_order_id']
                : null,
            'customer_name'   => $customerName !== '' ? $customerName : null,
            'phone'           => $phone,
            'ip_address'      => $ipAddress,
            'block_phone'     => $blockPhone,
            'block_ip'        => $blockIp,
            'reason'          => $reason !== '' ? $reason : null,
        ];
    }

    private function conflictQuery(array $payload, ?int $ignoreId = null): Builder
    {
        $query = BlockedCustomer::query()->active();

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->where(function (Builder $matchQuery) use ($payload) {
            $hasPhoneCondition = (bool) $payload['block_phone'] && ! empty($payload['phone']);
            $hasIpCondition = (bool) $payload['block_ip'] && ! empty($payload['ip_address']);

            if ($hasPhoneCondition) {
                $matchQuery->where(function (Builder $phoneQuery) use ($payload) {
                    $phoneQuery->where('block_phone', true)
                        ->where('phone', $payload['phone']);
                });
            }

            if ($hasIpCondition) {
                $method = $hasPhoneCondition ? 'orWhere' : 'where';

                $matchQuery->{$method}(function (Builder $ipQuery) use ($payload) {
                    $ipQuery->where('block_ip', true)
                        ->where('ip_address', $payload['ip_address']);
                });
            }
        });
    }
}
