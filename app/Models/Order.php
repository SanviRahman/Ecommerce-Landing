<?php

namespace App\Models;

use App\Services\OrderAssignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING    = 'pending';
    public const STATUS_CONFIRMED  = 'confirmed';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED    = 'shipped';
    public const STATUS_DELIVERED  = 'delivered';
    public const STATUS_CANCELLED  = 'cancelled';
    public const STATUS_FAKE       = 'fake';

    public const PAYMENT_COD                = 'cash_on_delivery';
    public const PAYMENT_STATUS_UNPAID      = 'unpaid';
    public const PAYMENT_STATUS_COD_PENDING = 'cod_pending';
    public const PAYMENT_STATUS_COLLECTED   = 'collected';
    public const PAYMENT_STATUS_FAILED      = 'failed';

    protected $fillable = [
        'invoice_id',
        'campaign_id',
        'assigned_employee_id',

        'customer_name',
        'phone',
        'address',
        'delivery_area',
        'success_token',
        /*
        |--------------------------------------------------------------------------
        | Courier
        |--------------------------------------------------------------------------
        | courier_id = Add Courier CRUD থেকে selected courier
        | courier_account_id = API account, only SteadFast/Pathao API send এর জন্য
        | courier_service = courier code, filter/send logic এর জন্য রাখা হলো
        |--------------------------------------------------------------------------
        */
        'courier_service',
        'courier_account_id',
        'courier_id',

        'sub_total',
        'shipping_charge',
        'is_free_delivery',
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

        'steadfast_consignment_id',
        'steadfast_tracking_code',
        'steadfast_status',
        'steadfast_note',
        'steadfast_response',
        'steadfast_sent_at',
        'steadfast_synced_at',

        'pathao_consignment_id',
        'pathao_merchant_order_id',
        'pathao_status',
        'pathao_delivery_fee',
        'pathao_note',
        'pathao_response',
        'pathao_sent_at',
        'pathao_synced_at',
    ];

    protected $casts = [
        'campaign_id'          => 'integer',
        'assigned_employee_id' => 'integer',
        'courier_account_id'   => 'integer',
        'courier_id'           => 'integer',

        'sub_total'            => 'decimal:2',
        'shipping_charge'      => 'decimal:2',
        'is_free_delivery'     => 'boolean',
        'cod_charge'           => 'decimal:2',
        'total_amount'         => 'decimal:2',

        'is_fake'              => 'boolean',

        'confirmed_at'         => 'datetime',
        'delivered_at'         => 'datetime',
        'cancelled_at'         => 'datetime',
        'marked_fake_at'       => 'datetime',

        'steadfast_response'   => 'array',
        'steadfast_sent_at'    => 'datetime',
        'steadfast_synced_at'  => 'datetime',

        'pathao_delivery_fee'  => 'decimal:2',
        'pathao_response'      => 'array',
        'pathao_sent_at'       => 'datetime',
        'pathao_synced_at'     => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (Order $order) {
            if (! $order->assigned_employee_id) {
                app(OrderAssignmentService::class)->assign($order);
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_employee_id');
    }

    public function courierAccount(): BelongsTo
    {
        return $this->belongsTo(CourierAccount::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }

    public function fakeLogs(): HasMany
    {
        return $this->hasMany(FakeOrderLog::class);
    }

    public function getCourierNameAttribute(): string
    {
        if ($this->courier) {
            return $this->courier->name;
        }

        if ($this->courierAccount) {
            return $this->courierAccount->name;
        }

        if ($this->courier_service) {
            return ucwords(str_replace('_', ' ', $this->courier_service));
        }

        return 'No Courier';
    }

    public function getIsCourierSelectedAttribute(): bool
    {
        return ! empty($this->courier_id) && ! empty($this->courier_service);
    }

    public function scopeForLoggedInUser(Builder $query): Builder
    {
        $user = auth()->user();

        if ($user && $user->isEmployee()) {
            return $query->where('assigned_employee_id', $user->id);
        }

        return $query;
    }

    public function scopeAssignedToEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('assigned_employee_id', $employeeId);
    }

    public function scopeFake(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('is_fake', true)
                ->orWhere('order_status', self::STATUS_FAKE);
        });
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('order_status', self::STATUS_PENDING);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('order_status', self::STATUS_CONFIRMED);
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('order_status', self::STATUS_PROCESSING);
    }

    public function scopeShipped(Builder $query): Builder
    {
        return $query->where('order_status', self::STATUS_SHIPPED);
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('order_status', self::STATUS_DELIVERED);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('order_status', self::STATUS_CANCELLED);
    }
}