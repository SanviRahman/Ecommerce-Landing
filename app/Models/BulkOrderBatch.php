<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulkOrderBatch extends Model
{
    protected $fillable = [
        'batch_uid',
        'created_by',
        'created_via',
        'raw_input',
        'total_orders',
        'total_customers',
        'total_items',
        'total_amount',
    ];

    protected $casts = [
        'created_by'     => 'integer',
        'total_orders'   => 'integer',
        'total_customers'=> 'integer',
        'total_items'    => 'integer',
        'total_amount'   => 'decimal:2',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'bulk_order_batch_id');
    }
}
