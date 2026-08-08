<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalOrderSync extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'order_id',
        'external_website_id',
        'sync_uuid',
        'status',
        'attempts',
        'response_status',
        'response_body',
        'error_message',
        'last_attempted_at',
        'sent_at',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'external_website_id' => 'integer',
        'attempts' => 'integer',
        'response_status' => 'integer',
        'last_attempted_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function externalWebsite(): BelongsTo
    {
        return $this->belongsTo(ExternalWebsite::class)->withTrashed();
    }
}
