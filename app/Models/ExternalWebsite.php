<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Throwable;

class ExternalWebsite extends Model
{
    use SoftDeletes;

    public const INBOUND_APPROVAL_AWAITING_REQUEST = 'awaiting_request';
    public const INBOUND_APPROVAL_PENDING = 'pending';
    public const INBOUND_APPROVAL_APPROVED = 'approved';
    public const INBOUND_APPROVAL_REJECTED = 'rejected';

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'api_token',
        'token_updated_at',
        'status',
        'receive_orders',
        'send_orders',
        'auto_send_orders',
        'remote_order_endpoint',
        'remote_health_endpoint',
        'remote_api_token',
        'request_timeout',
        'notes',
        'last_order_received_at',
        'last_authenticated_at',
        'last_auth_failed_at',
        'inbound_approval_status',
        'inbound_request_received_at',
        'inbound_request_ip',
        'inbound_request_meta',
        'inbound_approved_at',
        'inbound_rejected_at',
        'last_connection_tested_at',
        'last_connection_status',
        'last_connection_message',
        'last_order_sent_at',
        'last_send_failed_at',
        'last_send_error',
    ];

    protected $hidden = [
        'api_token',
        'remote_api_token',
    ];

    protected $casts = [
        'api_token' => 'encrypted',
        'remote_api_token' => 'encrypted',
        'token_updated_at' => 'datetime',
        'status' => 'boolean',
        'receive_orders' => 'boolean',
        'send_orders' => 'boolean',
        'auto_send_orders' => 'boolean',
        'request_timeout' => 'integer',
        'last_order_received_at' => 'datetime',
        'last_authenticated_at' => 'datetime',
        'last_auth_failed_at' => 'datetime',
        'inbound_request_received_at' => 'datetime',
        'inbound_request_meta' => 'array',
        'inbound_approved_at' => 'datetime',
        'inbound_rejected_at' => 'datetime',
        'last_connection_tested_at' => 'datetime',
        'last_order_sent_at' => 'datetime',
        'last_send_failed_at' => 'datetime',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function outboundSyncs(): HasMany
    {
        return $this->hasMany(ExternalOrderSync::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeReceiving(Builder $query): Builder
    {
        return $query->where('receive_orders', true);
    }

    public function scopeSending(Builder $query): Builder
    {
        return $query->where('send_orders', true);
    }

    public function scopeAutoSending(Builder $query): Builder
    {
        return $query
            ->where('send_orders', true)
            ->where('auto_send_orders', true);
    }

    public function tokenMatches(?string $providedToken): bool
    {
        if (! is_string($providedToken) || trim($providedToken) === '') {
            return false;
        }

        try {
            $storedToken = (string) $this->api_token;
        } catch (Throwable) {
            return false;
        }

        return $storedToken !== '' && hash_equals($storedToken, trim($providedToken));
    }

    public function canReceiveOrders(): bool
    {
        return $this->status
            && $this->receive_orders
            && $this->isInboundApproved();
    }

    public function isInboundApproved(): bool
    {
        return $this->inbound_approval_status === self::INBOUND_APPROVAL_APPROVED;
    }

    public function canSendOrders(): bool
    {
        if (! $this->status || ! $this->send_orders) {
            return false;
        }

        try {
            $remoteToken = trim((string) $this->remote_api_token);
        } catch (Throwable) {
            return false;
        }

        return trim((string) $this->remote_order_endpoint) !== ''
            && $remoteToken !== '';
    }

    public function getApiEndpointAttribute(): string
    {
        return route('api.external-orders.store', [
            'externalWebsite' => $this->slug,
        ]);
    }

    public function getHealthEndpointAttribute(): string
    {
        return route('api.external-orders.status', [
            'externalWebsite' => $this->slug,
        ]);
    }

    public function getResolvedRemoteHealthEndpointAttribute(): ?string
    {
        $healthEndpoint = trim((string) $this->remote_health_endpoint);

        if ($healthEndpoint !== '') {
            return $healthEndpoint;
        }

        $orderEndpoint = trim((string) $this->remote_order_endpoint);

        if ($orderEndpoint === '') {
            return null;
        }

        return rtrim($orderEndpoint, '/') . '/status';
    }

    public function getDomainHostAttribute(): string
    {
        return (string) (parse_url($this->domain, PHP_URL_HOST) ?: $this->domain);
    }

    public function getInboundConnectionStatusAttribute(): string
    {
        if (! $this->status || ! $this->receive_orders) {
            return 'inactive';
        }

        if ($this->inbound_approval_status === self::INBOUND_APPROVAL_PENDING) {
            return 'pending_approval';
        }

        if ($this->inbound_approval_status === self::INBOUND_APPROVAL_REJECTED) {
            return 'rejected';
        }

        if ($this->isInboundApproved() && $this->last_authenticated_at) {
            return 'connected';
        }

        if ($this->last_auth_failed_at) {
            return 'authentication_failed';
        }

        return 'awaiting_request';
    }

    public function getOutboundConnectionStatusAttribute(): string
    {
        if (! $this->status || ! $this->send_orders) {
            return 'inactive';
        }

        if ($this->last_connection_status === 'connected') {
            return 'connected';
        }

        if ($this->last_connection_status === 'failed') {
            return 'failed';
        }

        return 'not_tested';
    }

    public function getConnectionStatusAttribute(): string
    {
        return $this->inbound_connection_status;
    }
}
