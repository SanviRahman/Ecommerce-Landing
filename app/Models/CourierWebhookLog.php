<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierWebhookLog extends Model
{
    protected $fillable = [
        'courier_account_id',
        'order_id',
        'provider',
        'source',
        'event_key',
        'event_name',
        'external_event_id',
        'consignment_id',
        'tracking_code',
        'invoice',
        'merchant_order_id',
        'courier_status',
        'mapped_status',
        'signature_valid',
        'result',
        'attempts',
        'headers',
        'payload',
        'error_message',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'courier_account_id' => 'integer',
        'order_id' => 'integer',
        'signature_valid' => 'boolean',
        'attempts' => 'integer',
        'headers' => 'array',
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function courierAccount(): BelongsTo
    {
        return $this->belongsTo(CourierAccount::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
