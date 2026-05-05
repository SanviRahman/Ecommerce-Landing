<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'report_uid',
        'title',
        'report_type',
        'date_from',
        'date_to',
        'group_by',
        'format',
        'filters',
        'columns',
        'summary',
        'file_name',
        'file_path',
        'file_disk',
        'mime_type',
        'file_size',
        'status',
        'error_message',
        'generated_by',
        'generated_at',
    ];

    protected $casts = [
        'date_from'    => 'date',
        'date_to'      => 'date',
        'filters'      => 'array',
        'columns'      => 'array',
        'summary'      => 'array',
        'generated_at' => 'datetime',
    ];

    public const TYPE_ORDER = 'order_report';
    public const TYPE_SALES = 'sales_report';
    public const TYPE_CAMPAIGN = 'campaign_report';
    public const TYPE_PRODUCT = 'product_report';
    public const TYPE_EMPLOYEE_ORDER = 'employee_order_report';
    public const TYPE_FAKE_ORDER = 'fake_order_report';
    public const TYPE_PAYMENT = 'payment_report';
    public const TYPE_CUSTOMER = 'customer_report';
    public const TYPE_DELIVERY = 'delivery_report';
    public const TYPE_TRACKING_PIXEL = 'tracking_pixel_report';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeType($query, string $type)
    {
        return $query->where('report_type', $type);
    }
}