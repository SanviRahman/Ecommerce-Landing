<?php

namespace App\Models;

use App\Services\OrderAssignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

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
    public const STATUS_STOCK_OUT  = 'stock_out';

    public const PAYMENT_COD                = 'cash_on_delivery';
    public const PAYMENT_STATUS_UNPAID      = 'unpaid';
    public const PAYMENT_STATUS_COD_PENDING = 'cod_pending';
    public const PAYMENT_STATUS_COLLECTED   = 'collected';
    public const PAYMENT_STATUS_FAILED      = 'failed';

    public const CUSTOM_LIST_ONE = 'order_list_1';
    public const CUSTOM_LIST_TWO = 'order_list_2';

    protected $fillable = [
        'invoice_id',
        'success_token',
        'campaign_id',
        'assigned_employee_id',
        'order_field_id',
        'invoice_printed_at',
        'invoice_print_count',
        'custom_order_list',

        'customer_name',
        'phone',
        'address',
        'delivery_area',

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
        'order_field_id'       => 'integer',
        'invoice_printed_at'   => 'datetime',
        'invoice_print_count'  => 'integer',
        'custom_order_list'    => 'string',
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
        static::creating(function (Order $order) {
            if (! $order->order_status) {
                $order->order_status = self::STATUS_PROCESSING;
            }
        });

        static::updating(function (Order $order) {
            if (! $order->isDirty('order_status')) {
                return;
            }

            $now = now();
            $status = (string) $order->order_status;

            if ($status === self::STATUS_CONFIRMED && ! $order->confirmed_at) {
                $order->confirmed_at = $now;
            }

            if (in_array($status, [self::STATUS_DELIVERED, 'complete', 'completed'], true) && ! $order->delivered_at) {
                $order->delivered_at = $now;
            }

            if ($status === self::STATUS_CANCELLED && ! $order->cancelled_at) {
                $order->cancelled_at = $now;
            }

            if ($status === self::STATUS_FAKE && ! $order->marked_fake_at) {
                $order->marked_fake_at = $now;
                $order->is_fake = true;
            }
        });

        static::created(function (Order $order) {
            if (! $order->assigned_employee_id) {
                app(OrderAssignmentService::class)->assign($order);
            }

            $order->writeStatusLog($order->order_status ?: self::STATUS_PROCESSING, 'Order created.');
        });

        static::updated(function (Order $order) {
            if ($order->wasChanged('order_status')) {
                $order->writeStatusLog((string) $order->order_status, 'Order status changed.');
            }

            if ($order->wasChanged('invoice_printed_at') && $order->invoice_printed_at) {
                $order->writeStatusLog('invoiced', 'Invoice printed.');
            }
        });
    }

    public function writeStatusLog(?string $status, ?string $note = null): void
    {
        if (! $status || ! Schema::hasTable('order_status_logs')) {
            return;
        }

        try {
            OrderStatusLog::create([
                'order_id'   => $this->id,
                'status'     => $status,
                'note'       => $note,
                'created_by' => auth()->id(),
            ]);
        } catch (\Throwable $exception) {
            // Status log write should never break order flow.
        }
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

    public function orderField(): BelongsTo
    {
        return $this->belongsTo(OrderField::class, 'order_field_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }

    public function fakeLogs(): HasMany
    {
        return $this->hasMany(FakeOrderLog::class);
    }

    public static function displayTimezone(): string
    {
        return config('app.order_display_timezone', 'Asia/Dhaka');
    }

    public function localDateTime(?string $column = 'created_at'): ?Carbon
    {
        $rawValue = $this->getRawOriginal($column ?? 'created_at');

        if (! $rawValue) {
            return null;
        }

        return Carbon::parse($rawValue, 'UTC')->timezone(static::displayTimezone());
    }

    public function getLocalCreatedAtAttribute(): ?Carbon
    {
        return $this->localDateTime('created_at');
    }

    public function getLocalUpdatedAtAttribute(): ?Carbon
    {
        return $this->localDateTime('updated_at');
    }

    public function getFormattedLocalCreatedDateAttribute(): string
    {
        return $this->local_created_at?->format('d M Y') ?: '-';
    }

    public function getFormattedLocalCreatedTimeAttribute(): string
    {
        return $this->local_created_at?->format('h:i A') ?: '';
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

    public function getFirstProductImageUrlAttribute(): ?string
    {
        $firstItem = $this->relationLoaded('items')
            ? $this->items->first()
            : $this->items()->with('product')->first();

        if (! $firstItem) {
            return null;
        }

        return $firstItem->product_image_url;
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

    public function scopeStockOut(Builder $query): Builder
    {
        return $query->where('order_status', self::STATUS_STOCK_OUT);
    }

    public function scopeNewOrders(Builder $query): Builder
    {
        return $query->where('order_status', self::STATUS_PROCESSING)
            ->whereNull('custom_order_list');
    }

    public function scopeShipped(Builder $query): Builder
    {
        return $query->where('order_status', self::STATUS_SHIPPED);
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('order_status', self::STATUS_DELIVERED);
    }

    public function scopeOrderListOne(Builder $query): Builder
    {
        return $query->where('custom_order_list', self::CUSTOM_LIST_ONE);
    }

    public function scopeOrderListTwo(Builder $query): Builder
    {
        return $query->where('custom_order_list', self::CUSTOM_LIST_TWO);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('order_status', self::STATUS_CANCELLED);
    }
}
