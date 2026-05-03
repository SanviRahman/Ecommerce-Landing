<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAKE = 'fake';

    public const PAYMENT_COD = 'cash_on_delivery';

    public const PAYMENT_STATUS_UNPAID = 'unpaid';
    public const PAYMENT_STATUS_COD_PENDING = 'cod_pending';
    public const PAYMENT_STATUS_COLLECTED = 'collected';
    public const PAYMENT_STATUS_FAILED = 'failed';

    protected $fillable = [
        'invoice_id',
        'campaign_id',
        'customer_name',
        'phone',
        'address',
        'delivery_area',
        'sub_total',
        'shipping_charge',
        'cod_charge',
        'total_amount',
        'payment_method',
        'payment_status',
        'order_status',
        'is_fake',
        'admin_note',
        'customer_note',
        'source_ip',
        'user_agent',
        'source_url',
        'confirmed_at',
        'delivered_at',
        'cancelled_at',
        'marked_fake_at',
    ];

    protected $casts = [
        'sub_total' => 'integer',
        'shipping_charge' => 'integer',
        'cod_charge' => 'integer',
        'total_amount' => 'integer',
        'is_fake' => 'boolean',
        'confirmed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'marked_fake_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusLogs()
    {
        return $this->hasMany(OrderStatusLog::class);
    }

    public function fakeLog()
    {
        return $this->hasOne(FakeOrderLog::class);
    }

    public function scopePending($query)
    {
        return $query->where('order_status', self::STATUS_PENDING);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('order_status', self::STATUS_CONFIRMED);
    }

    public function scopeProcessing($query)
    {
        return $query->where('order_status', self::STATUS_PROCESSING);
    }

    public function scopeDelivered($query)
    {
        return $query->where('order_status', self::STATUS_DELIVERED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('order_status', self::STATUS_CANCELLED);
    }

    public function scopeFake($query)
    {
        return $query->where('is_fake', true)->orWhere('order_status', self::STATUS_FAKE);
    }

    public function markAsFake(?string $reason = null): void
    {
        $this->update([
            'is_fake' => true,
            'order_status' => self::STATUS_FAKE,
            'marked_fake_at' => now(),
        ]);

        $this->fakeLog()->updateOrCreate(
            ['order_id' => $this->id],
            [
                'fake_reason' => $reason ?: 'Marked as fake by admin',
                'detected_by' => 'admin',
            ]
        );
    }

    public function restoreFromFake(): void
    {
        $this->update([
            'is_fake' => false,
            'order_status' => self::STATUS_PENDING,
            'marked_fake_at' => null,
        ]);
    }
}