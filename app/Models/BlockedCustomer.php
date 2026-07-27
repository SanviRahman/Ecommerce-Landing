<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlockedCustomer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'source_order_id',
        'customer_name',
        'phone',
        'ip_address',
        'block_phone',
        'block_ip',
        'reason',
        'status',
        'blocked_by',
        'unblocked_by',
        'blocked_at',
        'unblocked_at',
    ];

    protected function casts(): array
    {
        return [
            'source_order_id' => 'integer',
            'block_phone'     => 'boolean',
            'block_ip'        => 'boolean',
            'status'          => 'boolean',
            'blocked_by'      => 'integer',
            'unblocked_by'    => 'integer',
            'blocked_at'      => 'datetime',
            'unblocked_at'    => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', false);
    }

    public function sourceOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'source_order_id');
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function unblockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unblocked_by');
    }
}
