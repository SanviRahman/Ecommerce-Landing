<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FakeOrderLog extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'fake_reason',
        'detected_by',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeSystemDetected($query)
    {
        return $query->where('detected_by', 'system');
    }

    public function scopeAdminDetected($query)
    {
        return $query->where('detected_by', 'admin');
    }
}